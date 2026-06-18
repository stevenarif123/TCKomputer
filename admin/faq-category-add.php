<?php
/**
 * Admin - Add FAQ Category
 * Form to create a new FAQ category with name, description, icon,
 * sort order, and active status. Includes server-side validation,
 * CSRF protection, and name uniqueness check.
 * Requirements: 9.1, 9.2, 9.3, 9.4, 9.5, 9.6, 13.1, 13.2
 */

$pageTitle = "Tambah Kategori FAQ";

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

    // Collect and trim form data
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $icon = trim($_POST['icon'] ?? '');
    $sortOrder = isset($_POST['sort_order']) ? (int) $_POST['sort_order'] : 0;
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    // Preserve form data for re-display on error
    $formData = [
        'name' => $name,
        'description' => $description,
        'icon' => $icon,
        'sort_order' => $sortOrder,
        'is_active' => $isActive,
    ];

    // Call validation helper
    $validationErrors = validateFaqCategoryInput($pdo, $formData);
    $errors = array_merge($errors, $validationErrors);

    // If no errors, insert into database
    if (empty($errors)) {
        $stmt = $pdo->prepare(
            "INSERT INTO faq_categories (name, description, icon, sort_order, is_active, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, NOW(), NOW())"
        );
        $stmt->execute([
            $name,
            $description !== '' ? $description : null,
            $icon !== '' ? $icon : null,
            $sortOrder,
            $isActive
        ]);

        redirect('faq-categories', 'Kategori FAQ berhasil ditambahkan');
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
    <h2>Tambah Kategori FAQ</h2>
    <a href="faq-categories" class="btn btn-secondary">&laquo; Kembali</a>
</div>

<div class="admin-form-container">
    <form action="" method="POST" class="admin-form">
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
            <label for="icon">Ikon (Material Symbol)</label>
            <input type="text" id="icon" name="icon" maxlength="100"
                   value="<?= sanitizeOutput($formData['icon'] ?? '') ?>"
                   placeholder="Contoh: shopping_cart, local_shipping, payments">
            <small class="form-help">Masukkan nama ikon dari Google Material Symbols.</small>
        </div>

        <div class="form-group">
            <label for="sort_order">Urutan</label>
            <input type="number" id="sort_order" name="sort_order" min="0" max="999"
                   value="<?= isset($formData['sort_order']) ? (int) $formData['sort_order'] : 0 ?>"
                   placeholder="0">
            <small class="form-help">Urutan tampil (0-999). Angka kecil tampil lebih dulu.</small>
        </div>

        <div class="form-group form-checkbox">
            <label>
                <input type="checkbox" name="is_active" value="1"
                    <?= (!isset($formData['is_active']) || $formData['is_active'] == 1) ? 'checked' : '' ?>>
                Aktif (tampilkan di toko)
            </label>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Simpan Kategori</button>
            <a href="faq-categories" class="btn btn-secondary">Batal</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
