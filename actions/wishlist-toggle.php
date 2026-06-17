<?php
/**
 * Wishlist Toggle Action Endpoint
 * Handles adding/removing products in session wishlist.
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

// Decode raw JSON input if sent that way
$input = json_decode(file_get_contents('php://input'), true);
$productId = (int)($input['product_id'] ?? $_POST['product_id'] ?? 0);

if ($productId <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID produk tidak valid']);
    exit;
}

// Check if product exists in database to prevent arbitrary data insertion
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT id, name FROM products WHERE id = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    
    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Produk tidak ditemukan atau tidak aktif']);
        exit;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Kesalahan koneksi database']);
    exit;
}

if (!isset($_SESSION['wishlist'])) {
    $_SESSION['wishlist'] = [];
}

$key = array_search($productId, $_SESSION['wishlist']);
if ($key !== false) {
    unset($_SESSION['wishlist'][$key]);
    $_SESSION['wishlist'] = array_values($_SESSION['wishlist']); // Re-index
    $action = 'removed';
    $message = 'Produk dihapus dari favorit.';
} else {
    $_SESSION['wishlist'][] = $productId;
    $action = 'added';
    $message = 'Produk ditambahkan ke favorit.';
    
    // Create notifications entry
    if (!isset($_SESSION['notifications'])) {
        $_SESSION['notifications'] = [];
    }
    $_SESSION['notifications'][] = [
        'title' => 'Favorit Baru',
        'message' => sanitizeOutput($product['name']) . ' ditambahkan ke favorit Anda.',
        'time' => date('H:i'),
        'unread' => true
    ];
}

echo json_encode([
    'success' => true,
    'action' => $action,
    'message' => $message,
    'count' => count($_SESSION['wishlist'])
]);
exit;
