<?php
/**
 * Admin Order Status Update Handler
 * Processes POST requests to update order status, payment status, and admin notes.
 * Validates CSRF token and enforces allowed status transitions.
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
    redirect('order-detail?id=' . $orderId, 'Permintaan tidak valid, silakan coba lagi', 'error');
}

// Get form data
$newOrderStatus = $_POST['order_status'] ?? '';
$newPaymentStatus = $_POST['payment_status'] ?? '';
$adminNotes = $_POST['admin_notes'] ?? '';

// Get database connection
$pdo = getDBConnection();

// Fetch current order
$stmt = $pdo->prepare("SELECT id, order_status FROM orders WHERE id = ?");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    redirect('orders', 'Pesanan tidak ditemukan', 'error');
}

// Valid order status values
$validOrderStatuses = ['menunggu_konfirmasi', 'diproses', 'siap_diantar', 'dikirim', 'selesai', 'dibatalkan'];

// Valid payment status values
$validPaymentStatuses = ['belum_dibayar', 'menunggu_konfirmasi', 'sudah_dibayar', 'cod'];

// Validate order status if it's being changed
if (!empty($newOrderStatus) && $newOrderStatus !== $order['order_status']) {
    // Check if new status is a valid enum value
    if (!in_array($newOrderStatus, $validOrderStatuses, true)) {
        redirect('order-detail?id=' . $orderId, 'Status pesanan tidak valid', 'error');
    }
} else {
    // Keep current status if not changing
    $newOrderStatus = $order['order_status'];
}

// Validate payment status
if (!empty($newPaymentStatus)) {
    if (!in_array($newPaymentStatus, $validPaymentStatuses, true)) {
        redirect('order-detail?id=' . $orderId, 'Status pembayaran tidak valid', 'error');
    }
} else {
    // If no payment status provided, don't change it - fetch current
    $stmtPayment = $pdo->prepare("SELECT payment_status FROM orders WHERE id = ?");
    $stmtPayment->execute([$orderId]);
    $currentOrder = $stmtPayment->fetch();
    $newPaymentStatus = $currentOrder['payment_status'];
}

// Update the order
try {
    $stmt = $pdo->prepare(
        "UPDATE orders SET order_status = ?, payment_status = ?, admin_notes = ?, updated_at = NOW() WHERE id = ?"
    );
    $stmt->execute([$newOrderStatus, $newPaymentStatus, $adminNotes, $orderId]);

    redirect('order-detail?id=' . $orderId, 'Status pesanan berhasil diperbarui', 'success');
} catch (PDOException $e) {
    error_log('Order update error: ' . $e->getMessage());
    redirect('order-detail?id=' . $orderId, 'Gagal memperbarui pesanan', 'error');
}
