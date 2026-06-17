<?php
/**
 * Admin Category Edit Page
 * Pre-populates form with existing category data, handles updates including image replacement.
 * Validates CSRF token on POST submission.
 * Name uniqueness check excludes the current category.
 */

$pageTitle = "Edit Kategori";
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/admin-auth.php';

// Protect admin page - redirect to login if not authenticated
requireAdmin();

$pdo = getDBConnection();

// Get category ID from URL
$categoryId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($categoryId <= 0) {
    redirect('categories', 'Kategori tidak ditemukan', 'error');
}

// Fetch category from database
try {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$categoryId]);
    $category = $stmt->fetch();
} catch (PDOException $e) {
    error_log('Error fetching category: ' . $e->getMessage());
    redirect('categories', 'Terjadi kesalahan, silakan coba lagi', 'error');
}

if (!$category) {
    redirect('categories', 'Kategori tidak ditemukan', 'error');
}

// Handle form submission
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    $token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($token)) {
        redirect('category-edit?id=' . $categoryId, 'Permintaan tidak valid, silakan coba lagi', 'error');
    }

    // Collect form data
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $sortOrder = (int) ($_POST['sort_order'] ?? 0);
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    // Validation
    if (empty($name)) {
        $errors[] = 'Nama kategori wajib diisi';
    } elseif (strlen($name) < 1 || strlen($name) > 100) {
        $errors[] = 'Nama kategori harus 1-100 karakter';
    }

    if (strlen($description) > 500) {
        $errors[] = 'Deskripsi maksimal 500 karakter';
    }

    if ($sortOrder < 0 || $sortOrder > 999) {
        $errors[] = 'Urutan harus antara 0 dan 999';
    }

    // Name uniqueness check (excluding current category)
    if (!empty($name)) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE name = ? AND id != ?");
            $stmt->execute([$name, $categoryId]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = 'Nama kategori sudah digunakan';
            }
        } catch (PDOException $e) {
            error_log('Error checking category name: ' . $e->getMessage());
            $errors[] = 'Gagal memvalidasi nama kategori';
        }
    }

    // Handle image upload or URL/icon
    $imageName = $category['image']; // Keep existing image by default
    $uploadDir = __DIR__ . '/../uploads/categories';
    $isUploadedFile = (!empty($category['image']) && file_exists($uploadDir . '/' . $category['image']));

    if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $uploadDir = __DIR__ . '/../uploads/categories';
        $uploadError = '';
        $newImage = uploadImage($_FILES['image'], $uploadDir, $uploadError);
        if ($newImage === false) {
            $errors[] = 'Upload gambar gagal: ' . ($uploadError ?: 'format tidak didukung atau ukuran melebihi 2MB');
        } else {
            // Delete old image if exists and was an uploaded file
            if ($isUploadedFile) {
                deleteImage($category['image'], $uploadDir);
            }
            $imageName = $newImage;
        }
    } else {
        $imageUrl = trim($_POST['image_url'] ?? '');
        if (!empty($imageUrl)) {
            if ($imageUrl !== $category['image']) {
                // Delete old image if it was an uploaded file
                if ($isUploadedFile) {
                    deleteImage($category['image'], $uploadDir);
                }
                $imageName = $imageUrl;
            }
        } else {
            // If image_url is cleared but was an icon/URL, set to null
            if (!$isUploadedFile) {
                $imageName = null;
            }
        }
    }

    if (empty($errors)) {
        // Generate slug from name (only if name changed)
        $slug = $category['slug'];
        if ($name !== $category['name']) {
            $slug = generateSlug($name);

            // Ensure unique slug (excluding current category)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE slug = ? AND id != ?");
            $stmt->execute([$slug, $categoryId]);
            if ($stmt->fetchColumn() > 0) {
                $suffix = 2;
                do {
                    $newSlug = $slug . '-' . $suffix;
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE slug = ? AND id != ?");
                    $stmt->execute([$newSlug, $categoryId]);
                    $suffix++;
                } while ($stmt->fetchColumn() > 0);
                $slug = $newSlug;
            }
        }

        try {
            $stmt = $pdo->prepare(
                "UPDATE categories SET 
                    name = ?, slug = ?, description = ?, image = ?, 
                    is_active = ?, sort_order = ?, updated_at = NOW()
                WHERE id = ?"
            );
            $stmt->execute([
                $name, $slug, $description, $imageName,
                $isActive, $sortOrder, $categoryId
            ]);

            redirect('categories', 'Kategori berhasil diperbarui', 'success');
        } catch (PDOException $e) {
            error_log('Error updating category: ' . $e->getMessage());
            $errors[] = 'Gagal memperbarui kategori, silakan coba lagi';
        }
    }

    // Update category array with submitted data for form re-population on error
    $category['name'] = $name;
    $category['description'] = $description;
    $category['image'] = $imageName;
    $category['sort_order'] = $sortOrder;
    $category['is_active'] = $isActive;
}

