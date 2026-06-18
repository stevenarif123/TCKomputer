<?php
/**
 * Admin - Add Product
 * Form to create a new product with all fields, image upload,
 * server-side validation, CSRF protection, and flash messages.
 */

$pageTitle = "Tambah Produk";

// Process form before including header (redirect needs to happen before output)
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/admin-auth.php';

requireAdmin();

$pdo = getDBConnection();
$errors = [];
$formData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($submittedToken)) {
        $errors[] = 'Token keamanan tidak valid. Silakan coba lagi.';
    }

    // Collect form data
    $name = trim($_POST['name'] ?? '');
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $sku = trim($_POST['sku'] ?? '');
    $brand = trim($_POST['brand'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $specification = trim($_POST['specification'] ?? '');
    $purchasePrice = (int) ($_POST['purchase_price'] ?? 0);
    $sellingPrice = (int) ($_POST['selling_price'] ?? 0);
    $stock = (int) ($_POST['stock'] ?? 0);
    $status = $_POST['status'] ?? 'ready';
    $conditionType = $_POST['condition_type'] ?? 'new';
    $warrantyNote = trim($_POST['warranty_note'] ?? '');
    $promoPrice = isset($_POST['promo_price']) && $_POST['promo_price'] !== '' ? (int) $_POST['promo_price'] : null;
    $promoActive = isset($_POST['promo_active']) ? 1 : 0;
    $promoStock = isset($_POST['promo_stock']) && $_POST['promo_stock'] !== '' ? (int) $_POST['promo_stock'] : 0;
    $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    // Preserve form data for re-display on error
    $formData = [
        'name' => $name,
        'category_id' => $categoryId,
        'sku' => $sku,
        'brand' => $brand,
        'model' => $model,
        'description' => $description,
        'specification' => $specification,
        'purchase_price' => $purchasePrice,
        'selling_price' => $sellingPrice,
        'promo_price' => $promoPrice,
        'promo_active' => $promoActive,
        'promo_stock' => $promoStock,
        'stock' => $stock,
        'status' => $status,
        'condition_type' => $conditionType,
        'warranty_note' => $warrantyNote,
        'is_featured' => $isFeatured,
        'is_active' => $isActive,
    ];

    // Validation
    if (empty($name)) {
        $errors[] = 'Nama produk wajib diisi';
    } elseif (strlen($name) > 255) {
        $errors[] = 'Nama produk maksimal 255 karakter';
    }

    if ($categoryId <= 0) {
        $errors[] = 'Kategori wajib dipilih';
    } else {
        // Verify category exists
        $stmt = $pdo->prepare("SELECT id FROM categories WHERE id = ? AND is_active = 1");
        $stmt->execute([$categoryId]);
        if (!$stmt->fetch()) {
            $errors[] = 'Kategori tidak valid';
        }
    }

    if ($sellingPrice <= 0) {
        $errors[] = 'Harga jual harus lebih dari 0';
    }

    if ($promoActive) {
        if ($promoPrice === null || $promoPrice <= 0) {
            $errors[] = 'Harga promo wajib diisi dan harus lebih dari 0 jika promo aktif';
        } elseif ($promoPrice >= $sellingPrice) {
            $errors[] = 'Harga promo harus lebih kecil dari harga jual regular';
        }
        if ($promoStock <= 0) {
            $errors[] = 'Stok promo wajib diisi dan harus lebih dari 0 jika promo aktif';
        } elseif ($promoStock > $stock) {
            $errors[] = 'Stok promo tidak boleh melebihi stok fisik produk (' . $stock . ')';
        }
    }

    if (!in_array($status, ['ready', 'po', 'habis'])) {
        $errors[] = 'Status tidak valid';
    }

    if (!in_array($conditionType, ['new', 'used'])) {
        $errors[] = 'Kondisi tidak valid';
    }

    // Handle image upload
    $imageName = null;
    if (!empty($_FILES['image']['name'])) {
        $uploadError = '';
        $imageName = uploadImage($_FILES['image'], __DIR__ . '/../uploads/products/', $uploadError);
        if ($imageName === false) {
            $errors[] = 'Upload gambar gagal: ' . ($uploadError ?: 'format tidak didukung atau ukuran melebihi 2MB');
            $imageName = null;
        }
    }

    // If no errors, insert product
    if (empty($errors)) {
        // Auto-generate slug
        $slug = generateSlug($name);

        // Check slug uniqueness
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE slug = ?");
        $stmt->execute([$slug]);
        if ($stmt->fetchColumn() > 0) {
            $slug .= '-' . time();
        }

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO products (category_id, name, slug, sku, brand, model, 
                 description, specification, purchase_price, selling_price, promo_price, promo_active, promo_stock, promo_stock_initial, stock, 
                 status, condition_type, warranty_note, image, is_featured, is_active, 
                 created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
            );
            $stmt->execute([
                $categoryId, $name, $slug, $sku, $brand, $model,
                $description, $specification, $purchasePrice, $sellingPrice, $promoPrice, $promoActive, $promoStock, $promoStock,
                $stock, $status, $conditionType, $warrantyNote,
                $imageName, $isFeatured, $isActive
            ]);

            $productId = $pdo->lastInsertId();

            // Handle additional images upload
            if (isset($_FILES['additional_images']) && is_array($_FILES['additional_images']['name'])) {
                $additionalUploadedCount = 0;
                $filesCount = count($_FILES['additional_images']['name']);
                
                for ($i = 0; $i < $filesCount; $i++) {
                    if ($_FILES['additional_images']['error'][$i] === UPLOAD_ERR_NO_FILE) {
                        continue;
                    }
                    
                    // Stop processing if we exceed 5 additional images
                    if ($additionalUploadedCount >= 5) {
                        break;
                    }
                    
                    $fileArray = [
                        'name'     => $_FILES['additional_images']['name'][$i],
                        'type'     => $_FILES['additional_images']['type'][$i],
                        'tmp_name' => $_FILES['additional_images']['tmp_name'][$i],
                        'error'    => $_FILES['additional_images']['error'][$i],
                        'size'     => $_FILES['additional_images']['size'][$i]
                    ];
                    
                    $uploadError = '';
                    $uploadedFilename = uploadImage($fileArray, __DIR__ . '/../uploads/products/', $uploadError);
                    
                    if ($uploadedFilename !== false) {
                        $stmtImg = $pdo->prepare("INSERT INTO product_images (product_id, image_path, sort_order) VALUES (?, ?, ?)");
                        $stmtImg->execute([$productId, $uploadedFilename, $additionalUploadedCount]);
                        $additionalUploadedCount++;
                    } else {
                        // Log warning but don't fail product creation
                        error_log("Failed to upload additional image: " . $uploadError);
                    }
                }
            }

            redirect('products', 'Produk berhasil ditambahkan');
        } catch (PDOException $e) {
            error_log('Error adding product: ' . $e->getMessage());
            $errors[] = 'Gagal menambahkan produk ke database: ' . $e->getMessage();
        }
    }
}

