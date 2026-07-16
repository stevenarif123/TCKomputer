<?php
/**
 * Admin - Product Import
 * Upload CSV for preview, then confirm import from session data.
 */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


$pageTitle = "Impor Produk";

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/admin-auth.php';
require_once __DIR__ . '/../config/import.php';

requireAdmin();

$pdo = getDBConnection();
$errors = [];
$messages = [];
$imageWarnings = [];
$allowedImageBaseDir = realpath(__DIR__ . '/..');
$selectedImageFolder = trim($_POST['image_folder'] ?? '');
$canonicalImageFolder = null;
$action = $_POST['action'] ?? '';

// Session Reset Handling
if (isset($_GET['reset'])) {
    unset($_SESSION['import_data']);
    redirect('product-import');
}

function deleteDirectoryRecursive(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $files = array_diff(scandir($dir) ?: [], ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        is_dir($path) ? deleteDirectoryRecursive($path) : @unlink($path);
    }
    @rmdir($dir);
}

function saveImportZipImages(array $zipFile): ?string
{
    if (empty($zipFile['name']) || ($zipFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }
    
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'product_import_zip_' . session_id();
    if (is_dir($dir)) {
        deleteDirectoryRecursive($dir);
    }
    mkdir($dir, 0755, true);
    
    $zip = new ZipArchive();
    if ($zip->open($zipFile['tmp_name']) === true) {
        $zip->extractTo($dir);
        $zip->close();
        return $dir;
    }
    
    return null;
}

function saveImportLocalImages(array $files): ?string
{
    if (empty($files['name'][0])) {
        return null;
    }
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'product_import_' . session_id();
    if (is_dir($dir)) {
        deleteDirectoryRecursive($dir);
    }
    mkdir($dir, 0755, true);
    foreach ($files['name'] as $i => $name) {
        if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            copy($files['tmp_name'][$i], $dir . DIRECTORY_SEPARATOR . basename((string)$name));
        }
    }
    return $dir;
}

function importPreviewImageSrc(string $path): string
{
    if ($path === '' || !is_file($path)) {
        return '';
    }
    return 'import-image-view.php?file=' . urlencode(basename($path));
}

function applyImportEdits(array $importData, array $edits, array $categoryMap): array
{
    $selectedIndices = $_POST['import_selected'] ?? [];

    foreach ($importData['rows'] as $i => &$row) {
        if (!empty($row['skipped'])) {
            continue;
        }

        $rowIdx = $row['row_num'] - 1;
        
        // Skip import if this row was not checked in the UI
        if (!isset($selectedIndices[$rowIdx])) {
            $row['valid'] = false;
            $row['mapped'] = [];
            continue;
        }

        // 1. Check for manual image upload for this row
        $manualImageUploaded = !empty($_FILES['row_images']['name'][$rowIdx]) && 
                               ($_FILES['row_images']['error'][$rowIdx] === UPLOAD_ERR_OK);
        
        if ($manualImageUploaded) {
            $tmpPath = $_FILES['row_images']['tmp_name'][$rowIdx];
            $fileName = $_FILES['row_images']['name'][$rowIdx];
            $fileSize = $_FILES['row_images']['size'][$rowIdx];
            
            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
            $mime = '';
            if (class_exists('finfo')) {
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmpPath) ?: '';
            } elseif (function_exists('mime_content_type')) {
                $mime = mime_content_type($tmpPath) ?: '';
            }
            
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            
            $imageErrors = [];
            if (!in_array($mime, $allowedMimes, true) || !in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                $imageErrors[] = "Format gambar tidak didukung (gunakan JPG/PNG/WebP).";
            }
            if ($fileSize > 2 * 1024 * 1024) {
                $imageErrors[] = "Ukuran gambar melebihi batas 2MB.";
            }
            
            if (empty($imageErrors)) {
                $row['main_image_found'] = true;
                $row['main_image_source'] = $tmpPath;
                $row['main_image'] = $fileName;
                // Remove the missing image error
                $row['errors'] = array_filter($row['errors'], function($err) {
                    return !str_contains($err, 'Gambar utama wajib cocok');
                });
            } else {
                $row['errors'] = array_unique(array_merge($row['errors'], $imageErrors));
            }
        }
        
        // 2. If there are edits, apply and validate them
        if (isset($edits[$rowIdx])) {
            $edit = $edits[$rowIdx];
            
            // Check for delete main image command
            if (!empty($edit['delete_main_image'])) {
                $row['main_image_found'] = false;
                $row['main_image_source'] = '';
                $row['main_image'] = '';
            }

            // Check for delete additional images
            if (isset($edit['delete_additional']) && is_array($edit['delete_additional'])) {
                foreach ($edit['delete_additional'] as $delIdx) {
                    unset($row['additional_images'][$delIdx]);
                    unset($row['additional_images_found'][$delIdx]);
                    unset($row['additional_image_sources'][$delIdx]);
                }
                $row['additional_images'] = array_values($row['additional_images'] ?? []);
                $row['additional_images_found'] = array_values($row['additional_images_found'] ?? []);
                $row['additional_image_sources'] = array_values($row['additional_image_sources'] ?? []);
            }

            $name = trim((string)($edit['name'] ?? ''));
            $sku = trim((string)($edit['sku'] ?? ''));
            $categoryId = max(0, (int)($edit['category_id'] ?? 0));
            $stock = max(0, (int)($edit['stock'] ?? 0));
            $purchasePrice = max(0, (int)($edit['purchase_price'] ?? 0));
            $sellingPrice = max(0, (int)($edit['selling_price'] ?? 0));
            $promoPrice = trim((string)($edit['promo_price'] ?? '')) === '' ? null : max(0, (int)($edit['promo_price'] ?? 0));
            
            $brand = trim((string)($edit['brand'] ?? ''));
            $model = trim((string)($edit['model'] ?? ''));
            $description = trim((string)($edit['description'] ?? ''));
            $specification = trim((string)($edit['specification'] ?? ''));
            
            // Re-validate details
            $validationErrors = [];
            if ($name === '') {
                $validationErrors[] = 'Nama produk wajib diisi.';
            } elseif (mb_strlen($name) > 255) {
                $validationErrors[] = 'Nama produk maksimal 255 karakter.';
            }
            if ($categoryId <= 0 || !array_key_exists($categoryId, $categoryMap)) {
                $validationErrors[] = 'Kategori tidak ditemukan atau tidak aktif.';
            }
            if ($sellingPrice <= 0) {
                $validationErrors[] = 'Harga jual harus lebih dari 0.';
            }
            
            if (!$row['main_image_found']) {
                $validationErrors[] = 'Gambar utama wajib cocok sebelum produk bisa diimport.';
            }
            
            // Keep image errors, combine with current validations
            $filteredErrors = array_filter($row['errors'], function($err) {
                return str_contains($err, 'Format gambar tidak didukung') || str_contains($err, 'Ukuran gambar melebihi');
            });
            
            $row['errors'] = array_unique(array_merge($filteredErrors, $validationErrors));
            $row['valid'] = empty($row['errors']);
            
            $row['mapped'] = [
                'category_id' => $categoryId,
                'name' => $name,
                'slug' => generateSlug($name),
                'sku' => $sku,
                'stock' => $stock,
                'purchase_price' => $purchasePrice,
                'selling_price' => $sellingPrice,
                'promo_price' => $promoPrice,
                'promo_active' => $promoPrice > 0 ? 1 : 0,
                'promo_stock' => $promoPrice > 0 ? $stock : 0,
                'promo_stock_initial' => $promoPrice > 0 ? $stock : 0,
                'brand' => $brand,
                'model' => $model,
                'description' => $description,
                'specification' => $specification,
                'image' => $row['main_image'] ?? '',
                'status' => 'ready',
                'condition_type' => 'new',
                'warranty_note' => '',
                'is_featured' => 0,
                'is_active' => 1,
            ];
        }
    }
    return $importData;
}

