<?php
/**
 * Admin - Edit FAQ Category
 * Form to edit an existing FAQ category.
 * Requirements: 10.1, 10.2, 10.3, 13.1, 13.2
 */

$pageTitle = "Edit Kategori FAQ";

// Process form before including header
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/admin-auth.php';

requireAdmin();

$pdo = getDBConnection();

$categoryId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($categoryId <= 0) {
    redirect('faq-categories', 'Kategori FAQ tidak ditemukan', 'error');
}

// Fetch category
try {
    $stmt = $pdo->prepare("SELECT * FROM faq_categories WHERE id = ?");
    $stmt->execute([$categoryId]);
    $category = $stmt->fetch();
} catch (PDOException $e) {
    error_log('Error fetching faq category: ' . $e->getMessage());
    redirect('faq-categories', 'Terjadi kesalahan, silakan coba lagi', 'error');
}

if (!$category) {
    redirect('faq-categories', 'Kategori FAQ tidak ditemukan', 'error');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        redirect('faq-category-edit?id=' . $categoryId, 'Permintaan tidak valid, silakan coba lagi', 'error');
    }

    // Collect data
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $icon = trim($_POST['icon'] ?? '');
    $sortOrder = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    // Validate inputs
    $errors = validateFaqCategoryInput($pdo, $_POST, $categoryId);

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare(
                "UPDATE faq_categories 
                 SET name = ?, description = ?, icon = ?, sort_order = ?, is_active = ?, updated_at = NOW() 
                 WHERE id = ?"
            );
            $stmt->execute([
                $name,
                $description !== '' ? $description : null,
                $icon !== '' ? $icon : null,
                $sortOrder,
                $isActive,
                $categoryId
            ]);

            redirect('faq-categories', 'Kategori FAQ berhasil diperbarui');
        } catch (PDOException $e) {
            error_log('Error updating faq category: ' . $e->getMessage());
            $errors[] = 'Gagal memperbarui kategori FAQ, silakan coba lagi';
        }
    }

    // Keep inputs for form repopulation on error
    $category['name'] = $name;
    $category['description'] = $description;
    $category['icon'] = $icon;
    $category['sort_order'] = $sortOrder;
    $category['is_active'] = $isActive;
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
    <h2>Edit Kategori FAQ</h2>
    <a href="faq-categories" class="btn btn-secondary">&laquo; Kembali</a>
</div>

<div class="admin-form-container">
    <form action="" method="POST" class="admin-form">
        <input type="hidden" name="csrf_token" value="<?= sanitizeOutput($csrfToken) ?>">

        <div class="form-group">
            <label for="name">Nama Kategori <span class="required">*</span></label>
            <input type="text" id="name" name="name" maxlength="100" required
                   value="<?= sanitizeOutput($category['name'] ?? '') ?>"
                   placeholder="Masukkan nama kategori">
        </div>

        <div class="form-group">
            <label for="description">Deskripsi</label>
            <textarea id="description" name="description" rows="4" maxlength="500"
                      placeholder="Deskripsi kategori (maks 500 karakter)"><?= sanitizeOutput($category['description'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label for="icon">Ikon (Material Symbol)</label>
            <input type="text" id="icon" name="icon" maxlength="100"
                   value="<?= sanitizeOutput($category['icon'] ?? '') ?>"
                   placeholder="Contoh: shopping_cart, local_shipping, payments">
            <small class="form-help">Masukkan nama ikon dari Google Material Symbols.</small>
        </div>

        <div class="form-group">
            <label for="sort_order">Urutan</label>
            <input type="number" id="sort_order" name="sort_order" min="0" max="999"
                   value="<?= isset($category['sort_order']) ? (int) $category['sort_order'] : 0 ?>"
                   placeholder="0">
            <small class="form-help">Urutan tampil (0-999). Angka kecil tampil lebih dulu.</small>
        </div>

        <div class="form-group form-checkbox">
            <label>
                <input type="checkbox" name="is_active" value="1"
                    <?= (isset($category['is_active']) && $category['is_active'] == 1) ? 'checked' : '' ?>>
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
