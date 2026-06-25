<?php
/**
 * Clean Orphaned Order Items
 * Script ini untuk menghapus baris "barang hantu" dari order_items
 * yang sudah tidak memiliki order_id di tabel orders.
 */
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/admin-auth.php';

requireAdmin();

$pdo = getDBConnection();

try {
    $pdo->beginTransaction();
    
    // Cari jumlah yatim piatu
    $stmtCheck = $pdo->query("SELECT COUNT(*) FROM order_items WHERE order_id NOT IN (SELECT id FROM orders)");
    $orphanCount = $stmtCheck->fetchColumn();
    
    if ($orphanCount > 0) {
        // Hapus barang hantu
        $pdo->exec("DELETE FROM order_items WHERE order_id NOT IN (SELECT id FROM orders)");
        $pdo->commit();
        $message = "Berhasil menghapus $orphanCount barang hantu dari database!";
        $status = "success";
    } else {
        $pdo->rollBack();
        $message = "Tidak ditemukan barang hantu. Database sudah bersih.";
        $status = "success";
    }
} catch (Exception $e) {
    $pdo->rollBack();
    $message = "Terjadi kesalahan: " . $e->getMessage();
    $status = "error";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Pembersihan Database - TCKomputer</title>
    <style>
        body { font-family: sans-serif; background: #f3f4f6; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .card { background: #fff; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); text-align: center; max-width: 500px; }
        .btn { background: #3b82f6; color: #fff; border: none; padding: 10px 20px; font-size: 16px; border-radius: 4px; cursor: pointer; font-weight: bold; margin-top: 20px; text-decoration: none; display: inline-block; }
        .btn:hover { background: #2563eb; }
        .msg { padding: 15px; margin-bottom: 20px; border-radius: 4px; font-weight: bold; }
        .msg.success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .msg.error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .back { display: block; margin-top: 20px; color: #6b7280; text-decoration: none; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Hasil Pembersihan Database</h2>
        
        <div class="msg <?= $status ?>">
            <?= htmlspecialchars($message) ?>
        </div>
        
        <p>Barang hantu terjadi karena sisa data pesanan lama yang gagal terhapus di database.</p>
        
        <a href="orders" class="btn">Kembali ke Kelola Pesanan</a>
    </div>
</body>
</html>
