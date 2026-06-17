<?php
/**
 * Buyer Login by Username or Phone with Password Action
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/security.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Metode request tidak valid.']);
    exit;
}

// CSRF validation — must run before any DB access (Req 12.1, 12.2, 12.5)
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Permintaan tidak valid, silakan muat ulang halaman.']);
    exit;
}

$loginIdentifier = trim($_POST['login_identifier'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($loginIdentifier)) {
    echo json_encode(['success' => false, 'message' => 'Username atau Nomor Telepon wajib diisi.']);
    exit;
}

if (empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Kata sandi wajib diisi.']);
    exit;
}

// Rate limiting — generous threshold for buyers (Req 11.1)
$pdo   = getDBConnection();
$rlKey = buildRateLimitKey('login', $loginIdentifier, $_SERVER['REMOTE_ADDR'] ?? '');
$rl    = checkRateLimit($pdo, 'login', $rlKey, 10, 900);
if (!$rl['allowed']) {
    echo json_encode(['success' => false, 'message' => 'Terlalu banyak percobaan. Coba lagi dalam ' . $rl['retry_after'] . ' detik.']);
    exit;
}

try {
    // pdo already initialized above for rate limit check
    
    // Fetch user matching username or phone number
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR phone = ?");
    $stmt->execute([$loginIdentifier, $loginIdentifier]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($password, $user['password'])) {
        // Record failed attempt
        recordAuthAttempt($pdo, 'login', $rlKey);
        echo json_encode(['success' => false, 'message' => 'Username/Nomor Telepon atau kata sandi salah.']);
        exit;
    }
    
    // Success — clear rate limit
    clearRateLimit($pdo, 'login', $rlKey);
    
    // Initialize user session
    $_SESSION['customer_id'] = $user['id'];
    $_SESSION['customer_profile'] = [
        'id' => $user['id'],
        'username' => $user['username'],
        'name' => $user['name'],
        'email' => $user['email'],
        'phone' => $user['phone'],
        'address' => $user['address'],
        'shipping_area_id' => $user['shipping_area_id']
    ];
    
    // Restore past orders history matching the user's phone number
    $stmt = $pdo->prepare("SELECT order_code FROM orders WHERE buyer_phone = ? ORDER BY id DESC");
    $stmt->execute([$user['phone']]);
    $orders = $stmt->fetchAll();
    $_SESSION['my_orders'] = array_column($orders, 'order_code');
    
    // Create login notification
    if (!isset($_SESSION['notifications'])) {
        $_SESSION['notifications'] = [];
    }
    $_SESSION['notifications'][] = [
        'title' => 'Masuk Berhasil',
        'message' => 'Selamat datang kembali, ' . $user['name'] . '!',
        'time' => date('H:i'),
        'unread' => true
    ];
    
    echo json_encode([
        'success' => true, 
        'message' => 'Berhasil masuk! Profil dan riwayat belanja Anda telah dimuat.'
    ]);
    exit;

} catch (Exception $e) {
    error_log('Error in profile-login: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan sistem, silakan coba beberapa saat lagi.']);
    exit;
}
