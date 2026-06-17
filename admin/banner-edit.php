<?php
/**
 * Admin Banner Edit Page
 * Pre-populates form with existing banner data, handles updates including image replacement.
 * Preserves existing image if no new upload is provided.
 * Validates CSRF token on POST submission.
 */

$pageTitle = "Edit Banner";
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/admin-auth.php';

// Protect admin page - redirect to login if not authenticated
requireAdmin();

$pdo = getDBConnection();

// Get banner ID from URL
$bannerId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($bannerId <= 0) {
    redirect('banners', 'Banner tidak ditemukan', 'error');
}

// Fetch banner from database
try {
    $stmt = $pdo->prepare("SELECT * FROM banners WHERE id = ?");
    $stmt->execute([$bannerId]);
    $banner = $stmt->fetch();
} catch (PDOException $e) {
    error_log('Error fetching banner: ' . $e->getMessage());
    redirect('banners', 'Terjadi kesalahan, silakan coba lagi', 'error');
}

if (!$banner) {
    redirect('banners', 'Banner tidak ditemukan', 'error');
}

// Handle form submission
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    $token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($token)) {
        redirect('banner-edit?id=' . $bannerId, 'Permintaan tidak valid, silakan coba lagi', 'error');
    }

    // Collect form data
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $linkUrl = trim($_POST['link_url'] ?? '');
    $sortOrder = (int) ($_POST['sort_order'] ?? 0);
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    // Validation
    if (empty($title)) {
        $errors[] = 'Judul banner wajib diisi';
    } elseif (strlen($title) > 255) {
        $errors[] = 'Judul banner maksimal 255 karakter';
    }

    if (strlen($description) > 1000) {
        $errors[] = 'Deskripsi maksimal 1000 karakter';
    }

    if (strlen($linkUrl) > 2048) {
        $errors[] = 'Link URL maksimal 2048 karakter';
    }

    if ($sortOrder < 0 || $sortOrder > 9999) {
        $errors[] = 'Urutan harus antara 0 dan 9999';
    }

    // Handle image upload (optional - keep existing if not uploaded)
    $imageName = $banner['image']; // Keep existing image by default
    if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $uploadDir = __DIR__ . '/../uploads/banners';
        $uploadError = '';
        $newImage = uploadImage($_FILES['image'], $uploadDir, $uploadError);
        if ($newImage === false) {
            $errors[] = 'Upload gambar gagal: ' . ($uploadError ?: 'format tidak didukung atau ukuran melebihi 2MB');
        } else {
            // Delete old image if exists
            if (!empty($banner['image'])) {
                deleteImage($banner['image'], $uploadDir);
            }
            $imageName = $newImage;
        }
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare(
                "UPDATE banners SET 
                    title = ?, description = ?, image = ?, 
                    link_url = ?, sort_order = ?, is_active = ?
                WHERE id = ?"
            );
            $stmt->execute([
                $title, $description, $imageName,
                $linkUrl, $sortOrder, $isActive, $bannerId
            ]);

            redirect('banners', 'Banner berhasil diperbarui', 'success');
        } catch (PDOException $e) {
            error_log('Error updating banner: ' . $e->getMessage());
            $errors[] = 'Gagal memperbarui banner, silakan coba lagi';
        }
    }

    // Update banner array with submitted data for form re-population on error
    $banner['title'] = $title;
    $banner['description'] = $description;
    $banner['link_url'] = $linkUrl;
    $banner['sort_order'] = $sortOrder;
    $banner['is_active'] = $isActive;
}

$pageTitle = "Edit Banner";
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
    <h2>Edit Banner</h2>
    <a href="banners" class="btn btn-secondary">&laquo; Kembali</a>
</div>

<form method="POST" action="banner-edit?id=<?= (int) $bannerId ?>" enctype="multipart/form-data" class="admin-form">
    <input type="hidden" name="csrf_token" value="<?= sanitizeOutput($csrfToken) ?>">

    <div class="form-group">
        <label for="title">Judul Banner <span class="required">*</span></label>
        <input type="text" id="title" name="title" required maxlength="255"
               value="<?= sanitizeOutput($banner['title'] ?? '') ?>">
    </div>

    <div class="form-group">
        <label for="description">Deskripsi</label>
        <textarea id="description" name="description" rows="4" maxlength="1000"><?= sanitizeOutput($banner['description'] ?? '') ?></textarea>
        <small class="form-help">Maksimal 1000 karakter.</small>
    </div>

    <div class="form-group">
        <label for="image">Gambar Banner</label>
        <?php if (!empty($banner['image'])): ?>
            <div class="current-image">
                <img src="/uploads/banners/<?= sanitizeOutput($banner['image']) ?>" 
                     alt="Current banner image" class="image-thumbnail">
                <p class="image-note">Gambar saat ini. Upload gambar baru untuk mengganti.</p>
            </div>
        <?php endif; ?>
        <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp"
               data-crop="true" data-aspect-ratio="3.1579" data-width="1200" data-height="380">
        <small class="form-help">Format: JPG, PNG, WebP. Maksimal 2MB. Rekomendasi ukuran: 1200 x 380 piksel (sesuai rasio banner halaman utama). Kosongkan jika tidak ingin mengubah gambar.</small>
    </div>

    <div class="form-group">
        <label for="link_url">Link URL</label>
        <input type="url" id="link_url" name="link_url" maxlength="2048"
               value="<?= sanitizeOutput($banner['link_url'] ?? '') ?>">
        <small class="form-help">URL tujuan saat banner diklik. Maksimal 2048 karakter.</small>
    </div>

    <div class="form-group">
        <label for="sort_order">Urutan</label>
        <input type="number" id="sort_order" name="sort_order" min="0" max="9999"
               value="<?= (int) ($banner['sort_order'] ?? 0) ?>">
        <small class="form-help">Angka 0-9999. Banner dengan angka lebih kecil ditampilkan lebih dulu.</small>
    </div>

    <div class="form-group">
        <label class="checkbox-label">
            <input type="checkbox" name="is_active" value="1"
                   <?= !empty($banner['is_active']) ? 'checked' : '' ?>>
            Aktif
        </label>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        <a href="banners" class="btn btn-secondary">Batal</a>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
