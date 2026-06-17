<?php
/**
 * Cart Checkout Preparation - TC Komputer
 * Receives selected items from cart.php and saves them into a session
 * variable before redirecting to checkout.php.
 */

session_start();
require_once __DIR__ . '/../config/helpers.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../cart', 'Metode tidak valid', 'error');
}

// Validate CSRF
$csrfToken = $_POST['csrf_token'] ?? '';
if (!validateCSRFToken($csrfToken)) {
    redirect('../cart', 'Sesi telah kadaluarsa, silakan coba lagi', 'error');
}

$selectedItems = $_POST['selected_items'] ?? [];

if (empty($selectedItems) || !is_array($selectedItems)) {
    redirect('../cart', 'Pilih setidaknya satu produk untuk di-checkout', 'warning');
}

// Ensure items are integers and filter out any invalid ones
$validIds = [];
foreach ($selectedItems as $id) {
    $id = (int)$id;
    if ($id > 0 && isset($_SESSION['cart'][$id])) {
        $validIds[] = $id;
    }
}

if (empty($validIds)) {
    redirect('../cart', 'Produk yang dipilih tidak valid atau tidak ada di keranjang', 'error');
}

// Store the valid selected items into session
$_SESSION['checkout_items'] = $validIds;

// Redirect to checkout
header("Location: ../checkout");
exit;
