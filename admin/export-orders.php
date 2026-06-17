<?php
/**
 * Export Orders to CSV
 * Fetches all orders and outputs them as a downloadable CSV file.
 * Protected by admin authentication.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/admin-auth.php';

// Protect admin access
requireAdmin();

$pdo = getDBConnection();

// Fetch all orders
try {
    $stmt = $pdo->query("
        SELECT o.*, sa.area_name AS shipping_area_name, sa.regency AS shipping_regency 
        FROM orders o 
        LEFT JOIN shipping_areas sa ON o.shipping_area_id = sa.id 
        ORDER BY o.created_at DESC
    ");
    $orders = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Error fetching orders for export: ' . $e->getMessage());
    die('Gagal menarik data pesanan.');
}

// Set CSV headers for download
$filename = 'orders_export_' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Open the output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for proper Excel encoding
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add column headers
fputcsv($output, [
    'ID Pesanan', 
    'Kode Pesanan', 
    'Nama Pembeli', 
    'No. Telepon', 
    'Alamat Pembeli', 
    'Area Pengiriman', 
    'Kabupaten/Kota', 
    'Opsi Pengiriman', 
    'Metode Pembayaran', 
    'Status Pembayaran', 
    'Status Pesanan', 
    'Subtotal (Rp)', 
    'Ongkos Kirim (Rp)', 
    'Total (Rp)', 
    'Catatan Pembeli', 
    'Catatan Admin', 
    'Tanggal Dibuat',
    'Tanggal Diupdate'
]);

// Helper maps
$shippingOptions = [
    'self_pickup' => 'Ambil di Toko',
    'local_delivery' => 'Pengiriman Lokal',
    'local_courier' => 'Kurir Lokal',
];

$paymentMethods = [
    'cod' => 'COD (Bayar di Tempat)',
    'transfer' => 'Transfer Bank',
    'pay_on_delivery' => 'Bayar Saat Diterima',
];

$paymentStatuses = [
    'belum_dibayar' => 'Belum Dibayar',
    'menunggu_konfirmasi' => 'Menunggu Konfirmasi',
    'sudah_dibayar' => 'Sudah Dibayar',
    'cod' => 'COD',
];

$orderStatuses = [
    'menunggu_konfirmasi' => 'Menunggu Konfirmasi',
    'diproses' => 'Diproses',
    'siap_diantar' => 'Siap Diantar',
    'dikirim' => 'Dikirim',
    'selesai' => 'Selesai',
    'dibatalkan' => 'Dibatalkan',
];

// Write order rows
foreach ($orders as $o) {
    fputcsv($output, [
        $o['id'],
        $o['order_code'],
        $o['buyer_name'],
        $o['buyer_phone'] ?: '-',
        $o['buyer_address'] ?: '-',
        $o['shipping_area_name'] ?: '-',
        $o['shipping_regency'] ?: '-',
        $shippingOptions[$o['shipping_option']] ?? $o['shipping_option'],
        $paymentMethods[$o['payment_method']] ?? $o['payment_method'],
        $paymentStatuses[$o['payment_status']] ?? $o['payment_status'],
        $orderStatuses[$o['order_status']] ?? $o['order_status'],
        (int)$o['subtotal'],
        (int)$o['shipping_cost'],
        (int)$o['total'],
        $o['order_notes'] ?: '-',
        $o['admin_notes'] ?: '-',
        $o['created_at'],
        $o['updated_at']
    ]);
}

fclose($output);
exit;
