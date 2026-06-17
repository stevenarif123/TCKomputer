<?php
/**
 * Cart Remove Action - Steven IT Shop
 * Removes a product from the session cart.
 * 
 * Expects POST with:
 * - csrf_token: CSRF protection token
 * - product_id: ID of the product to remove
 * 
 * Requirements: 3.8, 14.2
 */

session_start();
require_once __DIR__ . '/../config/helpers.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../cart', 'Permintaan tidak valid, silakan coba lagi', 'error');
}

// Validate CSRF token
$csrfToken = $_POST['csrf_token'] ?? '';
if (!validateCSRFToken($csrfToken)) {
    redirect('../cart', 'Permintaan tidak valid, silakan coba lagi', 'error');
}

// Get product ID from POST
$productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;

// Remove item from session cart
if ($productId > 0 && isset($_SESSION['cart'][$productId])) {
    $productName = $_SESSION['cart'][$productId]['name'] ?? 'Produk';
    unset($_SESSION['cart'][$productId]);
    
    if (!isset($_SESSION['notifications'])) {
        $_SESSION['notifications'] = [];
    }
    $_SESSION['notifications'][] = [
        'title' => 'Keranjang Belanja',
        'message' => $productName . ' dihapus dari keranjang.',
        'time' => date('H:i'),
        'unread' => true
    ];
    
    redirect('../cart', 'Produk dihapus dari keranjang', 'success');
} else {
    redirect('../cart', 'Produk tidak ditemukan di keranjang', 'warning');
}
