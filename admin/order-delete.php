<?php
/**
 * Admin Order Delete Handler
 * Processes POST requests to completely delete an order from the database.
 * Validates CSRF token and requires admin authentication.
 */

// Start session and include required files
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/admin-auth.php';

// Require admin authentication
requireAdmin();

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('orders', 'Metode request tidak valid', 'error');
}

// Get order ID from POST
$orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;

if ($orderId <= 0) {
    redirect('orders', 'ID pesanan tidak valid', 'error');
}

// Validate CSRF token
$csrfToken = $_POST['csrf_token'] ?? '';
if (!validateCSRFToken($csrfToken)) {
    redirect('orders', 'Permintaan tidak valid, silakan coba lagi', 'error');
}

// Get database connection
$pdo = getDBConnection();

// Fetch current order
$stmt = $pdo->prepare("SELECT id, order_code FROM orders WHERE id = ?");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    redirect('orders', 'Pesanan tidak ditemukan', 'error');
}

try {
    $pdo->beginTransaction();

    // Delete order items first (in case there's no ON DELETE CASCADE)
    $stmtDeleteItems = $pdo->prepare("DELETE FROM order_items WHERE order_id = ?");
    $stmtDeleteItems->execute([$orderId]);

    // Delete order
    $stmtDeleteOrder = $pdo->prepare("DELETE FROM orders WHERE id = ?");
    $stmtDeleteOrder->execute([$orderId]);

    $pdo->commit();

    redirect('orders', 'Pesanan ' . sanitizeOutput($order['order_code']) . ' berhasil dihapus permanen.', 'success');
} catch (Exception $e) {
    $pdo->rollBack();
    // Log error in real-world application
    redirect('orders', 'Terjadi kesalahan saat menghapus pesanan.', 'error');
}
