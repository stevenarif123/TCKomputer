<?php
/**
 * Admin - Add Shipping Area
 * Form to create a new shipping area with area name, cost,
 * and active status. Includes server-side validation and CSRF protection.
 */

$pageTitle = "Tambah Area Pengiriman";

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
    $areaName = trim($_POST['area_name'] ?? '');
    $regency = trim($_POST['regency'] ?? '');
    $cost = $_POST['cost'] ?? '';
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    // Preserve form data for re-display on error
    $formData = [
        'area_name' => $areaName,
        'regency' => $regency,
        'cost' => $cost,
        'is_active' => $isActive,
    ];

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

    // If no errors, insert shipping area
    if (empty($errors)) {
        $stmt = $pdo->prepare(
            "INSERT INTO shipping_areas (area_name, regency, cost, is_active, created_at)
             VALUES (?, ?, ?, ?, NOW())"
        );
        $stmt->execute([$areaName, $regency, $costInt, $isActive]);

        redirect('shipping-areas', 'Area pengiriman berhasil ditambahkan');
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
    <form action="" method="POST" class="admin-form">
        <input type="hidden" name="csrf_token" value="<?= sanitizeOutput($csrfToken) ?>">

        <div class="form-group">
            <label for="regency">Kabupaten/Kota <span class="required">*</span></label>
            <select id="regency" name="regency" required style="width: 100%; padding: 10px 12px; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 14px; background: #fff; color: #0b1c30; outline: none; margin-top: 4px;">
                <option value="">-- Pilih Kabupaten/Kota --</option>
                <option value="Tana Toraja" <?= (isset($formData['regency']) && $formData['regency'] === 'Tana Toraja') ? 'selected' : '' ?>>Tana Toraja</option>
                <option value="Toraja Utara" <?= (isset($formData['regency']) && $formData['regency'] === 'Toraja Utara') ? 'selected' : '' ?>>Toraja Utara</option>
            </select>
        </div>

        <div class="form-group">
            <label for="area_name">Nama Area <span class="required">*</span></label>
            <input type="text" id="area_name" name="area_name" maxlength="100" required
                   value="<?= sanitizeOutput($formData['area_name'] ?? '') ?>"
                   placeholder="Masukkan nama area pengiriman">
        </div>

        <div class="form-group">
            <label for="cost">Biaya Pengiriman (Rp) <span class="required">*</span></label>
            <input type="number" id="cost" name="cost" min="0" max="1000000" required
                   value="<?= sanitizeOutput($formData['cost'] ?? '0') ?>"
                   placeholder="0">
            <small class="form-help">Biaya dalam Rupiah (0 - 1.000.000)</small>
        </div>

        <div class="form-group form-checkbox">
            <label>
                <input type="checkbox" name="is_active" value="1"
                    <?= (isset($formData['is_active'])) ? (($formData['is_active'] == 1) ? 'checked' : '') : 'checked' ?>>
                Aktif (tampilkan di checkout)
            </label>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Simpan Area Pengiriman</button>
            <a href="shipping-areas" class="btn btn-secondary">Batal</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
