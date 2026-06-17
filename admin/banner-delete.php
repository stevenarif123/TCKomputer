<?php
/**
 * Admin Banner Delete Action
 * Handles banner deletion including image cleanup.
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
    redirect('banners', 'Permintaan tidak valid', 'error');
}

// Validate CSRF token
$csrfToken = $_POST['csrf_token'] ?? '';
if (!validateCSRFToken($csrfToken)) {
    redirect('banners', 'Permintaan tidak valid, silakan coba lagi', 'error');
}

// Get banner ID from POST
$bannerId = isset($_POST['banner_id']) ? (int) $_POST['banner_id'] : 0;

if ($bannerId <= 0) {
    redirect('banners', 'Banner tidak ditemukan', 'error');
}

$pdo = getDBConnection();

// Fetch banner to get image filename
try {
    $stmt = $pdo->prepare("SELECT id, image FROM banners WHERE id = ?");
    $stmt->execute([$bannerId]);
    $banner = $stmt->fetch();
} catch (PDOException $e) {
    error_log('Error fetching banner for deletion: ' . $e->getMessage());
    redirect('banners', 'Terjadi kesalahan, silakan coba lagi', 'error');
}

// If banner not found, redirect with error
if (!$banner) {
    redirect('banners', 'Banner tidak ditemukan', 'error');
}

// Delete associated image file if exists
if (!empty($banner['image'])) {
    $uploadDir = __DIR__ . '/../uploads/banners';
    deleteImage($banner['image'], $uploadDir);
}

// Delete banner record from database
try {
    $stmt = $pdo->prepare("DELETE FROM banners WHERE id = ?");
    $stmt->execute([$bannerId]);
} catch (PDOException $e) {
    error_log('Error deleting banner: ' . $e->getMessage());
    redirect('banners', 'Gagal menghapus banner, silakan coba lagi', 'error');
}

// Flash success message and redirect to banner list
redirect('banners', 'Banner berhasil dihapus', 'success');