$pageTitle = "Edit Kategori";
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
    <h2>Edit Kategori</h2>
    <a href="categories" class="btn btn-secondary">&laquo; Kembali</a>
</div>

<form method="POST" action="category-edit?id=<?= (int) $categoryId ?>" enctype="multipart/form-data" class="admin-form">
    <input type="hidden" name="csrf_token" value="<?= sanitizeOutput($csrfToken) ?>">

    <div class="form-group">
        <label for="name">Nama Kategori <span class="required">*</span></label>
        <input type="text" id="name" name="name" required maxlength="100"
               value="<?= sanitizeOutput($category['name'] ?? '') ?>">
    </div>

    <div class="form-group">
        <label for="description">Deskripsi</label>
        <textarea id="description" name="description" rows="4" maxlength="500"><?= sanitizeOutput($category['description'] ?? '') ?></textarea>
        <small class="form-help">Maksimal 500 karakter.</small>
    </div>

    <div class="form-group">
        <label for="sort_order">Urutan</label>
        <input type="number" id="sort_order" name="sort_order" min="0" max="999"
               value="<?= (int) ($category['sort_order'] ?? 0) ?>">
        <small class="form-help">Angka 0-999. Kategori dengan angka lebih kecil ditampilkan lebih dulu.</small>
    </div>

    <div class="form-group">
        <label for="image">Gambar Kategori</label>
        <?php 
        $currentImage = $category['image'] ?? '';
        $isUploadedFile = (!empty($currentImage) && file_exists(__DIR__ . '/../uploads/categories/' . $currentImage));
        $imageUrlVal = (!$isUploadedFile) ? $currentImage : '';
        
        if ($isUploadedFile): ?>
            <div class="current-image">
                <img src="/uploads/categories/<?= sanitizeOutput($currentImage) ?>" 
                     alt="Current category image" class="image-thumbnail">
                <p class="image-note">Gambar saat ini. Upload gambar baru untuk mengganti.</p>
            </div>
        <?php elseif (!empty($currentImage)): // Custom URL or Material Symbol ?>
            <div class="current-image">
                <?php if (stripos($currentImage, 'http://') === 0 || stripos($currentImage, 'https://') === 0 || stripos($currentImage, '/') === 0): ?>
                    <img src="<?= sanitizeOutput($currentImage) ?>" alt="Current category icon" class="image-thumbnail" style="max-height: 80px; width: auto; object-fit: contain; display: block; margin-bottom: 8px;">
                <?php else: ?>
                    <div style="display: inline-flex; align-items: center; justify-content: center; width: 60px; height: 60px; background: #e5eeff; border-radius: 12px; margin-bottom: 8px;">
                        <span class="material-symbols-outlined text-secondary" style="font-size: 32px;"><?= sanitizeOutput($currentImage) ?></span>
                    </div>
                <?php endif; ?>
                <p class="image-note">Icon/URL saat ini: <strong><?= sanitizeOutput($currentImage) ?></strong></p>
            </div>
        <?php endif; ?>
        <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp"
               data-crop="true" data-aspect-ratio="1" data-width="200" data-height="200">
        <small class="form-help">Format: JPG, PNG, WebP. Maksimal 2MB. Rekomendasi ukuran: 200 x 200 piksel (rasio 1:1).</small>
    </div>

    <div class="form-group">
        <label for="image_url">Atau Gunakan Custom Icon / Gambar URL</label>
        <input type="text" id="image_url" name="image_url" placeholder="Contoh: laptop, smartphone, atau https://example.com/icon.png"
               value="<?= sanitizeOutput($imageUrlVal) ?>">
        <small class="form-help">Masukkan nama <strong>Google Material Symbol</strong> (contoh: <em>laptop, smartphone, cable, keyboard, printer, handyman, sd_card</em>) <strong>ATAU</strong> URL gambar eksternal lengkap. Jika mengupload file di atas, file upload akan lebih diutamakan.</small>
    </div>

    <div class="form-group">
        <label class="checkbox-label">
            <input type="checkbox" name="is_active" value="1"
                   <?= !empty($category['is_active']) ? 'checked' : '' ?>>
            Aktif
        </label>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        <a href="categories" class="btn btn-secondary">Batal</a>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
