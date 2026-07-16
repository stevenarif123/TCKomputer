<?php
// Use explicit 127.0.0.1 to force TCP instead of socket on Windows CLI
$pdo = new PDO('mysql:host=127.0.0.1;dbname=u496707900_steven_it_shop;charset=utf8mb4', 'u496707900_steven_it_shop', 'Steven_it_shop1', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

// Find products where image is empty or null, or points to placeholder
$stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE image IS NULL OR image = '' OR image = 'placeholder.jpg'");
$count = $stmt->fetchColumn();

if ($count > 0) {
    echo "Ditemukan $count produk tanpa gambar. Menghapus...\n";
    $pdo->exec("DELETE FROM products WHERE image IS NULL OR image = '' OR image = 'placeholder.jpg'");
    echo "Penghapusan berhasil.\n";
} else {
    echo "Tidak ada produk tanpa gambar yang ditemukan.\n";
}
