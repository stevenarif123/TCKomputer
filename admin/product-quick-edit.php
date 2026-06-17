<?php
/**
 * Product Quick Edit Backend Endpoint
 * Updates a product's price or stock via AJAX.
 * Returns JSON response.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/admin-auth.php';

// Disable error display and set JSON header
header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

try {
    // Authenticate
    if (!isAdminLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $pdo = getDBConnection();

    // Validate CSRF token
    $token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($token)) {
        echo json_encode(['success' => false, 'message' => 'Token CSRF tidak valid.']);
        exit;
    }

    // Parameters
    $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $type = isset($_POST['type']) ? trim($_POST['type']) : '';
    $value = isset($_POST['value']) ? (int)$_POST['value'] : 0;

    if ($productId <= 0 || !in_array($type, ['price', 'stock']) || $value < 0) {
        echo json_encode(['success' => false, 'message' => 'Parameter tidak valid.']);
        exit;
    }

    // Verify product exists
    $stmt = $pdo->prepare("SELECT id, status, stock, promo_active, promo_price, promo_stock FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Produk tidak ditemukan.']);
        exit;
    }

    if ($type === 'price') {
        // Validation: If in active Flash Sale, regular price must be higher than promo price
        if ($product['promo_active'] && $value <= (int)$product['promo_price']) {
            echo json_encode([
                'success' => false, 
                'message' => 'Harga regular baru harus lebih tinggi dari harga promo Flash Sale yang aktif (' . formatRupiah((int)$product['promo_price']) . ').'
            ]);
            exit;
        }

        $stmtUpdate = $pdo->prepare("UPDATE products SET selling_price = ?, updated_at = NOW() WHERE id = ?");
        $stmtUpdate->execute([$value, $productId]);
    } else {
        // Validation: If in active Flash Sale, physical stock must not be lower than promo stock
        if ($product['promo_active'] && $value < (int)$product['promo_stock']) {
            echo json_encode([
                'success' => false, 
                'message' => 'Stok baru tidak boleh kurang dari stok promo Flash Sale yang aktif (' . $product['promo_stock'] . ').'
            ]);
            exit;
        }

        // Auto-update status depending on stock
        $newStatus = $product['status'];
        if ($value === 0) {
            $newStatus = 'habis';
        } else if ($value > 0 && $product['status'] === 'habis') {
            $newStatus = 'ready';
        }

        $stmtUpdate = $pdo->prepare("UPDATE products SET stock = ?, status = ?, updated_at = NOW() WHERE id = ?");
        $stmtUpdate->execute([$value, $newStatus, $productId]);

        // Generate updated status badge html
        $statusBadge = getStockStatusBadge($newStatus, $value);
        
        echo json_encode([
            'success' => true,
            'status_badge' => $statusBadge
        ]);
        exit;
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
