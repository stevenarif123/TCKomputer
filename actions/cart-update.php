<?php
/**
 * Cart Update Action - Steven IT Shop
 * Updates the quantity of a product in the session cart.
 * Validates CSRF token, quantity bounds, and stock limits for Ready products.
 *
 * Requirements: 3.6, 3.7, 14.2
 */

session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../cart', 'Metode tidak valid', 'error');
}

// Validate CSRF token
$csrfToken = $_POST['csrf_token'] ?? '';
if (!validateCSRFToken($csrfToken)) {
    redirect('../cart', 'Permintaan tidak valid, silakan coba lagi', 'error');
}

// Get product_id and quantity from POST
$productId = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
$quantity = isset($_POST['quantity']) ? (int) $_POST['quantity'] : 0;

// Validate quantity >= 1
if ($quantity < 1) {
    redirect('../cart', 'Jumlah minimal 1', 'error');
}

// Check if product is in cart
if (!isset($_SESSION['cart'][$productId])) {
    redirect('../cart', 'Produk tidak ditemukan di keranjang', 'error');
}

// Query product for stock and status
$pdo = getDBConnection();
$stmt = $pdo->prepare(
    "SELECT id, stock, status FROM products WHERE id = ? AND is_active = 1"
);
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    // Product no longer exists or is inactive, remove from cart
    unset($_SESSION['cart'][$productId]);
    redirect('../cart', 'Produk tidak lagi tersedia', 'error');
}

// For Ready products: cap quantity at available stock
if ($product['status'] === 'ready') {
    if ($quantity > (int) $product['stock']) {
        $quantity = (int) $product['stock'];
        $_SESSION['cart'][$productId]['quantity'] = $quantity;
        redirect('../cart', 'Jumlah disesuaikan dengan stok tersedia (' . $quantity . ')', 'warning');
    }
}

// For PO products: no stock limit, any quantity >= 1 is allowed

// Update session cart quantity
$_SESSION['cart'][$productId]['quantity'] = $quantity;

if (!isset($_SESSION['notifications'])) {
    $_SESSION['notifications'] = [];
}
$_SESSION['notifications'][] = [
    'title' => 'Keranjang Belanja',
    'message' => $_SESSION['cart'][$productId]['name'] . ' jumlah diperbarui ke ' . $quantity . '.',
    'time' => date('H:i'),
    'unread' => true
];

// Flash success message and redirect to cart
redirect('../cart', 'Keranjang diperbarui', 'success');
