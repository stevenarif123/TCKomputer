<?php
/**
 * Admin - Add Category
 * Form to create a new category with name, description, image,
 * active status, and sort order. Includes server-side validation,
 * CSRF protection, name uniqueness check, and auto slug generation.
 */

$pageTitle = "Tambah Kategori";

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
    $description = trim($_POST['description'] ?? '');
    $sortOrder = (int) ($_POST['sort_order'] ?? 0);
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    // Preserve form data for re-display on error
    $formData = [
        'name' => $name,
        'description' => $description,
        'image_url' => trim($_POST['image_url'] ?? ''),
        'sort_order' => $sortOrder,
        'is_active' => $isActive,
    ];

    // Validation - Name (1-100 chars, required)
    if (empty($name)) {
        $errors[] = 'Nama kategori wajib diisi';
    } elseif (strlen($name) < 1 || strlen($name) > 100) {
        $errors[] = 'Nama kategori harus antara 1-100 karakter';
    }

    // Validation - Description (max 500 chars)
    if (strlen($description) > 500) {
        $errors[] = 'Deskripsi maksimal 500 karakter';
    }

    // Validation - Sort order (0-999)
    if ($sortOrder < 0 || $sortOrder > 999) {
        $errors[] = 'Urutan harus antara 0-999';
    }

    // Name uniqueness check
    if (!empty($name) && empty($errors)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE name = ?");
        $stmt->execute([$name]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'Nama kategori sudah digunakan';
        }
    }

    // Handle image upload
    $imageName = null;
    if (!empty($_FILES['image']['name'])) {
        $uploadError = '';
        $imageName = uploadImage($_FILES['image'], __DIR__ . '/../uploads/categories/', $uploadError);
        if ($imageName === false) {
            $errors[] = 'Upload gambar gagal: ' . ($uploadError ?: 'format tidak didukung atau ukuran melebihi 2MB');
            $imageName = null;
        }
    } else {
        $imageUrl = trim($_POST['image_url'] ?? '');
        if (!empty($imageUrl)) {
            $imageName = $imageUrl;
        }
    }

    // If no errors, insert category
    if (empty($errors)) {
        // Auto-generate slug
        $slug = generateSlug($name);

        // Validate slug is not empty (name with only special chars)
        if (empty($slug)) {
            $errors[] = 'Nama kategori harus mengandung huruf atau angka yang valid';
        } else {
            // Check slug uniqueness and append numeric suffix if exists
            $baseSlug = $slug;
            $suffix = 2;
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE slug = ?");
            $stmt->execute([$slug]);

            while ($stmt->fetchColumn() > 0) {
                $slug = $baseSlug . '-' . $suffix;
                $suffix++;
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE slug = ?");
                $stmt->execute([$slug]);
            }

            // Insert into database
            $stmt = $pdo->prepare(
                "INSERT INTO categories (name, slug, description, image, is_active, sort_order, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())"
            );
            $stmt->execute([
                $name, $slug, $description ?: null, $imageName, $isActive, $sortOrder
            ]);

            redirect('categories', 'Kategori berhasil ditambahkan');
        }
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

<div class="admin-page-header">
    <h2>Tambah Kategori Baru</h2>
    <a href="categories" class="btn btn-secondary">&laquo; Kembali</a>
</div>

<div class="admin-form-container">
    <form action="" method="POST" enctype="multipart/form-data" class="admin-form">
        <input type="hidden" name="csrf_token" value="<?= sanitizeOutput($csrfToken) ?>">

        <div class="form-group">
            <label for="name">Nama Kategori <span class="required">*</span></label>
            <input type="text" id="name" name="name" maxlength="100" required
                   value="<?= sanitizeOutput($formData['name'] ?? '') ?>"
                   placeholder="Masukkan nama kategori">
        </div>

        <div class="form-group">
            <label for="description">Deskripsi</label>
            <textarea id="description" name="description" rows="4" maxlength="500"
                      placeholder="Deskripsi kategori (maks 500 karakter)"><?= sanitizeOutput($formData['description'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label for="image">Gambar Kategori</label>
            <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp"
                   data-crop="true" data-aspect-ratio="1" data-width="200" data-height="200">
            <small class="form-help">Format: JPG, PNG, WebP. Maksimal 2MB. Rekomendasi ukuran: 200 x 200 piksel (rasio 1:1).</small>
        </div>

        <div class="form-group">
            <label for="image_url">Atau Gunakan Custom Icon / Gambar URL</label>
            <input type="text" id="image_url" name="image_url" placeholder="Contoh: laptop, smartphone, atau https://example.com/icon.png"
                   value="<?= sanitizeOutput($formData['image_url'] ?? '') ?>">
            <small class="form-help">Masukkan nama <strong>Google Material Symbol</strong> (contoh: <em>laptop, smartphone, cable, keyboard, printer, handyman, sd_card</em>) <strong>ATAU</strong> URL gambar eksternal lengkap. Jika mengupload file di atas, file upload akan lebih diutamakan.</small>
        </div>

        <div class="form-group">
            <label for="sort_order">Urutan</label>
            <input type="number" id="sort_order" name="sort_order" min="0" max="999"
                   value="<?= (int) ($formData['sort_order'] ?? 0) ?>"
                   placeholder="0">
            <small class="form-help">Urutan tampil di halaman pembeli (0-999). Angka kecil tampil lebih dulu.</small>
        </div>

        <div class="form-group form-checkbox">
            <label>
                <input type="checkbox" name="is_active" value="1"
                    <?= (isset($formData['is_active'])) ? (($formData['is_active'] == 1) ? 'checked' : '') : 'checked' ?>>
                Aktif (tampilkan di toko)
            </label>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Simpan Kategori</button>
            <a href="categories" class="btn btn-secondary">Batal</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
