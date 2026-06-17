<?php
/**
 * Admin Category Delete Action
 * Handles category deletion with product assignment check and image cleanup.
 * Only accepts POST requests with valid CSRF token.
 * Rejects deletion if the category still has assigned products.
 */

session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/admin-auth.php';

// Require admin authentication
requireAdmin();

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('categories', 'Permintaan tidak valid', 'error');
}

// Validate CSRF token
$csrfToken = $_POST['csrf_token'] ?? '';
if (!validateCSRFToken($csrfToken)) {
    redirect('categories', 'Permintaan tidak valid, silakan coba lagi', 'error');
}

// Get category ID from POST
$categoryId = isset($_POST['category_id']) ? (int) $_POST['category_id'] : 0;

if ($categoryId <= 0) {
    redirect('categories', 'Kategori tidak ditemukan', 'error');
}

$pdo = getDBConnection();

// Fetch category to verify it exists and get image filename
try {
    $stmt = $pdo->prepare("SELECT id, image FROM categories WHERE id = ?");
    $stmt->execute([$categoryId]);
    $category = $stmt->fetch();
} catch (PDOException $e) {
    error_log('Error fetching category for deletion: ' . $e->getMessage());
    redirect('categories', 'Terjadi kesalahan, silakan coba lagi', 'error');
}

// If category not found, redirect with error
if (!$category) {
    redirect('categories', 'Kategori tidak ditemukan', 'error');
}

// Check if category has assigned products
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
    $stmt->execute([$categoryId]);
    $productCount = (int) $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log('Error checking category products: ' . $e->getMessage());
    redirect('categories', 'Terjadi kesalahan, silakan coba lagi', 'error');
}

// Reject deletion if products exist
if ($productCount > 0) {
    redirect('categories', 'Kategori masih memiliki produk, tidak dapat dihapus', 'error');
}

// Delete associated image file if exists
if (!empty($category['image'])) {
    $uploadDir = __DIR__ . '/../uploads/categories';
    deleteImage($category['image'], $uploadDir);
}

// Delete category record from database
try {
    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->execute([$categoryId]);
} catch (PDOException $e) {
    error_log('Error deleting category: ' . $e->getMessage());
    redirect('categories', 'Gagal menghapus kategori, silakan coba lagi', 'error');
}

// Flash success message and redirect to category list
redirect('categories', 'Kategori berhasil dihapus', 'success');
