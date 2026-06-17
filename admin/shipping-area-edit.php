<?php
/**
 * Admin - Edit Shipping Area
 * Pre-populates form with existing shipping area data, handles updates.
 * Validates CSRF token, area name (1-100 chars), cost (0-1,000,000), and active status.
 */

$pageTitle = "Edit Area Pengiriman";

// Process form before including header (redirect needs to happen before output)
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/admin-auth.php';

requireAdmin();

$pdo = getDBConnection();

// Get shipping area ID from URL
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    redirect('shipping-areas', 'Area pengiriman tidak ditemukan', 'error');
}

// Fetch shipping area from database
try {
    $stmt = $pdo->prepare("SELECT * FROM shipping_areas WHERE id = ?");
    $stmt->execute([$id]);
    $shippingArea = $stmt->fetch();
} catch (PDOException $e) {
    error_log('Error fetching shipping area: ' . $e->getMessage());
    redirect('shipping-areas', 'Terjadi kesalahan, silakan coba lagi', 'error');
}

if (!$shippingArea) {
    redirect('shipping-areas', 'Area pengiriman tidak ditemukan', 'error');
}

$errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($submittedToken)) {
        $errors[] = 'Token keamanan tidak valid. Silakan coba lagi.';
    }

    // Collect form data
    $areaName = trim($_POST['area_name'] ?? '');
    $regency = trim($_POST['regency'] ?? '');
    $cost = $_POST['cost'] ?? '';
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    // Validation - Area name (1-100 chars, required)
    if (empty($areaName)) {
        $errors[] = 'Nama area wajib diisi';
    } elseif (strlen($areaName) > 100) {
        $errors[] = 'Nama area maksimal 100 karakter';
    }

    // Validation - Regency
    $validRegencies = ['Tana Toraja', 'Toraja Utara'];
    if (empty($regency)) {
        $errors[] = 'Kabupaten/Kota wajib dipilih';
    } elseif (!in_array($regency, $validRegencies, true)) {
        $errors[] = 'Kabupaten/Kota tidak valid';
    }

    // Validation - Cost (integer 0-1000000)
    if ($cost === '') {
        $errors[] = 'Biaya pengiriman wajib diisi';
    } elseif (!is_numeric($cost) || (int) $cost != $cost || (int) $cost < 0 || (int) $cost > 1000000) {
        $errors[] = 'Biaya pengiriman harus berupa angka bulat antara 0 - 1.000.000';
    }

    // Cast cost to integer after validation
    $costInt = (int) $cost;

    // If no errors, update shipping area
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare(
                "UPDATE shipping_areas SET area_name = ?, regency = ?, cost = ?, is_active = ? WHERE id = ?"
            );
            $stmt->execute([$areaName, $regency, $costInt, $isActive, $id]);

            redirect('shipping-areas', 'Area pengiriman berhasil diperbarui');
        } catch (PDOException $e) {
            error_log('Error updating shipping area: ' . $e->getMessage());
            $errors[] = 'Gagal memperbarui area pengiriman, silakan coba lagi';
        }
    }

    // Update shipping area array with submitted data for form re-population on error
    $shippingArea['area_name'] = $areaName;
    $shippingArea['regency'] = $regency;
    $shippingArea['cost'] = $cost;
    $shippingArea['is_active'] = $isActive;
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
    <form action="shipping-area-edit?id=<?= (int) $id ?>" method="POST" class="admin-form">
        <input type="hidden" name="csrf_token" value="<?= sanitizeOutput($csrfToken) ?>">

        <div class="form-group">
            <label for="regency">Kabupaten/Kota <span class="required">*</span></label>
            <select id="regency" name="regency" required style="width: 100%; padding: 10px 12px; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 14px; background: #fff; color: #0b1c30; outline: none; margin-top: 4px;">
                <option value="">-- Pilih Kabupaten/Kota --</option>
                <option value="Tana Toraja" <?= (isset($shippingArea['regency']) && $shippingArea['regency'] === 'Tana Toraja') ? 'selected' : '' ?>>Tana Toraja</option>
                <option value="Toraja Utara" <?= (isset($shippingArea['regency']) && $shippingArea['regency'] === 'Toraja Utara') ? 'selected' : '' ?>>Toraja Utara</option>
            </select>
        </div>

        <div class="form-group">
            <label for="area_name">Nama Area <span class="required">*</span></label>
            <input type="text" id="area_name" name="area_name" maxlength="100" required
                   value="<?= sanitizeOutput($shippingArea['area_name'] ?? '') ?>"
                   placeholder="Masukkan nama area pengiriman">
        </div>

        <div class="form-group">
            <label for="cost">Biaya Pengiriman (Rp) <span class="required">*</span></label>
            <input type="number" id="cost" name="cost" min="0" max="1000000" required
                   value="<?= sanitizeOutput((string) ($shippingArea['cost'] ?? '0')) ?>"
                   placeholder="0">
            <small class="form-help">Biaya dalam Rupiah (0 - 1.000.000)</small>
        </div>

        <div class="form-group form-checkbox">
            <label>
                <input type="checkbox" name="is_active" value="1"
                    <?= !empty($shippingArea['is_active']) ? 'checked' : '' ?>>
                Aktif (tampilkan di checkout)
            </label>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            <a href="shipping-areas" class="btn btn-secondary">Batal</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
