<?php
/**
 * Database Migration: Populate Tana Toraja and Toraja Utara Districts (Kecamatan)
 * Run this file in your browser or command line to update the shipping areas.
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';

try {
    $pdo = getDBConnection();
    
    // 1. Ensure 'regency' column exists in shipping_areas
    $areasCols = $pdo->query("SHOW COLUMNS FROM shipping_areas")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('regency', $areasCols, true)) {
        $pdo->exec("ALTER TABLE shipping_areas ADD COLUMN regency VARCHAR(100) NOT NULL DEFAULT 'Tana Toraja' AFTER area_name");
        echo "Kolom 'regency' berhasil ditambahkan ke tabel shipping_areas.\n";
    }
    
    // 2. Mark all existing shipping areas as inactive to preserve past orders
    $pdo->exec("UPDATE shipping_areas SET is_active = 0");
    echo "Wilayah pengiriman lama berhasil dinonaktifkan (untuk menjaga riwayat pesanan).\n";
    
    // 3. Define the list of all 30 districts with their respective regencies and costs
    $districts = [
        // === TANA TORAJA (17 Kecamatan) ===
        // Zona A (Gratis)
        ['area_name' => 'Makale', 'regency' => 'Tana Toraja', 'cost' => 0],
        ['area_name' => 'Makale Utara', 'regency' => 'Tana Toraja', 'cost' => 0],
        ['area_name' => 'Makale Selatan', 'regency' => 'Tana Toraja', 'cost' => 0],
        
        // Zona B (Rp10.000)
        ['area_name' => 'Sangalla', 'regency' => 'Tana Toraja', 'cost' => 10000],
        ['area_name' => 'Sangalla Selatan', 'regency' => 'Tana Toraja', 'cost' => 10000],
        ['area_name' => 'Sangalla Utara', 'regency' => 'Tana Toraja', 'cost' => 10000],
        ['area_name' => 'Rantetayo', 'regency' => 'Tana Toraja', 'cost' => 10000],
        ['area_name' => 'Mengkendek', 'regency' => 'Tana Toraja', 'cost' => 10000],
        ['area_name' => 'Gandangbatu Sillanan', 'regency' => 'Tana Toraja', 'cost' => 10000],
        
        // Zona C (Rp20.000)
        ['area_name' => 'Kurra', 'regency' => 'Tana Toraja', 'cost' => 20000],
        ['area_name' => 'Malimbong Balepe', 'regency' => 'Tana Toraja', 'cost' => 20000],
        
        // Zona D (Rp30.000)
        ['area_name' => 'Rembon', 'regency' => 'Tana Toraja', 'cost' => 30000],
        ['area_name' => 'Bittuang', 'regency' => 'Tana Toraja', 'cost' => 30000],
        ['area_name' => 'Masanda', 'regency' => 'Tana Toraja', 'cost' => 30000],
        ['area_name' => 'Bonggakaradeng', 'regency' => 'Tana Toraja', 'cost' => 30000],
        ['area_name' => 'Simbuang', 'regency' => 'Tana Toraja', 'cost' => 30000],
        ['area_name' => 'Mappak', 'regency' => 'Tana Toraja', 'cost' => 30000],

        // === TORAJA UTARA (13 Kecamatan) ===
        // Zona C (Rp20.000)
        ['area_name' => 'Rantepao', 'regency' => 'Toraja Utara', 'cost' => 20000],
        ['area_name' => 'Tallunglipu', 'regency' => 'Toraja Utara', 'cost' => 20000],
        ['area_name' => 'Kesu', 'regency' => 'Toraja Utara', 'cost' => 20000],
        
        // Zona D (Rp30.000)
        ['area_name' => 'Sanggalangi', 'regency' => 'Toraja Utara', 'cost' => 30000],
        ['area_name' => 'Tikala', 'regency' => 'Toraja Utara', 'cost' => 30000],
        ['area_name' => 'Tondon', 'regency' => 'Toraja Utara', 'cost' => 30000],
        ['area_name' => 'Sopai', 'regency' => 'Toraja Utara', 'cost' => 30000],
        ['area_name' => 'Sa\'dan', 'regency' => 'Toraja Utara', 'cost' => 30000],
        ['area_name' => 'Nanggala', 'regency' => 'Toraja Utara', 'cost' => 30000],
        ['area_name' => 'Buntao', 'regency' => 'Toraja Utara', 'cost' => 30000],
        ['area_name' => 'Rindingallo', 'regency' => 'Toraja Utara', 'cost' => 30000],
        ['area_name' => 'Kapala Pitu', 'regency' => 'Toraja Utara', 'cost' => 30000],
        ['area_name' => 'Baruppu', 'regency' => 'Toraja Utara', 'cost' => 30000]
    ];
    
    // 4. Insert each district into the database
    $stmt = $pdo->prepare("INSERT INTO shipping_areas (area_name, regency, cost, is_active) VALUES (?, ?, ?, 1)");
    
    foreach ($districts as $district) {
        $stmt->execute([
            $district['area_name'],
            $district['regency'],
            $district['cost']
        ]);
        echo "Berhasil memasukkan: {$district['area_name']} ({$district['regency']}) - Rp " . number_format($district['cost'], 0, ',', '.') . "\n";
    }
    
    echo "Migrasi selesai! Semua kecamatan berhasil dimuat.\n";
} catch (Exception $e) {
    echo "Migrasi gagal: " . $e->getMessage() . "\n";
}
