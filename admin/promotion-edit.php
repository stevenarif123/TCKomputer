<?php
/**
 * Admin Promotion Edit
 * Form to edit an existing promotion.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/admin-auth.php';

requireAdmin();
$pdo = getDBConnection();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    redirect('promotions', 'ID Promosi tidak valid.', 'error');
}

// Fetch existing promotion
$stmt = $pdo->prepare("SELECT * FROM promotions WHERE id = ?");
$stmt->execute([$id]);
$promo = $stmt->fetch();

if (!$promo) {
    redirect('promotions', 'Promosi tidak ditemukan.', 'error');
}

$errors = [];

// Fetch categories and products for dropdowns
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();
$products = $pdo->query("SELECT id, name, selling_price FROM products WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($token)) {
        $errors[] = 'Token keamanan tidak valid, silakan coba lagi.';
    }

    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $promo_type = $_POST['promo_type'] ?? '';
    $discount_type = $_POST['discount_type'] ?? 'fixed';
    $discount_value = (int)($_POST['discount_value'] ?? 0);
    $min_spend = (int)($_POST['min_spend'] ?? 0);
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    $target_category_id = !empty($_POST['target_category_id']) ? (int)$_POST['target_category_id'] : null;
    $free_item_id = !empty($_POST['free_item_id']) ? (int)$_POST['free_item_id'] : null;

    if (empty($name)) {
        $errors[] = 'Nama promosi wajib diisi.';
    }
    if (empty($promo_type) || !in_array($promo_type, ['free_shipping', 'category_discount', 'free_item', 'cart_discount'])) {
        $errors[] = 'Tipe promosi tidak valid.';
    }
    if (empty($start_date) || empty($end_date)) {
        $errors[] = 'Tanggal mulai dan selesai wajib diisi.';
    } elseif (strtotime($start_date) >= strtotime($end_date)) {
        $errors[] = 'Tanggal selesai harus lebih besar dari tanggal mulai.';
    }
    
    if ($promo_type === 'category_discount' && !$target_category_id) {
        $errors[] = 'Pilih kategori target untuk diskon kategori.';
    }
    if ($promo_type === 'free_item' && !$free_item_id) {
        $errors[] = 'Pilih produk gratis untuk promo gratis item.';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE promotions 
                SET name = ?, description = ?, promo_type = ?, discount_type = ?, discount_value = ?, min_spend = ?, target_category_id = ?, free_item_id = ?, start_date = ?, end_date = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $name, $description, $promo_type, $discount_type, $discount_value, $min_spend, $target_category_id, $free_item_id, $start_date, $end_date, $is_active, $id
            ]);

            redirect('promotions', 'Promosi berhasil diperbarui.', 'success');
        } catch (PDOException $e) {
            $errors[] = 'Terjadi kesalahan database: ' . $e->getMessage();
        }
    }
}

$pageTitle = "Edit Promosi";
require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="admin-page-header">
    <h2>Edit Promosi</h2>
    <a href="promotions" class="btn btn-secondary">Batal & Kembali</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger" style="margin-bottom: 20px; background: #fee2e2; color: #991b1b; padding: 15px; border-radius: 8px;">
        <ul style="margin: 0; padding-left: 20px;">
            <?php foreach ($errors as $error): ?>
                <li><?= sanitizeOutput($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form action="promotion-edit?id=<?= $id ?>" method="POST" class="admin-form-container">
    <input type="hidden" name="csrf_token" value="<?= sanitizeOutput($csrfToken) ?>">

    <div class="form-group">
        <label for="name">Nama Promosi <span class="text-danger">*</span></label>
        <input type="text" id="name" name="name" class="form-control" value="<?= sanitizeOutput($_POST['name'] ?? $promo['name']) ?>" required>
    </div>

    <div class="form-group">
        <label for="description">Deskripsi (Opsional)</label>
        <textarea id="description" name="description" class="form-control" rows="2"><?= sanitizeOutput($_POST['description'] ?? $promo['description']) ?></textarea>
    </div>

    <div class="form-group">
        <label for="promo_type">Tipe Promosi <span class="text-danger">*</span></label>
        <select id="promo_type" name="promo_type" class="form-control" required onchange="togglePromoFields()">
            <?php $selType = $_POST['promo_type'] ?? $promo['promo_type']; ?>
            <option value="cart_discount" <?= ($selType === 'cart_discount') ? 'selected' : '' ?>>Diskon Keranjang</option>
            <option value="free_shipping" <?= ($selType === 'free_shipping') ? 'selected' : '' ?>>Gratis Ongkir</option>
            <option value="category_discount" <?= ($selType === 'category_discount') ? 'selected' : '' ?>>Diskon Kategori</option>
            <option value="free_item" <?= ($selType === 'free_item') ? 'selected' : '' ?>>Gratis Item</option>
        </select>
    </div>

    <div class="form-group" id="field-target_category_id" style="display: none;">
        <label for="target_category_id">Kategori Target <span class="text-danger">*</span></label>
        <select id="target_category_id" name="target_category_id" class="form-control">
            <option value="">-- Pilih Kategori --</option>
            <?php $selCat = $_POST['target_category_id'] ?? $promo['target_category_id']; ?>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= ($selCat == $cat['id']) ? 'selected' : '' ?>><?= sanitizeOutput($cat['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group" id="field-free_item_id" style="display: none;">
        <label for="free_item_id">Produk Gratis <span class="text-danger">*</span></label>
        <select id="free_item_id" name="free_item_id" class="form-control">
            <option value="">-- Pilih Produk --</option>
            <?php $selItem = $_POST['free_item_id'] ?? $promo['free_item_id']; ?>
            <?php foreach ($products as $prod): ?>
                <option value="<?= $prod['id'] ?>" <?= ($selItem == $prod['id']) ? 'selected' : '' ?>><?= sanitizeOutput($prod['name']) ?> (<?= formatRupiah($prod['selling_price']) ?>)</option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-row" style="display: flex; gap: 20px;">
        <div class="form-group" style="flex: 1;" id="field-discount_type">
            <label for="discount_type">Tipe Nilai Diskon</label>
            <select id="discount_type" name="discount_type" class="form-control">
                <?php $selDType = $_POST['discount_type'] ?? $promo['discount_type']; ?>
                <option value="fixed" <?= ($selDType === 'fixed') ? 'selected' : '' ?>>Nominal Tetap (Rp)</option>
                <option value="percentage" <?= ($selDType === 'percentage') ? 'selected' : '' ?>>Persentase (%)</option>
            </select>
        </div>
        <div class="form-group" style="flex: 1;" id="field-discount_value">
            <label for="discount_value">Nilai Diskon</label>
            <input type="number" id="discount_value" name="discount_value" class="form-control" value="<?= (int)($_POST['discount_value'] ?? $promo['discount_value']) ?>" min="0">
        </div>
    </div>

    <div class="form-group">
        <label for="min_spend">Minimal Belanja (Rp) <small class="text-muted">(Isi 0 jika tanpa minimal)</small></label>
        <input type="number" id="min_spend" name="min_spend" class="form-control" value="<?= (int)($_POST['min_spend'] ?? $promo['min_spend']) ?>" min="0">
    </div>

    <div class="form-row" style="display: flex; gap: 20px;">
        <div class="form-group" style="flex: 1;">
            <label for="start_date">Mulai Berlaku <span class="text-danger">*</span></label>
            <input type="datetime-local" id="start_date" name="start_date" class="form-control" value="<?= sanitizeOutput($_POST['start_date'] ?? date('Y-m-d\TH:i', strtotime($promo['start_date']))) ?>" required>
        </div>
        <div class="form-group" style="flex: 1;">
            <label for="end_date">Selesai Berlaku <span class="text-danger">*</span></label>
            <input type="datetime-local" id="end_date" name="end_date" class="form-control" value="<?= sanitizeOutput($_POST['end_date'] ?? date('Y-m-d\TH:i', strtotime($promo['end_date']))) ?>" required>
        </div>
    </div>

    <div class="form-group">
        <label class="toggle-switch">
            <?php $isActive = isset($_POST['is_active']) ? 1 : ($_SERVER['REQUEST_METHOD'] === 'POST' ? 0 : $promo['is_active']); ?>
            <input type="checkbox" name="is_active" value="1" <?= $isActive ? 'checked' : '' ?>>
            <span class="toggle-slider"></span>
        </label>
        <span style="margin-left: 10px; font-weight: 500;">Aktifkan Promosi Ini</span>
    </div>

    <div class="form-actions" style="margin-top: 30px;">
        <button type="submit" class="btn btn-primary" style="padding: 12px 24px;">Simpan Perubahan</button>
    </div>
</form>

<script>
function togglePromoFields() {
    const type = document.getElementById('promo_type').value;
    const catField = document.getElementById('field-target_category_id');
    const itemField = document.getElementById('field-free_item_id');
    const valTypeField = document.getElementById('field-discount_type');
    const valField = document.getElementById('field-discount_value');

    catField.style.display = (type === 'category_discount') ? 'block' : 'none';
    itemField.style.display = (type === 'free_item') ? 'block' : 'none';
    
    if (type === 'free_item') {
        valTypeField.style.display = 'none';
        valField.style.display = 'none';
    } else if (type === 'free_shipping') {
        valTypeField.style.display = 'none';
        valField.style.display = 'block';
        document.getElementById('discount_type').value = 'fixed';
    } else {
        valTypeField.style.display = 'block';
        valField.style.display = 'block';
    }
}

// Initial trigger
window.addEventListener('DOMContentLoaded', togglePromoFields);
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
