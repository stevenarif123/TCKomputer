<?php
/**
 * Database Warranty Text Update Script
 * Replaces "Garansi resmi" with "Garansi toko" or "Jaminan 100% Asli" in the active database.
 * Run this by visiting tckomputer.shop/maintenance/update_warranty_in_db.php on your browser.
 * Make sure to delete this file after running.
 */

require_once __DIR__ . '/../config/db.php';

try {
    $pdo = getDBConnection();
    
    // 1. Update store settings running ticker
    $stmt1 = $pdo->prepare("UPDATE store_settings SET 
        running_ticker = REPLACE(running_ticker, 'Garansi Resmi Distributor', 'Jaminan 100% Asli'),
        updated_at = NOW()"
    );
    $stmt1->execute();
    
    $stmt2 = $pdo->prepare("UPDATE store_settings SET 
        running_ticker = REPLACE(running_ticker, 'Garansi Resmi', 'Jaminan 100% Asli'),
        updated_at = NOW()"
    );
    $stmt2->execute();

    // 2. Update products warranty note
    $stmt3 = $pdo->prepare("UPDATE products SET 
        warranty_note = REPLACE(warranty_note, 'Garansi resmi', 'Garansi toko'),
        created_at = created_at
    ");
    $stmt3->execute();

    $stmt4 = $pdo->prepare("UPDATE products SET 
        warranty_note = REPLACE(warranty_note, 'garansi resmi', 'garansi toko'),
        created_at = created_at
    ");
    $stmt4->execute();
    
    echo "<div style='font-family: sans-serif; padding: 20px; border: 1px solid #c6f6d5; background-color: #f0fff4; color: #22543d; border-radius: 8px; max-width: 600px; margin: 40px auto;'>";
    echo "<h3 style='margin-top:0;'>Sukses!</h3>";
    echo "<p>Klaim garansi pada database berhasil diperbarui:</p>";
    echo "<ul>";
    echo "<li>Teks 'Garansi Resmi' di running ticker diubah menjadi 'Jaminan 100% Asli'.</li>";
    echo "<li>Catatan garansi produk yang bertuliskan 'Garansi resmi' diubah menjadi 'Garansi toko'.</li>";
    echo "</ul>";
    echo "<p style='margin-bottom:0; font-weight:bold; color:#9b2c2c;'>PENTING: Segera hapus file 'maintenance/update_warranty_in_db.php' dari server Anda demi keamanan.</p>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div style='font-family: sans-serif; padding: 20px; border: 1px solid #fed7d7; background-color: #fff5f5; color: #9b2c2c; border-radius: 8px; max-width: 600px; margin: 40px auto;'>";
    echo "<h3 style='margin-top:0;'>Terjadi Kesalahan</h3>";
    echo "<p style='margin-bottom:0;'>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
