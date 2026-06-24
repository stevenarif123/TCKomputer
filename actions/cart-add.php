<?php
/**
 * Cart Add Action
 * Handles adding products to the session-based shopping cart.
 * 
 * Validates CSRF token, product existence/availability, and stock limits.
 * Redirects back with flash message feedback.
 */

session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../index', 'Metode tidak diizinkan', 'error');
}

// Validate CSRF token
$token = $_POST['csrf_token'] ?? '';
if (!validateCSRFToken($token)) {
    safeRedirect(
        $_SERVER['HTTP_REFERER'] ?? null,
        'index',
        'Permintaan tidak valid, silakan coba lagi',
        'error'
    );
}

// Get and sanitize input
$productId = (int) ($_POST['product_id'] ?? 0);
$quantity = (int) ($_POST['quantity'] ?? 1);

// Ensure quantity is at least 1
if ($quantity < 1) {
    $quantity = 1;
}

// Get database connection
$pdo = getDBConnection();

// Query product to verify it exists and is purchasable
$stmt = $pdo->prepare(
    "SELECT id, name, selling_price, promo_price, promo_active, promo_stock, stock, status, image, is_active 
     FROM products WHERE id = ? AND is_active = 1"
);
$stmt->execute([$productId]);
$product = $stmt->fetch();

// Product not found or inactive
if (!$product) {
    safeRedirect(
        $_SERVER['HTTP_REFERER'] ?? null,
        'index',
        'Produk tidak ditemukan',
        'error'
    );
}

// Product sold out (habis)
if ($product['status'] === 'habis') {
    safeRedirect(
        $_SERVER['HTTP_REFERER'] ?? null,
        'index',
        'Produk tidak tersedia',
        'error'
    );
}

// Ready product with zero stock
if ($product['status'] === 'ready' && $product['stock'] <= 0) {
    safeRedirect(
        $_SERVER['HTTP_REFERER'] ?? null,
        'index',
        'Stok habis',
        'error'
    );
}

// Initialize cart if needed
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Add to cart or increment existing quantity
$redirectUrl = $_SERVER['HTTP_REFERER'] ?? 'product-detail?id=' . $productId;

if (isset($_POST['buy_now']) && $_POST['buy_now'] == 1) {
    $_SESSION['checkout_items'] = [$productId];
    $redirectUrl = '../cart?buy_now=1';
}

if (isset($_SESSION['cart'][$productId])) {
    // Increment existing quantity
    $newQty = $_SESSION['cart'][$productId]['quantity'] + $quantity;

    // For ready products, cap at available stock
    if ($product['status'] === 'ready' && $newQty > $product['stock']) {
        $newQty = $product['stock'];
        $_SESSION['cart'][$productId]['quantity'] = $newQty;
        redirect($redirectUrl, 'Jumlah disesuaikan dengan stok tersedia', 'warning');
    }

    $_SESSION['cart'][$productId]['quantity'] = $newQty;
} else {
    // Cap initial quantity for ready products
    $cappedQuantity = $quantity;
    if ($product['status'] === 'ready' && $quantity > $product['stock']) {
        $cappedQuantity = $product['stock'];
    }

    // Fetch flash sale state
    $stmtFs = $pdo->query("SELECT flash_sale_active, flash_sale_end FROM store_settings LIMIT 1");
    $fsSettings = $stmtFs->fetch();
    $fsSeconds = 0;
    if (!empty($fsSettings['flash_sale_end'])) {
        $endTime = strtotime($fsSettings['flash_sale_end']);
        if ($endTime > time()) {
            $fsSeconds = $endTime - time();
        }
    }
    $isGlobalFlashSaleActive = !empty($fsSettings['flash_sale_active']) && $fsSeconds > 0;

    $isPromo = $isGlobalFlashSaleActive && !empty($product['promo_active']) && !empty($product['promo_price']) && $product['promo_price'] > 0 && isset($product['promo_stock']) && $product['promo_stock'] > 0;
    $activePrice = $isPromo ? (int)$product['promo_price'] : (int)$product['selling_price'];

    $_SESSION['cart'][$productId] = [
        'quantity' => $cappedQuantity,
        'name' => $product['name'],
        'price' => $activePrice,
        'image' => $product['image'],
    ];

    // If quantity was capped, show warning
    if ($cappedQuantity < $quantity) {
        redirect($redirectUrl, 'Jumlah disesuaikan dengan stok tersedia', 'warning');
    }
}

// Success
if (!isset($_SESSION['notifications'])) {
    $_SESSION['notifications'] = [];
}
$_SESSION['notifications'][] = [
    'title' => 'Keranjang Belanja',
    'message' => $product['name'] . ' berhasil ditambahkan.',
    'time' => date('H:i'),
    'unread' => true
];

redirect($redirectUrl, 'Produk ditambahkan ke keranjang', 'success');