if ($selectedImageFolder !== '') {
    $resolvedImageFolder = realpath($selectedImageFolder);
    if ($resolvedImageFolder === false || !is_dir($resolvedImageFolder)) {
        $imageWarnings[] = 'Folder gambar tidak dapat diakses. Preview dilanjutkan tanpa pencocokan gambar.';
    } elseif ($allowedImageBaseDir === false || !str_starts_with($resolvedImageFolder . DIRECTORY_SEPARATOR, $allowedImageBaseDir . DIRECTORY_SEPARATOR)) {
        $imageWarnings[] = 'Folder gambar harus berada di dalam direktori aplikasi. Preview dilanjutkan tanpa pencocokan gambar.';
    } else {
        $canonicalImageFolder = $resolvedImageFolder;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($submittedToken)) {
        $errors[] = 'Token keamanan tidak valid. Silakan coba lagi.';
    } elseif ($action === 'preview') {
        if (empty($_FILES['csv_file']['name'])) {
            $errors[] = 'File CSV wajib dipilih.';
        } elseif (($_FILES['csv_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $errors[] = 'Upload CSV gagal. Silakan coba lagi.';
        } else {
            try {
                $parsedCsv = parseImportCSV($_FILES['csv_file']['tmp_name']);
                
                $localImageFolder = null;
                if (!empty($_FILES['image_zip']['name']) && ($_FILES['image_zip']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                    $localImageFolder = saveImportZipImages($_FILES['image_zip']);
                    if ($localImageFolder === null) {
                        $imageWarnings[] = 'Gagal mengekstrak file ZIP gambar. Preview dilanjutkan tanpa pencocokan gambar.';
                    }
                } elseif (!empty($_FILES['image_files']['name'][0])) {
                    $fileCount = count(array_filter($_FILES['image_files']['name']));
                    if ($fileCount > 20) {
                        $imageWarnings[] = "Peringatan: Anda mencoba mengunggah {$fileCount} file gambar secara langsung. Konfigurasi server cPanel Anda mungkin membatasi maksimal 20 file unggahan sekaligus (max_file_uploads). Jika sebagian gambar gagal diimpor, silakan gunakan opsi Opsi A (File ZIP Gambar).";
                    }
                    $localImageFolder = saveImportLocalImages($_FILES['image_files']);
                }

                $_SESSION['import_data'] = buildImportPreviewData(
                    $parsedCsv['rows'],
                    loadActiveCategoryMap($pdo),
                    $localImageFolder ?: $canonicalImageFolder,
                    $imageWarnings
                );
                $messages[] = 'File CSV berhasil diproses untuk preview.';
            } catch (RuntimeException $e) {
                unset($_SESSION['import_data']);
                $errors[] = $e->getMessage();
            }
        }
    } elseif ($action === 'confirm') {
        if (empty($_SESSION['import_data'])) {
            $errors[] = 'Sesi import tidak ditemukan atau sudah kedaluwarsa. Silakan upload ulang file CSV.';
        } else {
            $categoryMap = loadActiveCategoryMap($pdo);
            $summary = confirmProductImport($pdo, applyImportEdits($_SESSION['import_data'], $_POST['rows'] ?? [], $categoryMap));
            unset($_SESSION['import_data']);
            redirect('products', "Import selesai: {$summary['imported']} produk berhasil, {$summary['failed']} gagal.");
        }
    } else {
        $errors[] = 'Aksi import tidak valid.';
    }
}

$importData = $_SESSION['import_data'] ?? null;

require_once __DIR__ . '/../includes/admin-header.php';
?>

<?php if (!empty($errors)): ?>
<div class="alert alert-error">
    <ul>
        <?php foreach ($errors as $error): ?>
            <li><?= sanitizeOutput($error) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php if (!empty($messages)): ?>
<div class="alert alert-success">
    <ul>
        <?php foreach ($messages as $message): ?>
            <li><?= sanitizeOutput($message) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php if (!empty($imageWarnings)): ?>
<div class="alert alert-warning">
    <ul>
        <?php foreach ($imageWarnings as $warning): ?>
            <li><?= sanitizeOutput($warning) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<style>
/* CSS Wizard Steps */
.import-steps { display: flex; gap: 20px; margin-bottom: 24px; border-bottom: 1px solid var(--admin-border); padding-bottom: 16px; }
.import-step { display: flex; align-items: center; gap: 8px; color: var(--admin-text-muted); font-weight: 600; font-size: 15px; }
.import-step.active { color: var(--admin-primary); }
.import-step.completed { color: var(--admin-success); }
.import-step-num { width: 28px; height: 28px; border-radius: 50%; background: var(--admin-border); color: var(--admin-text); display: flex; align-items: center; justify-content: center; font-size: 13px; }
.import-step.active .import-step-num { background: var(--admin-primary); color: #fff; }
.import-step.completed .import-step-num { background: var(--admin-success); color: #fff; }

/* Drag Drop and File Styles */
.drag-drop-zone { border: 2px dashed var(--admin-border); padding: 30px 20px; border-radius: 12px; text-align: center; background: rgba(255,255,255,0.02); transition: all 0.2s ease; cursor: pointer; position: relative; }
.drag-drop-zone:hover, .drag-drop-zone.dragover { border-color: var(--admin-primary); background: var(--admin-primary-light); }
.drag-drop-zone input[type="file"] { position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
.drag-drop-icon { font-size: 40px; color: var(--admin-text-muted); margin-bottom: 8px; }

/* Radio Group Selector */
.image-sources-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 20px; }
.image-source-card { border: 1px solid var(--admin-border); padding: 16px; border-radius: 12px; cursor: pointer; background: var(--admin-card-bg); transition: all 0.2s ease; position: relative; }
.image-source-card input[type="radio"] { position: absolute; top: 16px; right: 16px; width: 18px; height: 18px; accent-color: var(--admin-primary); }
.image-source-card:hover { border-color: var(--admin-primary); box-shadow: var(--admin-shadow); }
.image-source-card.is-active { border-color: var(--admin-primary); background: var(--admin-primary-light); }
.image-source-title { font-weight: 700; font-size: 14px; margin-bottom: 4px; color: var(--admin-text); }
.image-source-desc { font-size: 11px; color: var(--admin-text-muted); line-height: 1.4; }

/* Master-Detail Layout */
.master-detail-container { display: flex; gap: 20px; height: 70vh; margin-top: 16px; }
.master-list-panel { width: 35%; display: flex; flex-direction: column; background: var(--admin-card-bg); border: 1px solid var(--admin-border); border-radius: var(--admin-radius); overflow: hidden; }
.panel-search-box { padding: 12px; border-bottom: 1px solid var(--admin-border); }
.panel-search-box input { width: 100%; padding: 8px 12px; border: 1px solid var(--admin-border); border-radius: var(--admin-radius-sm); font-size: 13px; background: var(--admin-bg); color: var(--admin-text); }
.master-list-items { flex: 1; overflow-y: auto; padding: 10px; display: flex; flex-direction: column; gap: 8px; }
.product-list-card { display: flex; gap: 10px; padding: 10px; border-radius: var(--admin-radius-sm); border: 1px solid var(--admin-border); cursor: pointer; transition: all 0.15s ease; background: var(--admin-card-bg); align-items: center; position: relative; }
.product-list-card:hover { background: var(--admin-bg); }
.product-list-card.is-active { border-color: var(--admin-primary); background: var(--admin-primary-light); }
.product-list-card-thumb { width: 44px; height: 44px; border-radius: 6px; object-fit: cover; background: var(--admin-bg); border: 1px solid var(--admin-border); flex-shrink: 0; }
.product-list-card-info { flex: 1; min-width: 0; }
.product-list-card-title { font-size: 13px; font-weight: 600; color: var(--admin-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.product-list-card-sku { font-size: 11px; color: var(--admin-text-muted); }
.product-list-card-badge { display: inline-block; padding: 2px 6px; border-radius: 99px; font-size: 10px; font-weight: 700; text-transform: uppercase; margin-top: 4px; }
.product-list-card-badge.valid { background: #dcfce7; color: #166534; }
.product-list-card-badge.invalid { background: #fee2e2; color: #991b1b; }
.product-list-card-badge.skipped { background: #f3f4f6; color: #374151; }

.detail-edit-panel { width: 65%; background: var(--admin-card-bg); border: 1px solid var(--admin-border); border-radius: var(--admin-radius); display: flex; flex-direction: column; overflow: hidden; }
.detail-panel-placeholder { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; color: var(--admin-text-muted); text-align: center; padding: 40px; }
.detail-panel-form-container { flex: 1; overflow-y: auto; padding: 20px; }
.product-editor-form { display: none; }
.product-editor-form.is-active { display: block; }

.detail-errors-box { background: #fee2e2; border: 1px solid #fecaca; border-radius: 8px; padding: 12px 16px; margin-bottom: 20px; }
.detail-errors-list { color: #991b1b; margin-left: 20px; font-size: 13px; }

.editor-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; margin-bottom: 20px; }
.editor-grid-full { grid-column: 1 / -1; }
.editor-form-group { display: flex; flex-direction: column; gap: 4px; }
.editor-form-group label { font-size: 12px; font-weight: 600; color: var(--admin-text-muted); }
.editor-form-group input, .editor-form-group select, .editor-form-group textarea { padding: 9px 12px; border: 1px solid var(--admin-border); border-radius: var(--admin-radius-sm); font-size: 13px; background: var(--admin-card-bg); color: var(--admin-text); }
.editor-form-group textarea { resize: vertical; min-height: 80px; }
.editor-form-group input:focus, .editor-form-group select:focus, .editor-form-group textarea:focus { border-color: var(--admin-primary); outline: none; }

.editor-images-area { display: flex; gap: 16px; margin-top: 10px; }
.editor-image-box { width: 140px; text-align: center; }
.editor-image-preview-wrapper { width: 140px; height: 140px; border-radius: 8px; border: 1px solid var(--admin-border); background: var(--admin-bg); overflow: hidden; display: flex; align-items: center; justify-content: center; position: relative; margin-bottom: 8px; cursor: pointer; }
.editor-image-preview-wrapper img { width: 100%; height: 100%; object-fit: cover; }
.editor-image-preview-wrapper.missing { border-color: var(--admin-danger); background: rgba(220,38,38,0.02); }
.editor-image-preview-wrapper input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
.editor-image-label { font-size: 11px; font-weight: 600; word-break: break-all; }
.btn-delete-img { position: absolute; top: 4px; right: 4px; width: 22px; height: 22px; border-radius: 50%; background: rgba(220, 38, 38, 0.85); color: #fff; border: none; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: bold; cursor: pointer; z-index: 10; transition: background 0.15s; padding: 0; line-height: 1; }
.btn-delete-img:hover { background: rgba(220, 38, 38, 1); }


/* Loading overlay */
.import-loading { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center; }
.import-loading.is-active { display: flex; }
.import-loading-box { background: var(--admin-card-bg); padding: 24px; border-radius: 12px; border: 1px solid var(--admin-border); text-align: center; width: min(400px, 90vw); box-shadow: var(--admin-shadow-lg); }
</style>

<div id="importLoading" class="import-loading" aria-live="polite" aria-busy="true">
    <div class="import-loading-box">
        <strong id="importLoadingTitle">Memproses...</strong>
        <div class="import-progress" style="height:10px; background:#eee; border-radius:999px; overflow:hidden; margin:14px 0;"><div id="importProgressBar" class="import-progress-bar" style="height:100%; width:0; background:#2563eb; transition:width .2s;"></div></div>
        <small id="importLoadingText">Mohon tunggu, jangan tutup halaman.</small>
    </div>
</div>

<div class="admin-page-header">
    <h2>Impor Produk</h2>
    <a href="products" class="btn btn-secondary">&laquo; Kembali</a>
</div>

<!-- Step Tracker Header -->
<div class="import-steps">
    <div class="import-step <?= !$importData ? 'active' : 'completed' ?>">
        <span class="import-step-num"><?= !$importData ? '1' : '✓' ?></span>
        <span>Konfigurasi & Upload</span>
    </div>
    <div class="import-step <?= $importData ? 'active' : '' ?>">
        <span class="import-step-num">2</span>
        <span>Review & Sesuaikan Data</span>
    </div>
</div>

<?php if (!$importData): ?>
<div class="admin-form-container">
    <form action="" method="POST" enctype="multipart/form-data" class="admin-form js-loading-form" data-loading-title="Mengupload CSV dan gambar..." data-upload="1">
        <input type="hidden" name="csrf_token" value="<?= sanitizeOutput($csrfToken) ?>">
        <input type="hidden" name="action" value="preview">

        <div class="form-group">
            <label>Pilih File CSV <span class="required">*</span></label>
            <div class="drag-drop-zone" id="csvDragZone">
                <span class="material-symbols-outlined drag-drop-icon" style="font-size: 44px; color: var(--admin-text-muted);">csv</span>
                <p style="font-weight: 600; margin: 4px 0 2px;">Seret file CSV ke sini atau klik untuk memilih</p>
                <small class="form-help">File .csv dengan pemisah titik koma (;)</small>
                <input type="file" id="csv_file" name="csv_file" accept=".csv,text/csv" required>
            </div>
        </div>

        <div style="margin: 24px 0 16px;">
            <h3 style="font-size: 15px; margin-bottom: 12px;">Sumber Gambar Produk</h3>
            
            <div class="image-sources-grid">
                <div class="image-source-card is-active" data-target="section-zip">
                    <input type="radio" name="img_src_opt" id="opt_zip" value="zip" checked>
                    <div class="image-source-title">Opsi A: File ZIP Gambar</div>
                    <div class="image-source-desc">Unggah satu file .zip berisi semua gambar produk. Sangat andal dan direkomendasikan.</div>
                </div>
                <div class="image-source-card" data-target="section-local">
                    <input type="radio" name="img_src_opt" id="opt_local" value="local">
                    <div class="image-source-title">Opsi B: Folder Lokal</div>
                    <div class="image-source-desc">Pilih folder gambar produk dari komputer Anda (terbatas maks 20 file karena batasan cPanel).</div>
                </div>
                <div class="image-source-card" data-target="section-server">
                    <input type="radio" name="img_src_opt" id="opt_server" value="server">
                    <div class="image-source-title">Opsi C: Folder Server</div>
                    <div class="image-source-desc">Tentukan path folder server jika Anda telah mengunggah gambar via FTP/File Manager.</div>
                </div>
            </div>

            <!-- Opsi A Fields -->
            <div id="section-zip" class="form-group image-opt-section" style="background: var(--admin-bg); padding: 16px; border-radius: 8px;">
                <label for="image_zip" style="font-weight: 600;">File ZIP Gambar</label>
                <input type="file" id="image_zip" name="image_zip" accept=".zip" style="width: 100%; padding: 8px 0;">
                <small class="form-help">Pilih file `.zip` yang berisi kumpulan gambar produk.</small>
            </div>

            <!-- Opsi B Fields -->
            <div id="section-local" class="form-group image-opt-section" style="background: var(--admin-bg); padding: 16px; border-radius: 8px; display: none;">
                <label for="image_files" style="font-weight: 600;">Pilih Folder Gambar</label>
                <input type="file" id="image_files" name="image_files[]" accept="image/jpeg,image/png,image/webp" webkitdirectory multiple style="width: 100%; padding: 8px 0;">
                <small class="form-help">Pilih direktori lokal di PC Anda.</small>
            </div>

            <!-- Opsi C Fields -->
            <div id="section-server" class="form-group image-opt-section" style="background: var(--admin-bg); padding: 16px; border-radius: 8px; display: none;">
                <label for="image_folder" style="font-weight: 600;">Path Folder Gambar di Server</label>
                <input type="text" id="image_folder" name="image_folder" placeholder="Contoh: uploads/import_temp/" value="<?= sanitizeOutput($selectedImageFolder) ?>" style="width: 100%; border: 1px solid var(--admin-border); border-radius: 8px; padding: 10px; margin-top: 5px;">
                <small class="form-help">Masukkan path folder relatif terhadap direktori aplikasi.</small>
            </div>
        </div>

        <div class="form-actions" style="margin-top: 24px;">
            <button type="submit" class="btn btn-primary">Mulai Proses Preview</button>
        </div>
    </form>
</div>
<?php endif; ?>

<?php if ($importData): ?>
<div class="admin-form-container" style="margin-top: 12px; padding: 16px;">
    <!-- Stats interactive cards -->
    <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 12px; margin-bottom: 16px;">
        <div class="stat-card is-clickable active-stat-filter" data-filter="all" style="cursor:pointer; border: 1px solid var(--admin-border); padding: 12px; border-radius: 10px; background: var(--admin-card-bg);">
            <small style="color: var(--admin-text-muted); font-weight: 600;">Total CSV</small>
            <h3 style="font-size: 20px; font-weight:800; margin: 4px 0 0;"><?= (int)($importData['stats']['total_csv_rows'] ?? 0) ?></h3>
        </div>
        <div class="stat-card is-clickable" data-filter="valid" style="cursor:pointer; border: 1px solid var(--admin-border); padding: 12px; border-radius: 10px; background: var(--admin-card-bg);">
            <small style="color: var(--admin-success); font-weight:700;">Valid</small>
            <h3 style="font-size: 20px; font-weight:800; color: var(--admin-success); margin: 4px 0 0;"><?= (int)($importData['stats']['valid'] ?? 0) ?></h3>
        </div>
        <div class="stat-card is-clickable" data-filter="invalid" style="cursor:pointer; border: 1px solid var(--admin-border); padding: 12px; border-radius: 10px; background: var(--admin-card-bg);">
            <small style="color: var(--admin-danger); font-weight:700;">Invalid</small>
            <h3 style="font-size: 20px; font-weight:800; color: var(--admin-danger); margin: 4px 0 0;"><?= (int)($importData['stats']['invalid'] ?? 0) ?></h3>
        </div>
        <div class="stat-card is-clickable" data-filter="skipped" style="cursor:pointer; border: 1px solid var(--admin-border); padding: 12px; border-radius: 10px; background: var(--admin-card-bg);">
            <small style="color: var(--admin-text-muted); font-weight:700;">Dilewati</small>
            <h3 style="font-size: 20px; font-weight:800; color: var(--admin-text-muted); margin: 4px 0 0;"><?= (int)($importData['stats']['skipped_not_completed'] ?? 0) ?></h3>
        </div>
        <div class="stat-card is-clickable" data-filter="missing_img" style="cursor:pointer; border: 1px solid var(--admin-border); padding: 12px; border-radius: 10px; background: var(--admin-card-bg);">
            <small style="color: var(--admin-warning); font-weight:700;">Gambar Hilang</small>
            <h3 style="font-size: 20px; font-weight:800; color: var(--admin-warning); margin: 4px 0 0;"><?= (int)($importData['stats']['images_missing'] ?? 0) ?></h3>
        </div>
    </div>

    <?php $categories = loadActiveCategoryMap($pdo); ?>

    <form action="" method="POST" enctype="multipart/form-data" class="admin-form js-loading-form" data-loading-title="Mengimport produk..." style="margin: 0;">
        <input type="hidden" name="csrf_token" value="<?= sanitizeOutput($csrfToken) ?>">
        <input type="hidden" name="action" value="confirm">

        <div class="master-detail-container">
            <!-- Left Panel: Product List -->
            <div class="master-list-panel">
                <div class="panel-search-box" style="display: flex; gap: 8px; align-items: center; padding: 12px; border-bottom: 1px solid var(--admin-border);">
                    <input type="text" id="productSearch" placeholder="Cari berdasarkan nama atau SKU..." oninput="filterProductList()" style="flex: 1; padding: 8px 12px; border: 1px solid var(--admin-border); border-radius: var(--admin-radius-sm); font-size: 13px; background: var(--admin-bg); color: var(--admin-text);">
                    <button type="button" class="btn btn-secondary" id="btnToggleSelectAll" onclick="toggleSelectAllImports()" style="padding: 8px 12px; font-size: 12px; white-space: nowrap; border: 1px solid var(--admin-border); border-radius: var(--admin-radius-sm); font-weight:600; cursor:pointer;">Kosongkan</button>
                </div>
                <div class="master-list-items" id="masterProductList">
                    <?php foreach (($importData['rows'] ?? []) as $index => $row): ?>
                        <?php
                        $mapped = $row['mapped'] ?? [];
                        $isSkipped = !empty($row['skipped']);
                        $statusLabel = $isSkipped ? 'skipped' : (!empty($row['valid']) ? 'valid' : 'invalid');
                        $hasMissingImg = !$isSkipped && empty($row['main_image_found']);
                        $mainSrc = importPreviewImageSrc((string)($row['main_image_source'] ?? ''));
                        
                        $itemName = $isSkipped ? '(Produk Dilewati)' : ($mapped['name'] ?? 'Baris #' . ($index + 1));
                        $itemSku = $mapped['sku'] ?? 'N/A';
                        ?>
                        <div class="product-list-card" 
                             id="list-item-<?= $index ?>" 
                             data-index="<?= $index ?>" 
                             data-status="<?= $statusLabel ?>"
                             data-missing-image="<?= $hasMissingImg ? '1' : '0' ?>"
                             data-name="<?= sanitizeOutput(strtolower($itemName)) ?>"
                             data-sku="<?= sanitizeOutput(strtolower($itemSku)) ?>"
                             onclick="selectProduct(<?= $index ?>)">
                            
                            <input type="checkbox" name="import_selected[<?= $index ?>]" value="1" <?= $row['valid'] && !$isSkipped ? 'checked' : '' ?> class="product-import-checkbox" onclick="event.stopPropagation();" style="margin-right: 4px; width: 16px; height: 16px; accent-color: var(--admin-primary); cursor: pointer; flex-shrink: 0;">
                            
                            <?php if ($mainSrc): ?>
                                <img class="product-list-card-thumb" src="<?= sanitizeOutput($mainSrc) ?>" id="list-thumb-<?= $index ?>">
                            <?php else: ?>
                                <div class="product-list-card-thumb" style="display:flex; align-items:center; justify-content:center; color: var(--admin-text-muted);" id="list-thumb-placeholder-<?= $index ?>">
                                    <span class="material-symbols-outlined" style="font-size:20px;">image_not_supported</span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="product-list-card-info">
                                <div class="product-list-card-title" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 180px; font-size:13px; font-weight:600; color:var(--admin-text);"><?= sanitizeOutput($itemName) ?></div>
                                <div class="product-list-card-sku">SKU: <?= sanitizeOutput($itemSku) ?></div>
                                <span class="product-list-card-badge <?= $statusLabel ?>">
                                    <?= $statusLabel === 'skipped' ? 'Dilewati' : ($statusLabel === 'valid' ? 'Valid' : 'Invalid') ?>
                                </span>
                                <?php if ($hasMissingImg): ?>
                                    <span class="product-list-card-badge invalid" style="background:#fff7ed; color:#c2410c;">Gbr Hilang</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Right Panel: Active Product Editor -->
            <div class="detail-edit-panel" id="detailEditPanel">
                <div class="detail-panel-placeholder" id="detailPlaceholder">
                    <span class="material-symbols-outlined" style="font-size: 64px; color: var(--admin-text-muted); margin-bottom:12px;">inventory</span>
                    <h3>Pilih Produk</h3>
                    <p>Silakan pilih produk di panel kiri untuk melihat dan menyunting detail sebelum impor.</p>
                </div>

                <div class="detail-panel-form-container" style="display: none;" id="detailFormArea">
                    <?php foreach (($importData['rows'] ?? []) as $index => $row): ?>
                        <?php if (!empty($row['skipped'])): ?>
                            <!-- Skipped Product Details -->
                            <div class="product-editor-form" id="editor-row-<?= $index ?>">
                                <div class="detail-panel-placeholder" style="padding: 20px 0;">
                                    <span class="material-symbols-outlined" style="font-size: 48px; color: var(--admin-text-muted); margin-bottom:12px;">block</span>
                                    <h3>Produk Dilewati</h3>
                                    <p>Baris ini dilewati karena status di file CSV bukan "completed". Tidak ada data untuk disunting.</p>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php
                            $mapped = $row['mapped'] ?? [];
                            $mainSrc = importPreviewImageSrc((string)($row['main_image_source'] ?? ''));
                            ?>
                            <div class="product-editor-form" id="editor-row-<?= $index ?>">
                                <!-- Error Display Box -->
                                <div class="detail-errors-box" id="error-box-<?= $index ?>" style="<?= empty($row['errors']) ? 'display:none;' : '' ?>">
                                    <h4 style="color:#991b1b; font-weight:700; margin-bottom:6px; font-size:13px;">Kesalahan Validasi:</h4>
                                    <ul class="detail-errors-list" id="error-list-<?= $index ?>">
                                        <?php foreach (($row['errors'] ?? []) as $error): ?>
                                            <li><?= sanitizeOutput($error) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>

                                <!-- Product Edit Grid -->
                                <div class="editor-grid">
                                    <div class="editor-form-group editor-grid-full">
                                        <label>Nama Produk <span style="color:var(--admin-danger);">*</span></label>
                                        <input type="text" name="rows[<?= $index ?>][name]" value="<?= sanitizeOutput($mapped['name'] ?? '') ?>" oninput="updateListCardName(<?= $index ?>, this.value)">
                                    </div>
                                    <div class="editor-form-group">
                                        <label>SKU (Kode Item)</label>
                                        <input type="text" name="rows[<?= $index ?>][sku]" value="<?= sanitizeOutput($mapped['sku'] ?? '') ?>" oninput="updateListCardSku(<?= $index ?>, this.value)">
                                    </div>
                                    <div class="editor-form-group">
                                        <label>Kategori <span style="color:var(--admin-danger);">*</span></label>
                                        <select name="rows[<?= $index ?>][category_id]">
                                            <option value="">-- Pilih Kategori --</option>
                                            <?php foreach ($categories as $catId => $catName): ?>
                                                <option value="<?= $catId ?>" <?= ($mapped['category_id'] ?? '') == $catId ? 'selected' : '' ?>><?= sanitizeOutput($catName) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="editor-form-group">
                                        <label>Harga Beli</label>
                                        <input type="number" name="rows[<?= $index ?>][purchase_price]" value="<?= (int)($mapped['purchase_price'] ?? 0) ?>">
                                    </div>
                                    <div class="editor-form-group">
                                        <label>Harga Jual <span style="color:var(--admin-danger);">*</span></label>
                                        <input type="number" name="rows[<?= $index ?>][selling_price]" value="<?= (int)($mapped['selling_price'] ?? 0) ?>">
                                    </div>
                                    <div class="editor-form-group">
                                        <label>Harga Promo (Opsional)</label>
                                        <input type="number" name="rows[<?= $index ?>][promo_price]" value="<?= sanitizeOutput((string)($mapped['promo_price'] ?? '')) ?>">
                                    </div>
                                    <div class="editor-form-group">
                                        <label>Stok</label>
                                        <input type="number" name="rows[<?= $index ?>][stock]" value="<?= (int)($mapped['stock'] ?? 0) ?>">
                                    </div>
                                    <div class="editor-form-group">
                                        <label>Brand</label>
                                        <input type="text" name="rows[<?= $index ?>][brand]" value="<?= sanitizeOutput($mapped['brand'] ?? '') ?>">
                                    </div>
                                    <div class="editor-form-group">
                                        <label>Model</label>
                                        <input type="text" name="rows[<?= $index ?>][model]" value="<?= sanitizeOutput($mapped['model'] ?? '') ?>">
                                    </div>
                                    <div class="editor-form-group editor-grid-full">
                                        <label>Deskripsi Produk</label>
                                        <textarea name="rows[<?= $index ?>][description]"><?= sanitizeOutput($mapped['description'] ?? '') ?></textarea>
                                    </div>
                                    <div class="editor-form-group editor-grid-full">
                                        <label>Spesifikasi Produk</label>
                                        <textarea name="rows[<?= $index ?>][specification]"><?= sanitizeOutput($mapped['specification'] ?? '') ?></textarea>
                                    </div>
                                </div>

                                <!-- Image Management Section -->
                                <h4 style="font-size: 13px; margin: 16px 0 8px; border-top: 1px solid var(--admin-border); padding-top: 12px;">Kelola Gambar Utama & Tambahan</h4>
                                <div class="editor-images-area">
                                    <!-- Main Image -->
                                    <div class="editor-image-box">
                                        <div class="editor-image-preview-wrapper <?= empty($row['main_image_found']) ? 'missing' : '' ?>" id="main-preview-wrap-<?= $index ?>">
                                            <?php if ($mainSrc): ?>
                                                <img src="<?= sanitizeOutput($mainSrc) ?>" id="main-preview-img-<?= $index ?>">
                                                <button type="button" class="btn-delete-img" onclick="deleteMainImage(<?= $index ?>)" title="Hapus Gambar Utama">&times;</button>
                                            <?php else: ?>
                                                <span class="material-symbols-outlined" style="font-size:36px; color: var(--admin-danger);" id="main-preview-icon-<?= $index ?>">image_not_supported</span>
                                            <?php endif; ?>
                                            <input type="file" id="file-input-<?= $index ?>" name="row_images[<?= $index ?>]" accept="image/jpeg,image/png,image/webp" onchange="previewManualUpload(<?= $index ?>, this)">
                                        </div>
                                        <input type="hidden" name="rows[<?= $index ?>][delete_main_image]" value="0" id="delete-main-input-<?= $index ?>">
                                        <div class="editor-image-label" id="main-image-name-<?= $index ?>">
                                            <?= sanitizeOutput($row['main_image'] ?: 'Belum ada gambar') ?>
                                        </div>
                                        <small style="font-size:10px; color:<?= empty($row['main_image_found']) ? 'var(--admin-danger)' : 'var(--admin-success)' ?>; font-weight:700;" id="main-image-status-<?= $index ?>">
                                            (<?= empty($row['main_image_found']) ? 'Gambar Hilang' : 'Cocok' ?>)
                                        </small>
                                    </div>

                                    <!-- Additional Images -->
                                    <?php if (!empty($row['additional_images'])): ?>
                                        <?php foreach ($row['additional_images'] as $addIndex => $imgName): ?>
                                            <?php 
                                            $found = !empty($row['additional_images_found'][$addIndex]); 
                                            $addSrc = importPreviewImageSrc((string)($row['additional_image_sources'][$addIndex] ?? '')); 
                                            ?>
                                            <div class="editor-image-box" id="additional-box-<?= $index ?>-<?= $addIndex ?>">
                                                <div class="editor-image-preview-wrapper <?= !$found ? 'missing' : '' ?>">
                                                    <?php if ($addSrc): ?>
                                                        <img src="<?= sanitizeOutput($addSrc) ?>">
                                                        <button type="button" class="btn-delete-img" onclick="deleteAdditionalImage(<?= $index ?>, <?= $addIndex ?>)" title="Hapus Gambar Tambahan">&times;</button>
                                                    <?php else: ?>
                                                        <span class="material-symbols-outlined" style="font-size:24px; color: var(--admin-text-muted);">image</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="editor-image-label"><?= sanitizeOutput($imgName) ?></div>
                                                <small style="font-size:10px; color:<?= !$found ? 'var(--admin-danger)' : 'var(--admin-success)' ?>; font-weight:700;">
                                                    (<?= !$found ? 'Hilang' : 'Cocok' ?>)
                                                </small>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Footer actions panel -->
        <div class="form-actions" style="margin-top: 20px; display: flex; justify-content: flex-end; gap: 12px; background: var(--admin-card-bg); padding: 16px; border: 1px solid var(--admin-border); border-radius: var(--admin-radius);">
            <a href="product-import?reset=1" class="btn btn-secondary" style="border: 1px solid var(--admin-border);">Batal & Ulangi</a>
            <button type="submit" class="btn btn-primary">Konfirmasi & Import Semua</button>
        </div>
    </form>
</div>
<?php endif; ?>

<script>
(() => {
    // Image source selectors
    const imageCards = document.querySelectorAll('.image-source-card');
    imageCards.forEach(card => {
        card.addEventListener('click', () => {
            imageCards.forEach(c => c.classList.remove('is-active'));
            card.classList.add('is-active');
            
            const radio = card.querySelector('input[type="radio"]');
            if (radio) radio.checked = true;
            
            // Hide all sections, show active
            document.querySelectorAll('.image-opt-section').forEach(sec => sec.style.display = 'none');
            const target = card.dataset.target;
            const activeSec = document.getElementById(target);
            if (activeSec) activeSec.style.display = 'block';
        });
    });

    // Drag and drop zone handling
    const dragZone = document.getElementById('csvDragZone');
    const fileInput = document.getElementById('csv_file');
    if (dragZone && fileInput) {
        dragZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dragZone.classList.add('dragover');
        });
        dragZone.addEventListener('dragleave', () => {
            dragZone.classList.remove('dragover');
        });
        dragZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dragZone.classList.remove('dragover');
            if (e.dataTransfer.files.length) {
                fileInput.files = e.dataTransfer.files;
                const label = dragZone.querySelector('p');
                if (label) label.textContent = 'Selected: ' + e.dataTransfer.files[0].name;
            }
        });
        fileInput.addEventListener('change', () => {
            if (fileInput.files.length) {
                const label = dragZone.querySelector('p');
                if (label) label.textContent = 'Selected: ' + fileInput.files[0].name;
            }
        });
    }

    // Loading indicator
    const overlay = document.getElementById('importLoading');
    const title = document.getElementById('importLoadingTitle');
    const text = document.getElementById('importLoadingText');
    const bar = document.getElementById('importProgressBar');
    const showLoading = (message, percent = 15) => {
        title.textContent = message;
        text.textContent = 'Mohon tunggu, jangan tutup halaman.';
        if (bar) bar.style.width = percent + '%';
        overlay.classList.add('is-active');
    };

    document.querySelectorAll('.js-loading-form').forEach(form => {
        form.addEventListener('submit', () => {
            showLoading(form.dataset.loadingTitle || 'Memproses...', form.dataset.upload === '1' ? 35 : 70);
            const button = form.querySelector('[type="submit"]');
            if (button) {
                button.disabled = true;
                button.textContent = form.dataset.upload === '1' ? 'Mengupload...' : 'Memproses...';
            }
        });
    });
})();

// Active Row Selection
let activeIndex = null;
function selectProduct(index) {
    // Highlight active card
    document.querySelectorAll('.product-list-card').forEach(card => card.classList.remove('is-active'));
    const activeCard = document.getElementById('list-item-' + index);
    if (activeCard) activeCard.classList.add('is-active');
    
    // Hide placeholder and show form area
    const placeholder = document.getElementById('detailPlaceholder');
    if (placeholder) placeholder.style.display = 'none';
    
    const formArea = document.getElementById('detailFormArea');
    if (formArea) formArea.style.display = 'block';
    
    // Show active editor, hide others
    document.querySelectorAll('.product-editor-form').forEach(form => form.classList.remove('is-active'));
    const activeForm = document.getElementById('editor-row-' + index);
    if (activeForm) activeForm.classList.add('is-active');
    
    activeIndex = index;
}

// Dynamic Live Cards
function updateListCardName(index, value) {
    const card = document.getElementById('list-item-' + index);
    if (card) {
        card.dataset.name = value.toLowerCase();
        const titleElem = card.querySelector('.product-list-card-title');
        if (titleElem) titleElem.textContent = value || '(Nama Kosong)';
    }
}

// Dynamic Live SKUs
function updateListCardSku(index, value) {
    const card = document.getElementById('list-item-' + index);
    if (card) {
        card.dataset.sku = value.toLowerCase();
        const skuElem = card.querySelector('.product-list-card-sku');
        if (skuElem) skuElem.textContent = 'SKU: ' + (value || 'N/A');
    }
}

// Client-side image replacement preview
function previewManualUpload(index, input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const reader = new FileReader();
        
        reader.onload = function(e) {
            // Reset delete main image hidden input
            const delInput = document.getElementById('delete-main-input-' + index);
            if (delInput) {
                delInput.value = '0';
            }

            // Update Main Detail preview
            const wrap = document.getElementById('main-preview-wrap-' + index);
            if (wrap) {
                wrap.classList.remove('missing');
                wrap.innerHTML = `<img src="${e.target.result}" id="main-preview-img-${index}"><button type="button" class="btn-delete-img" onclick="deleteMainImage(${index})" title="Hapus Gambar Utama">&times;</button><input type="file" id="file-input-${index}" name="row_images[${index}]" accept="image/jpeg,image/png,image/webp" onchange="previewManualUpload(${index}, this)">`;
            }
            
            // Update Left card thumbnail
            const cardThumb = document.getElementById('list-thumb-' + index);
            if (cardThumb) {
                cardThumb.src = e.target.result;
            } else {
                const placeholder = document.getElementById('list-thumb-placeholder-' + index);
                if (placeholder) {
                    const img = document.createElement('img');
                    img.className = 'product-list-card-thumb';
                    img.id = 'list-thumb-' + index;
                    img.src = e.target.result;
                    placeholder.parentNode.replaceChild(img, placeholder);
                }
            }
            
            // Update Label info
            const nameLabel = document.getElementById('main-image-name-' + index);
            if (nameLabel) nameLabel.textContent = file.name;
            
            const statusLabel = document.getElementById('main-image-status-' + index);
            if (statusLabel) {
                statusLabel.textContent = '(Cocok/Unggahan Baru)';
                statusLabel.style.color = 'var(--admin-success)';
            }
            
            // Update card state for filters
            const card = document.getElementById('list-item-' + index);
            if (card) {
                card.dataset.missingImage = '0';
                
                // Auto check the checkbox
                const checkbox = card.querySelector('.product-import-checkbox');
                if (checkbox) {
                    checkbox.checked = true;
                }
            }
        };
        
        reader.readAsDataURL(file);
    }
}

function deleteMainImage(index) {
    // 1. Set hidden delete input
    const input = document.getElementById('delete-main-input-' + index);
    if (input) {
        input.value = "1";
    }
    
    // 2. Clear main image uploaded file input (if any)
    const fileInput = document.getElementById('file-input-' + index);
    if (fileInput) {
        fileInput.value = "";
    }

    // 3. Update UI preview to show placeholder
    const wrap = document.getElementById('main-preview-wrap-' + index);
    if (wrap) {
        wrap.classList.add('missing');
        wrap.innerHTML = `
            <span class="material-symbols-outlined" style="font-size:36px; color: var(--admin-danger);" id="main-preview-icon-${index}">image_not_supported</span>
            <input type="file" id="file-input-${index}" name="row_images[${index}]" accept="image/jpeg,image/png,image/webp" onchange="previewManualUpload(${index}, this)">
        `;
    }
    
    // 4. Update Left card thumbnail
    const cardThumb = document.getElementById('list-thumb-' + index);
    if (cardThumb) {
        // Replace with placeholder icon
        const placeholder = document.createElement('div');
        placeholder.className = 'product-list-card-thumb';
        placeholder.id = 'list-thumb-placeholder-' + index;
        placeholder.style.display = 'flex';
        placeholder.style.alignItems = 'center';
        placeholder.style.justifyContent = 'center';
        placeholder.style.color = 'var(--admin-text-muted)';
        placeholder.innerHTML = '<span class="material-symbols-outlined" style="font-size:20px;">image_not_supported</span>';
        cardThumb.parentNode.replaceChild(placeholder, cardThumb);
    }
    
    // 5. Update Label info
    const nameLabel = document.getElementById('main-image-name-' + index);
    if (nameLabel) nameLabel.textContent = 'Belum ada gambar';
    
    const statusLabel = document.getElementById('main-image-status-' + index);
    if (statusLabel) {
        statusLabel.textContent = '(Gambar Hilang)';
        statusLabel.style.color = 'var(--admin-danger)';
    }
    
    // 6. Update card state for filters
    const card = document.getElementById('list-item-' + index);
    if (card) {
        card.dataset.missingImage = '1';
        card.dataset.status = 'invalid';
        
        // Auto uncheck the checkbox
        const checkbox = card.querySelector('.product-import-checkbox');
        if (checkbox) {
            checkbox.checked = false;
        }

        const badge = card.querySelector('.product-list-card-badge');
        if (badge) {
            badge.className = 'product-list-card-badge invalid';
            badge.textContent = 'Invalid';
        }
    }
}

function deleteAdditionalImage(index, addIndex) {
    // 1. Add hidden input to the corresponding form row so the backend knows to delete it
    const form = document.getElementById('editor-row-' + index);
    if (form) {
        // Check if input already exists to avoid duplicates
        const existingInput = form.querySelector(`input[name="rows[${index}][delete_additional][]"][value="${addIndex}"]`);
        if (!existingInput) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = `rows[${index}][delete_additional][]`;
            input.value = addIndex;
            form.appendChild(input);
        }
    }
    
    // 2. Hide/remove the box from DOM
    const box = document.getElementById(`additional-box-${index}-${addIndex}`);
    if (box) {
        box.style.display = 'none';
    }
}

// Search and filters
let currentFilter = 'all';

// Wire up filter cards click events
document.querySelectorAll('.stat-card.is-clickable').forEach(card => {
    card.addEventListener('click', () => {
        document.querySelectorAll('.stat-card.is-clickable').forEach(c => c.classList.remove('active-stat-filter'));
        card.classList.add('active-stat-filter');
        currentFilter = card.dataset.filter;
        filterProductList();
    });
});

function filterProductList() {
    const query = document.getElementById('productSearch').value.toLowerCase().trim();
    const cards = document.querySelectorAll('.product-list-card');
    
    cards.forEach(card => {
        const name = card.dataset.name || '';
        const sku = card.dataset.sku || '';
        const status = card.dataset.status || '';
        const isMissingImg = card.dataset.missingImage === '1';
        
        let matchesSearch = name.includes(query) || sku.includes(query);
        let matchesFilter = false;
        
        if (currentFilter === 'all') {
            matchesFilter = true;
        } else if (currentFilter === 'valid') {
            matchesFilter = (status === 'valid');
        } else if (currentFilter === 'invalid') {
            matchesFilter = (status === 'invalid');
        } else if (currentFilter === 'skipped') {
            matchesFilter = (status === 'skipped');
        } else if (currentFilter === 'missing_img') {
            matchesFilter = isMissingImg;
        }
        
        if (matchesSearch && matchesFilter) {
            card.style.display = 'flex';
        } else {
            card.style.display = 'none';
        }
    });
}

let allSelected = true;
function toggleSelectAllImports() {
    const checkboxes = document.querySelectorAll('.product-import-checkbox');
    allSelected = !allSelected;
    checkboxes.forEach(cb => {
        cb.checked = allSelected;
    });
    
    // Update button text
    const btn = document.getElementById('btnToggleSelectAll');
    if (btn) {
        btn.textContent = allSelected ? 'Kosongkan' : 'Pilih Semua';
    }
}
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>