<?php
/**
 * Profile Update Action Endpoint
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Metode request tidak valid.']);
    exit;
}

// CSRF validation — must run before any DB access (Req 12.1, 12.2, 12.5)
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Permintaan tidak valid, silakan muat ulang halaman.']);
    exit;
}

// Ensure the user is logged in
if (!isset($_SESSION['customer_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesi Anda telah berakhir, silakan masuk kembali.']);
    exit;
}

$userId = (int)$_SESSION['customer_id'];

// Collect input data
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$address = trim($_POST['address'] ?? '');
$shippingAreaId = isset($_POST['shipping_area_id']) && $_POST['shipping_area_id'] !== '' ? (int)$_POST['shipping_area_id'] : null;
$password = $_POST['password'] ?? '';
$passwordConfirm = $_POST['password_confirm'] ?? '';

// Validation - Required fields
// Validation - Required fields detailed checks
if (empty($name)) {
    echo json_encode(['success' => false, 'message' => 'Nama lengkap wajib diisi.']);
    exit;
}
if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Email wajib diisi.']);
    exit;
}
if (empty($phone)) {
    echo json_encode(['success' => false, 'message' => 'Nomor telepon wajib diisi.']);
    exit;
}
if ($shippingAreaId === null || $shippingAreaId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Area Pengiriman wajib dipilih.']);
    exit;
}
if (empty($address)) {
    echo json_encode(['success' => false, 'message' => 'Alamat lengkap wajib diisi.']);
    exit;
}

// Validation - Email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Format email tidak valid.']);
    exit;
}

// Validation - Phone format
if (!isValidPhoneNumber($phone)) {
    echo json_encode(['success' => false, 'message' => 'Format nomor telepon tidak valid.']);
    exit;
}

// Validation - Optional password change
$updatePassword = false;
if (!empty($password)) {
    if (strlen($password) < 6) {
        echo json_encode(['success' => false, 'message' => 'Kata sandi baru minimal 6 karakter.']);
        exit;
    }
    if ($password !== $passwordConfirm) {
        echo json_encode(['success' => false, 'message' => 'Konfirmasi kata sandi baru tidak cocok.']);
        exit;
    }
    $updatePassword = true;
}

try {
    $pdo = getDBConnection();
    
    // Check if phone number is already registered by another user
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE phone = ? AND id != ?");
    $stmt->execute([$phone, $userId]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'Nomor telepon sudah digunakan oleh akun lain.']);
        exit;
    }
    
    // Perform update
    if ($updatePassword) {
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare(
            "UPDATE users SET name = ?, email = ?, phone = ?, address = ?, shipping_area_id = ?, password = ?, updated_at = NOW() WHERE id = ?"
        );
        $stmt->execute([$name, $email, $phone, $address, $shippingAreaId, $passwordHash, $userId]);
    } else {
        $stmt = $pdo->prepare(
            "UPDATE users SET name = ?, email = ?, phone = ?, address = ?, shipping_area_id = ?, updated_at = NOW() WHERE id = ?"
        );
        $stmt->execute([$name, $email, $phone, $address, $shippingAreaId, $userId]);
    }
    
    // Update session profile
    $_SESSION['customer_profile'] = [
        'id' => $userId,
        'username' => $_SESSION['customer_profile']['username'],
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'address' => $address,
        'shipping_area_id' => $shippingAreaId
    ];
    
    // Create notification
    if (!isset($_SESSION['notifications'])) {
        $_SESSION['notifications'] = [];
    }
    $_SESSION['notifications'][] = [
        'title' => 'Profil Diperbarui',
        'message' => 'Data profil Anda berhasil diperbarui.',
        'time' => date('H:i'),
        'unread' => true
    ];
    
    echo json_encode(['success' => true, 'message' => 'Profil berhasil diperbarui!']);
    exit;
    
} catch (Exception $e) {
    error_log('Error in profile-update: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan sistem, silakan coba beberapa saat lagi.']);
    exit;
}
