<?php
/**
 * Script untuk memasukkan data dummy promosi agar bisa langsung ditest.
 * Silakan akses di browser: namadomain.com/seed_promotions.php
 */

require_once __DIR__ . '/config/db.php';

$pdo = getDBConnection();
$messages = [];

try {
    // Pastikan tabel promotions kosong sebelum diisi agar tidak duplicate
    $pdo->exec("DELETE FROM promotions");
    $pdo->exec("ALTER TABLE promotions AUTO_INCREMENT = 1");

    $pdo->beginTransaction();

    // Ambil satu kategori dan satu produk secara acak untuk dijadikan target diskon kategori dan free item
    $stmtCategory = $pdo->query("SELECT id FROM categories LIMIT 1");
    $category = $stmtCategory->fetch();
    $targetCategoryId = $category ? $category['id'] : null;

    $stmtProduct = $pdo->query("SELECT id FROM products WHERE status = 'ready' AND stock > 0 LIMIT 1");
    $product = $stmtProduct->fetch();
    $freeItemId = $product ? $product['id'] : null;

    $now = date('Y-m-d H:i:s');
    $nextMonth = date('Y-m-d H:i:s', strtotime('+1 month'));

    // 1. Promo Gratis Ongkir (Min. belanja Rp 5.000.000, max diskon ongkir Rp 50.000)
    $stmt = $pdo->prepare("INSERT INTO promotions (name, description, promo_type, discount_type, discount_value, min_spend, start_date, end_date) VALUES (?, ?, 'free_shipping', 'fixed', ?, ?, ?, ?)");
    $stmt->execute(['Gratis Ongkir Sultan', 'Gratis ongkos kirim hingga Rp 50.000 untuk belanja di atas 5 Juta.', 50000, 5000000, $now, $nextMonth]);

    // 2. Diskon Keranjang (Potongan Rp 100.000 untuk belanja min Rp 10.000.000)
    $stmt = $pdo->prepare("INSERT INTO promotions (name, description, promo_type, discount_type, discount_value, min_spend, start_date, end_date) VALUES (?, ?, 'cart_discount', 'fixed', ?, ?, ?, ?)");
    $stmt->execute(['Diskon Ekstra 100 Ribu', 'Potongan langsung 100rb untuk belanja super besar!', 100000, 10000000, $now, $nextMonth]);

    // 3. Diskon Kategori 10% (Hanya jika ada kategori)
    if ($targetCategoryId) {
        $stmt = $pdo->prepare("INSERT INTO promotions (name, description, promo_type, discount_type, discount_value, target_category_id, start_date, end_date) VALUES (?, ?, 'category_discount', 'percentage', ?, ?, ?, ?)");
        $stmt->execute(['Diskon Kategori Spesial 10%', 'Diskon 10% khusus untuk produk pada kategori ini.', 10, $targetCategoryId, $now, $nextMonth]);
    }

    // 4. Gratis Item (Belanja min Rp 15.000.000 dapat item gratis, hanya jika ada produk)
    if ($freeItemId) {
        $stmt = $pdo->prepare("INSERT INTO promotions (name, description, promo_type, discount_type, discount_value, min_spend, free_item_id, start_date, end_date) VALUES (?, ?, 'free_item', 'fixed', 0, ?, ?, ?, ?)");
        $stmt->execute(['Bonus Hadiah Langsung', 'Dapatkan hadiah eksklusif gratis untuk pembelian di atas 15 Juta!', 15000000, $freeItemId, $now, $nextMonth]);
    }

    $pdo->commit();
    $messages[] = "Sukses! Data dummy promosi berhasil dimasukkan.";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $messages[] = "ERROR: " . $e->getMessage();
}

// Display results
echo "<h1>Input Dummy Promotions</h1><ul>";
foreach ($messages as $msg) {
    $color = strpos($msg, 'ERROR') !== false ? 'red' : 'green';
    echo "<li style='color: $color;'>$msg</li>";
}
echo "</ul><br><p><strong>Sekarang coba akses halaman Keranjang dan lihat bagaimana diskon ini bekerja otomatis saat Anda menambahkan produk! (Hapus file ini bila sudah tidak diperlukan)</strong></p>";
