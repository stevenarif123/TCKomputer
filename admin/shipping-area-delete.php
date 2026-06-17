<?php
/**
 * Admin Shipping Area Delete Action
 * Handles shipping area deletion.
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
    redirect('shipping-areas', 'Permintaan tidak valid', 'error');
}

// Validate CSRF token
$csrfToken = $_POST['csrf_token'] ?? '';
if (!validateCSRFToken($csrfToken)) {
    redirect('shipping-areas', 'Permintaan tidak valid, silakan coba lagi', 'error');
}

// Get shipping area ID from POST
$shippingAreaId = isset($_POST['shipping_area_id']) ? (int) $_POST['shipping_area_id'] : 0;

if ($shippingAreaId <= 0) {
    redirect('shipping-areas', 'Area pengiriman tidak ditemukan', 'error');
}

$pdo = getDBConnection();

// Delete shipping area record from database
try {
    $stmt = $pdo->prepare("DELETE FROM shipping_areas WHERE id = ?");
    $stmt->execute([$shippingAreaId]);

    if ($stmt->rowCount() === 0) {
        redirect('shipping-areas', 'Area pengiriman tidak ditemukan', 'error');
    }
} catch (PDOException $e) {
    error_log('Error deleting shipping area: ' . $e->getMessage());
    redirect('shipping-areas', 'Gagal menghapus area pengiriman, silakan coba lagi', 'error');
}

// Flash success message and redirect to shipping areas list
redirect('shipping-areas', 'Area pengiriman berhasil dihapus', 'success');
