<?php
/**
 * Export Products to CSV
 * Fetches all products and outputs them as a downloadable CSV file.
 * Protected by admin authentication.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/admin-auth.php';

// Protect admin access
requireAdmin();

$pdo = getDBConnection();

// Fetch all products
try {
    $stmt = $pdo->query("
        SELECT p.*, c.name AS category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        ORDER BY p.name ASC
    ");
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Error fetching products for export: ' . $e->getMessage());
    die('Gagal menarik data produk.');
}

// Set CSV headers for download
$filename = 'products_export_' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Open the output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for proper Excel encoding
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add column headers
fputcsv($output, [
    'ID', 
    'Nama Produk', 
    'SKU', 
    'Kategori', 
    'Brand', 
    'Model', 
    'Harga Beli (Rp)', 
    'Harga Jual (Rp)', 
    'Harga Promo (Rp)', 
    'Promo Aktif', 
    'Stok Promo',
    'Stok Fisik', 
    'Status', 
    'Kondisi', 
    'Garansi',
    'Featured', 
    'Aktif'
]);

// Write product rows
foreach ($products as $p) {
    fputcsv($output, [
        $p['id'],
        $p['name'],
        $p['sku'] ?: '-',
        $p['category_name'] ?: '-',
        $p['brand'] ?: '-',
        $p['model'] ?: '-',
        (int)$p['purchase_price'],
        (int)$p['selling_price'],
        $p['promo_price'] !== null ? (int)$p['promo_price'] : '-',
        $p['promo_active'] ? 'Ya' : 'Tidak',
        (int)$p['promo_stock'],
        (int)$p['stock'],
        strtoupper($p['status']),
        $p['condition_type'] === 'new' ? 'Baru' : 'Bekas',
        $p['warranty_note'] ?: '-',
        $p['is_featured'] ? 'Ya' : 'Tidak',
        $p['is_active'] ? 'Ya' : 'Tidak'
    ]);
}

fclose($output);
exit;
