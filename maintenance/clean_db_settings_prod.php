<?php
/**
 * Production Database Settings Clean-up Script
 * Sanitizes the store_settings table to remove the misleading running ticker claim.
 * Run this by visiting tckomputer.shop/clean_db_settings_prod.php on your browser.
 * Make sure to delete this file after running.
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/helpers.php';

try {
    $pdo = getDBConnection();
    
    // Fetch current settings
    $stmt = $pdo->query("SELECT * FROM store_settings LIMIT 1");
    $settings = $stmt->fetch();
    
    if ($settings) {
        $ticker = $settings['running_ticker'] ?? '';
        if (strpos($ticker, 'Bebas Ongkir Wilayah Gowa & Makassar') !== false || strpos($ticker, 'Gowa & Makassar') !== false || strpos($ticker, 'PROMO MERDEKA') !== false) {
            $newTicker = "⚡ Dapatkan perlengkapan IT, hardware, dan aksesoris komputer berkualitas tinggi dengan Jaminan Asli • Konsultasi Rakit PC & Layanan Ramah Terpercaya!";
            
            $stmtUpdate = $pdo->prepare("UPDATE store_settings SET running_ticker = ?, updated_at = NOW() WHERE id = ?");
            $stmtUpdate->execute([$newTicker, $settings['id']]);
            
            echo "<div style='font-family: sans-serif; padding: 20px; border: 1px solid #c6f6d5; background-color: #f0fff4; color: #22543d; border-radius: 8px;'>";
            echo "<h3 style='margin-top:0;'>Sukses!</h3>";
            echo "<p>Data running ticker pada database produksi berhasil diperbarui.</p>";
            echo "<p>Teks baru: <strong>" . htmlspecialchars($newTicker) . "</strong></p>";
            echo "<p style='margin-bottom:0; font-weight:bold; color:#9b2c2c;'>PENTING: Segera hapus file 'clean_db_settings_prod.php' dari server hosting Anda demi keamanan.</p>";
            echo "</div>";
        } else {
            echo "<div style='font-family: sans-serif; padding: 20px; border: 1px solid #feebc8; background-color: #fffaf0; color: #744210; border-radius: 8px;'>";
            echo "<h3 style='margin-top:0;'>Informasi</h3>";
            echo "<p style='margin-bottom:0;'>Data running ticker di database produksi sudah bersih dari klaim bebas ongkir fiktif. Tidak ada perubahan yang dilakukan.</p>";
            echo "</div>";
        }
    } else {
        echo "<p>Error: Pengaturan toko tidak ditemukan di database.</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>Terjadi kesalahan: " . htmlspecialchars($e->getMessage()) . "</p>";
}
