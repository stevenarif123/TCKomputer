<?php
/**
 * Setup Database Constraints (Foreign Keys)
 * Mencegah masalah 'barang hantu' selamanya (bahkan saat hapus manual via phpMyAdmin)
 */
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/admin-auth.php';

requireAdmin();

$pdo = getDBConnection();
$message = '';
$status = '';

try {
    // 1. Bersihkan yatim piatu terlebih dahulu sebelum membuat relasi (kalau masih ada)
    $pdo->exec("DELETE FROM order_items WHERE order_id NOT IN (SELECT id FROM orders)");

    // 2. Cek apakah foreign key constraint sudah ada
    $stmt = $pdo->query("
        SELECT CONSTRAINT_NAME 
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'order_items' 
        AND COLUMN_NAME = 'order_id'
        AND REFERENCED_TABLE_NAME = 'orders'
    ");
    
    $existingConstraint = $stmt->fetchColumn();

    // 3. Jika sudah ada, hapus yang lama (yang mungkin tanpa CASCADE)
    if ($existingConstraint) {
        $pdo->exec("ALTER TABLE order_items DROP FOREIGN KEY `$existingConstraint`");
    }

    // 4. Tambahkan constraint yang baru dengan aturan ON DELETE CASCADE
    $pdo->exec("
        ALTER TABLE order_items
        ADD CONSTRAINT fk_order_items_orders
        FOREIGN KEY (order_id)
        REFERENCES orders(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
    ");

    $message = "Berhasil! Sistem database sekarang terkunci kuat. Jika sebuah pesanan dihapus, semua barang di dalamnya akan otomatis terhapus oleh MySQL.";
    $status = "success";

} catch (PDOException $e) {
    // Jika kolom order_id bukan index, kita harus jadikan index dulu
    if (strpos($e->getMessage(), 'cannot find an index') !== false) {
        try {
            $pdo->exec("ALTER TABLE order_items ADD INDEX (order_id)");
            
            $pdo->exec("
                ALTER TABLE order_items
                ADD CONSTRAINT fk_order_items_orders
                FOREIGN KEY (order_id)
                REFERENCES orders(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE
            ");
            
            $message = "Berhasil! Index ditambahkan dan sistem database sekarang terkunci kuat.";
            $status = "success";
        } catch (Exception $e2) {
            $message = "Gagal membuat relasi: " . $e2->getMessage();
            $status = "error";
        }
    } else {
        $message = "Terjadi kesalahan: " . $e->getMessage();
        $status = "error";
    }
} catch (Exception $e) {
    $message = "Terjadi kesalahan: " . $e->getMessage();
    $status = "error";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kunci Database - TCKomputer</title>
    <style>
        body { font-family: sans-serif; background: #f3f4f6; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .card { background: #fff; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); text-align: center; max-width: 500px; }
        .btn { background: #10b981; color: #fff; border: none; padding: 10px 20px; font-size: 16px; border-radius: 4px; cursor: pointer; font-weight: bold; margin-top: 20px; text-decoration: none; display: inline-block; }
        .btn:hover { background: #059669; }
        .msg { padding: 15px; margin-bottom: 20px; border-radius: 4px; font-weight: bold; line-height: 1.5; }
        .msg.success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .msg.error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .back { display: block; margin-top: 20px; color: #6b7280; text-decoration: none; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Perbaikan Struktur Database</h2>
        
        <div class="msg <?= $status ?>">
            <?= htmlspecialchars($message) ?>
        </div>
        
        <p style="font-size: 14px; color: #4b5563;">Setelah perbaikan ini, Anda boleh menghapus data dari mana saja (Admin Web, maupun phpMyAdmin), dan barang hantu tidak akan pernah muncul lagi.</p>
        
        <a href="../admin/orders" class="btn">Kembali ke Kelola Pesanan</a>
    </div>
</body>
</html>