// Get categories for dropdown
$categories = $pdo->query(
    "SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name"
)->fetchAll();

// Include header after processing (generates new CSRF token)
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

<div class="admin-page-header">
    <h2>Tambah Produk Baru</h2>
    <a href="products" class="btn btn-secondary">&laquo; Kembali</a>
</div>

<div class="admin-form-container">
    <form action="" method="POST" enctype="multipart/form-data" class="admin-form">
        <input type="hidden" name="csrf_token" value="<?= sanitizeOutput($csrfToken) ?>">

        <h3>Informasi Dasar</h3>

        <div class="form-group">
            <label for="name">Nama Produk <span class="required">*</span></label>
            <input type="text" id="name" name="name" maxlength="255" required
                   value="<?= sanitizeOutput($formData['name'] ?? '') ?>"
                   placeholder="Masukkan nama produk">
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="category_id">Kategori <span class="required">*</span></label>
                <select id="category_id" name="category_id" required>
                    <option value="">-- Pilih Kategori --</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int) $category['id'] ?>"
                            <?= (($formData['category_id'] ?? 0) == $category['id']) ? 'selected' : '' ?>>
                            <?= sanitizeOutput($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="sku">SKU</label>
                <input type="text" id="sku" name="sku" maxlength="100"
                       value="<?= sanitizeOutput($formData['sku'] ?? '') ?>"
                       placeholder="Kode produk (opsional)">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="brand">Brand</label>
                <input type="text" id="brand" name="brand" maxlength="100"
                       value="<?= sanitizeOutput($formData['brand'] ?? '') ?>"
                       placeholder="Merek produk">
            </div>
            <div class="form-group">
                <label for="model">Model</label>
                <input type="text" id="model" name="model" maxlength="100"
                       value="<?= sanitizeOutput($formData['model'] ?? '') ?>"
                       placeholder="Model produk">
            </div>
        </div>

        <div class="form-group">
            <label for="description">Deskripsi</label>
            <textarea id="description" name="description" rows="4"
                      placeholder="Deskripsi produk"><?= sanitizeOutput($formData['description'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label for="specification">Spesifikasi</label>
            <textarea id="specification" name="specification" rows="4"
                      placeholder="Spesifikasi teknis produk"><?= sanitizeOutput($formData['specification'] ?? '') ?></textarea>
        </div>

        <h3>Harga & Stok</h3>

        <div class="form-row">
            <div class="form-group">
                <label for="purchase_price">Harga Beli (Rp)</label>
                <input type="number" id="purchase_price" name="purchase_price" min="0"
                       value="<?= (int) ($formData['purchase_price'] ?? 0) ?>"
                       placeholder="0">
            </div>
            <div class="form-group">
                <label for="selling_price">Harga Jual (Rp) <span class="required">*</span></label>
                <input type="number" id="selling_price" name="selling_price" min="1" required
                       value="<?= (int) ($formData['selling_price'] ?? 0) ?>"
                       placeholder="0">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="promo_price">Harga Promo / Flash Sale (Rp)</label>
                <input type="number" id="promo_price" name="promo_price" min="0"
                       value="<?= isset($formData['promo_price']) && $formData['promo_price'] !== null ? (int)$formData['promo_price'] : '' ?>"
                       placeholder="Masukkan harga diskon jika ada">
            </div>
            <div class="form-group">
                <label for="promo_stock">Stok Promo (Flash Sale)</label>
                <input type="number" id="promo_stock" name="promo_stock" min="0"
                       value="<?= (int) ($formData['promo_stock'] ?? 0) ?>"
                       placeholder="Kuota barang promo">
                <small class="form-help">Tidak boleh melebihi stok fisik.</small>
            </div>
        </div>

        <div class="form-group form-checkbox">
            <label>
                <input type="checkbox" name="promo_active" value="1"
                    <?= (($formData['promo_active'] ?? 0) == 1) ? 'checked' : '' ?>>
                Promo Aktif (Masukkan ke Flash Sale)
            </label>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="stock">Stok</label>
                <input type="number" id="stock" name="stock" min="0"
                       value="<?= (int) ($formData['stock'] ?? 0) ?>"
                       placeholder="0">
            </div>
            <div class="form-group">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="ready" <?= (($formData['status'] ?? 'ready') === 'ready') ? 'selected' : '' ?>>Ready</option>
                    <option value="po" <?= (($formData['status'] ?? '') === 'po') ? 'selected' : '' ?>>Pre-Order (PO)</option>
                    <option value="habis" <?= (($formData['status'] ?? '') === 'habis') ? 'selected' : '' ?>>Habis</option>
                </select>
            </div>
        </div>

        <h3>Detail Tambahan</h3>

        <div class="form-row">
            <div class="form-group">
                <label for="condition_type">Kondisi</label>
                <select id="condition_type" name="condition_type">
                    <option value="new" <?= (($formData['condition_type'] ?? 'new') === 'new') ? 'selected' : '' ?>>Baru</option>
                    <option value="used" <?= (($formData['condition_type'] ?? '') === 'used') ? 'selected' : '' ?>>Bekas</option>
                </select>
            </div>
            <div class="form-group">
                <label for="warranty_note">Catatan Garansi</label>
                <input type="text" id="warranty_note" name="warranty_note" maxlength="255"
                       value="<?= sanitizeOutput($formData['warranty_note'] ?? '') ?>"
                       placeholder="Contoh: Garansi 1 tahun">
            </div>
        </div>

        <div class="form-group">
            <label for="image">Gambar Utama Produk</label>
            <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp"
                   data-crop="true" data-aspect-ratio="1" data-width="800" data-height="800">
            <small class="form-help">Format: JPG, PNG, WebP. Maksimal 2MB. Rekomendasi ukuran: 800 x 800 piksel (rasio 1:1).</small>
        </div>

        <div class="form-group">
            <label for="additional_images">Gambar Tambahan Produk (Bisa pilih banyak sekaligus, maks 5 gambar)</label>
            <input type="file" id="additional_images" name="additional_images[]" accept="image/jpeg,image/png,image/webp" multiple>
            <small class="form-help">Format: JPG, PNG, WebP. Maksimal 2MB per gambar. Foto ini akan menjadi galeri pendukung detail produk.</small>
        </div>

        <div class="form-row">
            <div class="form-group form-checkbox">
                <label>
                    <input type="checkbox" name="is_featured" value="1"
                        <?= (($formData['is_featured'] ?? 0) == 1) ? 'checked' : '' ?>>
                    Produk Unggulan (Featured)
                </label>
            </div>
            <div class="form-group form-checkbox">
                <label>
                    <input type="checkbox" name="is_active" value="1"
                        <?= (isset($formData['is_active'])) ? (($formData['is_active'] == 1) ? 'checked' : '') : 'checked' ?>>
                    Aktif (tampilkan di toko)
                </label>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Simpan Produk</button>
            <a href="products" class="btn btn-secondary">Batal</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
