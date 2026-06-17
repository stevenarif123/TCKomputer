<?php
/**
 * Admin - Add Banner
 * Form to create a new banner with title, description, image upload,
 * link URL, sort order, and active status.
 * Server-side validation, CSRF protection, and flash messages.
 */

$pageTitle = "Tambah Banner";

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
        $errors[] = 'Permintaan tidak valid, silakan coba lagi';
    }

    // Collect form data
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $linkUrl = trim($_POST['link_url'] ?? '');
    $sortOrder = (int) ($_POST['sort_order'] ?? 0);
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    // Preserve form data for re-display on error
    $formData = [
        'title' => $title,
        'description' => $description,
        'link_url' => $linkUrl,
        'sort_order' => $sortOrder,
        'is_active' => $isActive,
    ];

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

    // Handle image upload (required)
    $imageName = null;
    if (empty($_FILES['image']['name'])) {
        $errors[] = 'Gambar banner wajib diupload';
    } else {
        $uploadError = '';
        $imageName = uploadImage($_FILES['image'], __DIR__ . '/../uploads/banners/', $uploadError);
        if ($imageName === false) {
            $errors[] = 'Upload gambar gagal: ' . ($uploadError ?: 'format tidak didukung atau ukuran melebihi 2MB');
            $imageName = null;
        }
    }

    // If no errors, insert banner
    if (empty($errors)) {
        $stmt = $pdo->prepare(
            "INSERT INTO banners (title, description, image, link_url, sort_order, is_active, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([
            $title,
            !empty($description) ? $description : null,
            $imageName,
            !empty($linkUrl) ? $linkUrl : null,
            $sortOrder,
            $isActive
        ]);

        redirect('banners', 'Banner berhasil ditambahkan');
    }
}

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

<div class="admin-form-container">
    <form action="" method="POST" enctype="multipart/form-data" class="admin-form">
        <input type="hidden" name="csrf_token" value="<?= sanitizeOutput($csrfToken) ?>">

        <div class="form-group">
            <label for="title">Judul Banner <span class="required">*</span></label>
            <input type="text" id="title" name="title" maxlength="255" required
                   value="<?= sanitizeOutput($formData['title'] ?? '') ?>"
                   placeholder="Masukkan judul banner">
        </div>

        <div class="form-group">
            <label for="description">Deskripsi</label>
            <textarea id="description" name="description" rows="4" maxlength="1000"
                      placeholder="Deskripsi banner (opsional)"><?= sanitizeOutput($formData['description'] ?? '') ?></textarea>
            <small class="form-help">Maksimal 1000 karakter.</small>
        </div>

        <div class="form-group">
            <label for="image">Gambar Banner <span class="required">*</span></label>
            <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp" required
                   data-crop="true" data-aspect-ratio="3.1579" data-width="1200" data-height="380">
            <small class="form-help">Format: JPG, PNG, WebP. Maksimal 2MB. Rekomendasi ukuran: 1200 x 380 piksel (sesuai rasio banner halaman utama).</small>
        </div>

        <div class="form-group">
            <label for="link_url">Link URL</label>
            <input type="url" id="link_url" name="link_url" maxlength="2048"
                   value="<?= sanitizeOutput($formData['link_url'] ?? '') ?>"
                   placeholder="https://contoh.com/promo (opsional)">
        </div>

        <div class="form-group">
            <label for="sort_order">Urutan</label>
            <input type="number" id="sort_order" name="sort_order" min="0" max="9999"
                   value="<?= (int) ($formData['sort_order'] ?? 0) ?>"
                   placeholder="0">
            <small class="form-help">Angka lebih kecil ditampilkan lebih dulu (0-9999).</small>
        </div>

        <div class="form-group form-checkbox">
            <label>
                <input type="checkbox" name="is_active" value="1"
                    <?= (($formData['is_active'] ?? 0) == 1) ? 'checked' : '' ?>>
                Aktif (tampilkan di halaman utama)
            </label>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Simpan Banner</button>
            <a href="banners" class="btn btn-secondary">Batal</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
