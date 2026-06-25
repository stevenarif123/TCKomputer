<?php
/**
 * Reset Demo Data
 * This script will completely truncate (empty) the orders and order_items tables
 * and reset their auto-increment IDs to 1.
 * Useful for preparing a clean demo environment.
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/admin-auth.php';

requireAdmin();

$pdo = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_demo'])) {
    try {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        $pdo->exec("TRUNCATE TABLE order_items");
        $pdo->exec("TRUNCATE TABLE orders");
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        $message = "Semua data pesanan berhasil dikosongkan. Sistem siap untuk demo.";
        $status = "success";
    } catch (Exception $e) {
        $message = "Terjadi kesalahan: " . $e->getMessage();
        $status = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Reset Data Demo - TCKomputer</title>
    <style>
        body { font-family: sans-serif; background: #f3f4f6; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .card { background: #fff; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); text-align: center; max-width: 400px; }
        .btn { background: #ef4444; color: #fff; border: none; padding: 10px 20px; font-size: 16px; border-radius: 4px; cursor: pointer; font-weight: bold; margin-top: 20px; }
        .btn:hover { background: #dc2626; }
        .msg { padding: 10px; margin-bottom: 20px; border-radius: 4px; }
        .msg.success { background: #dcfce7; color: #166534; }
        .msg.error { background: #fee2e2; color: #991b1b; }
        .back { display: block; margin-top: 15px; color: #3b82f6; text-decoration: none; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Reset Data Pesanan (Demo)</h2>
        <p>Aksi ini akan <strong>menghapus semua pesanan</strong> secara permanen dan mereset nomor urut ID pesanan kembali ke 1. Lakukan ini jika Anda ingin membuat video demo yang bersih dari pesanan sebelumnya atau barang hantu.</p>
        
        <?php if (isset($message)): ?>
            <div class="msg <?= $status ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <form method="POST" onsubmit="return confirm('PERINGATAN: SEMUA PESANAN AKAN DIHAPUS. Anda yakin?');">
            <button type="submit" name="reset_demo" value="1" class="btn">Kosongkan Semua Pesanan</button>
        </form>
        
        <a href="orders" class="back">&laquo; Kembali ke Daftar Pesanan</a>
    </div>
</body>
</html>
