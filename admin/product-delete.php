<?php
/**
 * Admin Product Delete Action
 * Handles product deletion including image cleanup.
 * Only accepts POST requests with valid CSRF token.
 */

session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/admin-auth.php';

// Require admin authentication
requireAdmin();

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('products', 'Permintaan tidak valid', 'error');
}

// Validate CSRF token
$csrfToken = $_POST['csrf_token'] ?? '';
if (!validateCSRFToken($csrfToken)) {
    redirect('products', 'Permintaan tidak valid, silakan coba lagi', 'error');
}

// Get product ID from POST
$productId = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;

if ($productId <= 0) {
    redirect('products', 'Produk tidak ditemukan', 'error');
}

$pdo = getDBConnection();

// Fetch product to get image filename
try {
    $stmt = $pdo->prepare("SELECT id, image FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
} catch (PDOException $e) {
    error_log('Error fetching product for deletion: ' . $e->getMessage());
    redirect('products', 'Terjadi kesalahan, silakan coba lagi', 'error');
}

// If product not found, redirect with error
if (!$product) {
    redirect('products', 'Produk tidak ditemukan', 'error');
}

// Delete associated image file if exists
if (!empty($product['image'])) {
    $uploadDir = __DIR__ . '/../uploads/products';
    deleteImage($product['image'], $uploadDir);
}

// Delete product record from database
try {
    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([$productId]);
} catch (PDOException $e) {
    error_log('Error deleting product: ' . $e->getMessage());
    redirect('products', 'Gagal menghapus produk, silakan coba lagi', 'error');
}

// Flash success message and redirect to product list
redirect('products', 'Produk berhasil dihapus', 'success');
