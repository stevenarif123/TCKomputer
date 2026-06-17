<?php
/**
 * Admin Store Settings Page
 * Displays and processes the store settings form.
 * Fields: store name, phone, address, email, logo upload, bank account, COD info, shipping info, footer text.
 * Validates required fields, email format, phone format, and logo image upload.
 * Replaces old logo on new upload. CSRF protected.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/admin-auth.php';

// Protect admin page - redirect to login if not authenticated
requireAdmin();

// Get database connection
$pdo = getDBConnection();

// Fetch current settings
try {
    $stmt = $pdo->prepare("SELECT * FROM store_settings LIMIT 1");
    $stmt->execute();
    $settings = $stmt->fetch();
} catch (PDOException $e) {
    error_log('Error fetching store settings: ' . $e->getMessage());
    $settings = null;
}

if (!$settings) {
    $settings = [
        'id' => 1,
        'store_name' => '',
        'phone' => '',
        'address' => '',
        'email' => '',
        'logo' => '',
        'bank_account' => '',
        'cod_info' => '',
        'shipping_info' => '',
        'footer_text' => '',
        'running_ticker' => '',
        'popular_searches' => '',
        'promo_banner_1_title' => '',
        'promo_banner_1_desc' => '',
        'promo_banner_1_link' => '',
        'promo_banner_1_icon' => '',
        'promo_banner_2_title' => '',
        'promo_banner_2_desc' => '',
        'promo_banner_2_link' => '',
        'promo_banner_2_icon' => '',
        'promo_banner_3_title' => '',
        'promo_banner_3_desc' => '',
        'promo_banner_3_link' => '',
        'promo_banner_3_icon' => '',
    ];
}

// Handle form submission
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    $token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($token)) {
        redirect('settings', 'Permintaan tidak valid, silakan coba lagi', 'error');
    }

    // Collect form data
    $storeName = trim($_POST['store_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $bankAccount = trim($_POST['bank_account'] ?? '');
    $codInfo = trim($_POST['cod_info'] ?? '');
    $shippingInfo = trim($_POST['shipping_info'] ?? '');
    $footerText = trim($_POST['footer_text'] ?? '');
    $runningTicker = trim($_POST['running_ticker'] ?? '');
    $popularSearches = trim($_POST['popular_searches'] ?? '');
    $promoBanner1Title = trim($_POST['promo_banner_1_title'] ?? '');
    $promoBanner1Desc = trim($_POST['promo_banner_1_desc'] ?? '');
    $promoBanner1Link = trim($_POST['promo_banner_1_link'] ?? '');
    $promoBanner1Icon = trim($_POST['promo_banner_1_icon'] ?? '');
    $promoBanner2Title = trim($_POST['promo_banner_2_title'] ?? '');
    $promoBanner2Desc = trim($_POST['promo_banner_2_desc'] ?? '');
    $promoBanner2Link = trim($_POST['promo_banner_2_link'] ?? '');
    $promoBanner2Icon = trim($_POST['promo_banner_2_icon'] ?? '');
    $promoBanner3Title = trim($_POST['promo_banner_3_title'] ?? '');
    $promoBanner3Desc = trim($_POST['promo_banner_3_desc'] ?? '');
    $promoBanner3Link = trim($_POST['promo_banner_3_link'] ?? '');
    $promoBanner3Icon = trim($_POST['promo_banner_3_icon'] ?? '');

    // Validation - Required fields
    if (empty($storeName)) {
        $errors[] = 'Nama toko wajib diisi';
    } elseif (strlen($storeName) > 255) {
        $errors[] = 'Nama toko maksimal 255 karakter';
    }

    if (empty($phone)) {
        $errors[] = 'Nomor telepon wajib diisi';
    } elseif (!isValidPhoneNumber($phone)) {
        $errors[] = 'Format nomor telepon tidak valid (gunakan format 08xx atau +628xx)';
    }

    if (empty($address)) {
        $errors[] = 'Alamat wajib diisi';
    }

    if (empty($email)) {
        $errors[] = 'Email wajib diisi';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid';
    }

    if (strlen($promoBanner1Title) > 255 || strlen($promoBanner2Title) > 255 || strlen($promoBanner3Title) > 255) {
        $errors[] = 'Judul promo banner maksimal 255 karakter';
    }
    if (strlen($promoBanner1Desc) > 255 || strlen($promoBanner2Desc) > 255 || strlen($promoBanner3Desc) > 255) {
        $errors[] = 'Deskripsi promo banner maksimal 255 karakter';
    }
    if (strlen($promoBanner1Link) > 255 || strlen($promoBanner2Link) > 255 || strlen($promoBanner3Link) > 255) {
        $errors[] = 'Link promo banner maksimal 255 karakter';
    }
    if (strlen($promoBanner1Icon) > 255 || strlen($promoBanner2Icon) > 255 || strlen($promoBanner3Icon) > 255) {
        $errors[] = 'Ikon promo banner maksimal 255 karakter';
    }

    // Handle logo upload
    $logoName = $settings['logo'] ?? ''; // Keep existing logo by default
    if (!empty($_FILES['logo']['name']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $uploadDir = __DIR__ . '/../uploads/logo';
        $uploadError = '';
        $newLogo = uploadImage($_FILES['logo'], $uploadDir, $uploadError);
        if ($newLogo === false) {
            $errors[] = 'Upload logo gagal: ' . ($uploadError ?: 'format tidak didukung atau ukuran melebihi 2MB');
        } else {
            // Delete old logo if exists
            if (!empty($settings['logo'])) {
                deleteImage($settings['logo'], $uploadDir);
            }
            $logoName = $newLogo;
        }
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare(
                "UPDATE store_settings SET 
                    store_name = ?, phone = ?, address = ?, email = ?, logo = ?,
                    bank_account = ?, cod_info = ?, shipping_info = ?, footer_text = ?,
                    running_ticker = ?, popular_searches = ?,
                    promo_banner_1_title = ?, promo_banner_1_desc = ?, promo_banner_1_link = ?, promo_banner_1_icon = ?,
                    promo_banner_2_title = ?, promo_banner_2_desc = ?, promo_banner_2_link = ?, promo_banner_2_icon = ?,
                    promo_banner_3_title = ?, promo_banner_3_desc = ?, promo_banner_3_link = ?, promo_banner_3_icon = ?,
                    updated_at = NOW()
                WHERE id = ?"
            );
            $stmt->execute([
                $storeName, $phone, $address, $email, $logoName,
                $bankAccount, $codInfo, $shippingInfo, $footerText,
                $runningTicker, $popularSearches,
                $promoBanner1Title, $promoBanner1Desc, $promoBanner1Link, $promoBanner1Icon,
                $promoBanner2Title, $promoBanner2Desc, $promoBanner2Link, $promoBanner2Icon,
                $promoBanner3Title, $promoBanner3Desc, $promoBanner3Link, $promoBanner3Icon,
                $settings['id']
            ]);

            redirect('settings', 'Pengaturan toko berhasil diperbarui', 'success');
        } catch (PDOException $e) {
            error_log('Error updating store settings: ' . $e->getMessage());
            $errors[] = 'Gagal memperbarui pengaturan, silakan coba lagi';
        }
    }

    // Update settings array with submitted data for form re-population on error
    $settings['store_name'] = $storeName;
    $settings['phone'] = $phone;
    $settings['address'] = $address;
    $settings['email'] = $email;
    $settings['logo'] = $logoName;
    $settings['bank_account'] = $bankAccount;
    $settings['cod_info'] = $codInfo;
    $settings['shipping_info'] = $shippingInfo;
    $settings['footer_text'] = $footerText;
    $settings['running_ticker'] = $runningTicker;
    $settings['popular_searches'] = $popularSearches;
    $settings['promo_banner_1_title'] = $promoBanner1Title;
    $settings['promo_banner_1_desc'] = $promoBanner1Desc;
    $settings['promo_banner_1_link'] = $promoBanner1Link;
    $settings['promo_banner_1_icon'] = $promoBanner1Icon;
    $settings['promo_banner_2_title'] = $promoBanner2Title;
    $settings['promo_banner_2_desc'] = $promoBanner2Desc;
    $settings['promo_banner_2_link'] = $promoBanner2Link;
    $settings['promo_banner_2_icon'] = $promoBanner2Icon;
    $settings['promo_banner_3_title'] = $promoBanner3Title;
    $settings['promo_banner_3_desc'] = $promoBanner3Desc;
    $settings['promo_banner_3_link'] = $promoBanner3Link;
    $settings['promo_banner_3_icon'] = $promoBanner3Icon;
}

$pageTitle = "Pengaturan Toko";
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

<form method="POST" action="settings" enctype="multipart/form-data" class="admin-form">
    <input type="hidden" name="csrf_token" value="<?= sanitizeOutput($csrfToken) ?>">

    <h3>Informasi Toko</h3>

    <div class="form-group">
        <label for="store_name">Nama Toko <span class="required">*</span></label>
        <input type="text" id="store_name" name="store_name" required maxlength="255"
               value="<?= sanitizeOutput($settings['store_name'] ?? '') ?>">
    </div>

    <div class="form-group">
        <label for="phone">Nomor Telepon <span class="required">*</span></label>
        <input type="text" id="phone" name="phone" required maxlength="20"
               value="<?= sanitizeOutput($settings['phone'] ?? '') ?>">
        <small class="form-help">Format: 08xxxxxxxxx atau +628xxxxxxxxx</small>
    </div>

    <div class="form-group">
        <label for="address">Alamat <span class="required">*</span></label>
        <textarea id="address" name="address" rows="3" required><?= sanitizeOutput($settings['address'] ?? '') ?></textarea>
    </div>

    <div class="form-group">
        <label for="email">Email <span class="required">*</span></label>
        <input type="email" id="email" name="email" required maxlength="255"
               value="<?= sanitizeOutput($settings['email'] ?? '') ?>">
    </div>

    <div class="form-group">
        <label for="logo">Logo Toko</label>
        <?php if (!empty($settings['logo'])): ?>
            <div class="current-image">
                <img src="/uploads/logo/<?= sanitizeOutput($settings['logo']) ?>"
                     alt="Logo toko saat ini" class="image-thumbnail">
                <p class="image-note">Logo saat ini. Upload logo baru untuk mengganti.</p>
            </div>
        <?php endif; ?>
        <input type="file" id="logo" name="logo" accept="image/jpeg,image/png,image/webp"
               data-crop="true" data-aspect-ratio="NaN" data-height="150">
        <small class="form-help">Format: JPG, PNG, WebP. Maksimal 2MB. Rekomendasi ukuran: Tinggi 80-120 piksel (landscape/horizontal) dengan latar belakang transparan (PNG).</small>
    </div>

    <h3>Informasi Pembayaran</h3>

    <div class="form-group">
        <label for="bank_account">Informasi Rekening Bank</label>
        <textarea id="bank_account" name="bank_account" rows="4"><?= sanitizeOutput($settings['bank_account'] ?? '') ?></textarea>
        <small class="form-help">Informasi rekening bank untuk pembayaran transfer. Ditampilkan di halaman checkout.</small>
    </div>

    <div class="form-group">
        <label for="cod_info">Informasi COD</label>
        <textarea id="cod_info" name="cod_info" rows="3"><?= sanitizeOutput($settings['cod_info'] ?? '') ?></textarea>
        <small class="form-help">Informasi terkait pembayaran COD. Ditampilkan di halaman checkout.</small>
    </div>

    <h3>Informasi Pengiriman</h3>

    <div class="form-group">
        <label for="shipping_info">Informasi Pengiriman</label>
        <textarea id="shipping_info" name="shipping_info" rows="3"><?= sanitizeOutput($settings['shipping_info'] ?? '') ?></textarea>
        <small class="form-help">Informasi terkait pengiriman. Ditampilkan di halaman checkout.</small>
    </div>

    <h3>Pengaturan Promo &amp; Homepage</h3>

    <div class="form-group">
        <label for="running_ticker">Teks Running Ticker</label>
        <textarea id="running_ticker" name="running_ticker" rows="3"><?= sanitizeOutput($settings['running_ticker'] ?? '') ?></textarea>
        <small class="form-help">Teks pengumuman/promo berjalan di bagian teratas homepage. Kosongkan untuk menyembunyikan ticker ini.</small>
    </div>

    <div class="form-group">
        <label for="popular_searches">Pencarian Populer</label>
        <input type="text" id="popular_searches" name="popular_searches" value="<?= sanitizeOutput($settings['popular_searches'] ?? '') ?>">
        <small class="form-help">Daftar kata kunci pencarian populer. Pisahkan dengan tanda koma (contoh: RTX 4060, Ryzen 5, SSD NVMe). Kosongkan untuk menyembunyikan bagian pencarian populer.</small>
    </div>

    <div style="border: 1px solid #e2e8f0; padding: 1.25rem; border-radius: 8px; margin-bottom: 1.5rem; background-color: #f8fafc;">
        <h4 style="margin-top: 0; margin-bottom: 1rem; font-weight: bold; color: #1e293b; border-b: 1px solid #e2e8f0; padding-bottom: 0.5rem;">Banner Kampanye 1 (Kiri)</h4>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
            <div class="form-group" style="margin-bottom: 0;">
                <label for="promo_banner_1_title">Judul Banner 1</label>
                <input type="text" id="promo_banner_1_title" name="promo_banner_1_title" value="<?= sanitizeOutput($settings['promo_banner_1_title'] ?? '') ?>">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label for="promo_banner_1_desc">Deskripsi Banner 1</label>
                <input type="text" id="promo_banner_1_desc" name="promo_banner_1_desc" value="<?= sanitizeOutput($settings['promo_banner_1_desc'] ?? '') ?>">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label for="promo_banner_1_link">Link URL Banner 1</label>
                <input type="text" id="promo_banner_1_link" name="promo_banner_1_link" value="<?= sanitizeOutput($settings['promo_banner_1_link'] ?? '') ?>">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label for="promo_banner_1_icon">Ikon Banner 1 (Material Symbol)</label>
                <input type="text" id="promo_banner_1_icon" name="promo_banner_1_icon" value="<?= sanitizeOutput($settings['promo_banner_1_icon'] ?? '') ?>">
                <small class="form-help">Contoh: desktop_windows, keyboard, sd_card</small>
            </div>
        </div>
    </div>

    <div style="border: 1px solid #e2e8f0; padding: 1.25rem; border-radius: 8px; margin-bottom: 1.5rem; background-color: #f8fafc;">
        <h4 style="margin-top: 0; margin-bottom: 1rem; font-weight: bold; color: #1e293b; border-b: 1px solid #e2e8f0; padding-bottom: 0.5rem;">Banner Kampanye 2 (Tengah)</h4>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
            <div class="form-group" style="margin-bottom: 0;">
                <label for="promo_banner_2_title">Judul Banner 2</label>
                <input type="text" id="promo_banner_2_title" name="promo_banner_2_title" value="<?= sanitizeOutput($settings['promo_banner_2_title'] ?? '') ?>">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label for="promo_banner_2_desc">Deskripsi Banner 2</label>
                <input type="text" id="promo_banner_2_desc" name="promo_banner_2_desc" value="<?= sanitizeOutput($settings['promo_banner_2_desc'] ?? '') ?>">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label for="promo_banner_2_link">Link URL Banner 2</label>
                <input type="text" id="promo_banner_2_link" name="promo_banner_2_link" value="<?= sanitizeOutput($settings['promo_banner_2_link'] ?? '') ?>">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label for="promo_banner_2_icon">Ikon Banner 2 (Material Symbol)</label>
                <input type="text" id="promo_banner_2_icon" name="promo_banner_2_icon" value="<?= sanitizeOutput($settings['promo_banner_2_icon'] ?? '') ?>">
                <small class="form-help">Contoh: desktop_windows, keyboard, sd_card</small>
            </div>
        </div>
    </div>

    <div style="border: 1px solid #e2e8f0; padding: 1.25rem; border-radius: 8px; margin-bottom: 1.5rem; background-color: #f8fafc;">
        <h4 style="margin-top: 0; margin-bottom: 1rem; font-weight: bold; color: #1e293b; border-b: 1px solid #e2e8f0; padding-bottom: 0.5rem;">Banner Kampanye 3 (Kanan)</h4>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
            <div class="form-group" style="margin-bottom: 0;">
                <label for="promo_banner_3_title">Judul Banner 3</label>
                <input type="text" id="promo_banner_3_title" name="promo_banner_3_title" value="<?= sanitizeOutput($settings['promo_banner_3_title'] ?? '') ?>">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label for="promo_banner_3_desc">Deskripsi Banner 3</label>
                <input type="text" id="promo_banner_3_desc" name="promo_banner_3_desc" value="<?= sanitizeOutput($settings['promo_banner_3_desc'] ?? '') ?>">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label for="promo_banner_3_link">Link URL Banner 3</label>
                <input type="text" id="promo_banner_3_link" name="promo_banner_3_link" value="<?= sanitizeOutput($settings['promo_banner_3_link'] ?? '') ?>">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label for="promo_banner_3_icon">Ikon Banner 3 (Material Symbol)</label>
                <input type="text" id="promo_banner_3_icon" name="promo_banner_3_icon" value="<?= sanitizeOutput($settings['promo_banner_3_icon'] ?? '') ?>">
                <small class="form-help">Contoh: desktop_windows, keyboard, sd_card</small>
            </div>
        </div>
    </div>

    <h3>Lainnya</h3>

    <div class="form-group">
        <label for="footer_text">Teks Footer</label>
        <textarea id="footer_text" name="footer_text" rows="3"><?= sanitizeOutput($settings['footer_text'] ?? '') ?></textarea>
        <small class="form-help">Teks yang ditampilkan di bagian footer halaman pembeli.</small>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Simpan Pengaturan</button>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
