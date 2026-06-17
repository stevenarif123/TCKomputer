<?php
/**
 * Buyer Registration Action Endpoint
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Metode request tidak valid.']);
    exit;
}

// Collect input data
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$address = trim($_POST['address'] ?? '');
$shippingAreaId = isset($_POST['shipping_area_id']) && $_POST['shipping_area_id'] !== '' ? (int)$_POST['shipping_area_id'] : null;

// Validation - Required fields detailed checks
if (empty($username)) {
    echo json_encode(['success' => false, 'message' => 'Username wajib diisi.']);
    exit;
}
if (empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Kata sandi wajib diisi.']);
    exit;
}
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

// Validation - Username format (alphanumeric + underscore, 3-30 chars)
if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
    echo json_encode(['success' => false, 'message' => 'Username hanya boleh huruf, angka, garis bawah (_), dan sepanjang 3-30 karakter.']);
    exit;
}

// Validation - Password length
if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'message' => 'Kata sandi minimal 6 karakter.']);
    exit;
}

// Validation - Email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Format email tidak valid.']);
    exit;
}

// Validation - Phone format
if (!isValidPhoneNumber($phone)) {
    echo json_encode(['success' => false, 'message' => 'Format nomor telepon tidak valid (gunakan format 08xx atau +628xx).']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Check if username already exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'Username sudah digunakan. Silakan pilih username lain.']);
        exit;
    }
    
    // Check if phone number already exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE phone = ?");
    $stmt->execute([$phone]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'Nomor telepon sudah terdaftar. Silakan masuk (login) dengan akun Anda.']);
        exit;
    }
    
    // Hash password
    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
    
    // Insert into database
    $stmt = $pdo->prepare(
        "INSERT INTO users (username, phone, name, email, address, shipping_area_id, password, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
    );
    $stmt->execute([
        $username,
        $phone,
        $name,
        $email,
        $address,
        $shippingAreaId,
        $passwordHash
    ]);
    
    $userId = (int)$pdo->lastInsertId();
    
    // Auto-login: initialize profile session
    $_SESSION['customer_id'] = $userId;
    $_SESSION['customer_profile'] = [
        'id' => $userId,
        'username' => $username,
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'address' => $address,
        'shipping_area_id' => $shippingAreaId
    ];
    
    // Restore past orders history matching the registered phone number
    $stmt = $pdo->prepare("SELECT order_code FROM orders WHERE buyer_phone = ? ORDER BY id DESC");
    $stmt->execute([$phone]);
    $orders = $stmt->fetchAll();
    $_SESSION['my_orders'] = array_column($orders, 'order_code');
    
    // Create registration notification
    if (!isset($_SESSION['notifications'])) {
        $_SESSION['notifications'] = [];
    }
    $_SESSION['notifications'][] = [
        'title' => 'Pendaftaran Berhasil',
        'message' => 'Selamat datang di TC Komputer! Akun Anda berhasil dibuat.',
        'time' => date('H:i'),
        'unread' => true
    ];
    
    echo json_encode([
        'success' => true,
        'message' => 'Pendaftaran berhasil! Akun Anda telah aktif.'
    ]);
    exit;
    
} catch (Exception $e) {
    error_log('Error in profile-register: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan sistem, silakan coba beberapa saat lagi.']);
    exit;
}
