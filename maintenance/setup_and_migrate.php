<?php
/**
 * Setup and Migration Script for TC Komputer
 * Automatically sets up the database, creates the DB user, grants permissions, 
 * imports seed data (safely without overwriting), and sets up the product_images table.
 */

// Enable error reporting to CLI
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "=========================================================\n";
echo "        TC Komputer - Setup & Migration Helper           \n";
echo "=========================================================\n\n";

// 1. Load configuration from .env file
$envFile = __DIR__ . '/../.env';
$dbHost = 'localhost';
$dbName = 'u496707900_steven_it_shop';
$dbUser = 'u496707900_steven_it_shop';
$dbPass = 'Steven_it_shop1';

if (file_exists($envFile)) {
    echo "[INFO] Membaca konfigurasi dari berkas .env...\n";
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0 || strpos($line, '=') === false) {
            continue;
        }
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (preg_match('/^"([^"]*)"$/', $value, $matches) || preg_match('/^\'([^\']*)\'$/', $value, $matches)) {
            $value = $matches[1];
        }
        if ($name === 'DB_HOST') $dbHost = $value;
        if ($name === 'DB_NAME') $dbName = $value;
        if ($name === 'DB_USER') $dbUser = $value;
        if ($name === 'DB_PASS') $dbPass = $value;
    }
} else {
    echo "[WARNING] Berkas .env tidak ditemukan. Menggunakan nilai default.\n";
}

try {
    // 2. Connect to MySQL as root to perform administrative tasks
    echo "[STEP 1] Menghubungkan ke MySQL Localhost sebagai root...\n";
    $adminPdo = new PDO("mysql:host=localhost", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    // Create database if not exists
    echo "[STEP 2] Membuat database jika belum ada: `$dbName`...\n";
    $adminPdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` COLLATE utf8mb4_unicode_ci;");
    
    // Create database user and grant permissions
    echo "[STEP 3] Menyiapkan user database: `$dbUser`...\n";
    if ($dbUser !== 'root') {
        $adminPdo->exec("CREATE USER IF NOT EXISTS '$dbUser'@'localhost' IDENTIFIED BY '$dbPass';");
        // Update password if user already exists
        try {
            $adminPdo->exec("ALTER USER '$dbUser'@'localhost' IDENTIFIED BY '$dbPass';");
        } catch (Exception $e) {
            // Ignore if alter user fails due to privileges
        }
        $adminPdo->exec("GRANT ALL PRIVILEGES ON `$dbName`.* TO '$dbUser'@'localhost';");
        $adminPdo->exec("FLUSH PRIVILEGES;");
    }
    echo "[SUCCESS] Akses admin/user database berhasil dikonfigurasi.\n\n";

} catch (Exception $e) {
    echo "[WARNING] Gagal melakukan setup administratif MySQL (root): " . $e->getMessage() . "\n";
    echo "          Mencoba langsung menghubungkan menggunakan kredensial .env...\n\n";
}

try {
    // 3. Connect to the database using the actual app credentials
    echo "[STEP 4] Menghubungkan ke database `$dbName` dengan user `$dbUser`...\n";
    $dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "[SUCCESS] Koneksi database berhasil!\n\n";
    
    // 4. Safely import schema database.sql if database is empty
    echo "[STEP 5] Memeriksa apakah database sudah memiliki tabel...\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'products'");
    $hasProducts = $stmt->fetch();
    
    if (!$hasProducts) {
        echo "[INFO] Tabel utama tidak ditemukan. Mengimpor skema awal dari database.sql...\n";
        $sqlFile = __DIR__ . '/database.sql';
        if (file_exists($sqlFile)) {
            $sqlContent = file_get_contents($sqlFile);
            // Split queries by semicolon to execute safely
            $queries = array_filter(array_map('trim', explode(';', $sqlContent)));
            foreach ($queries as $query) {
                if (empty($query)) continue;
                try {
                    $pdo->exec($query);
                } catch (PDOException $ex) {
                    // Log warning but continue
                    echo "[WARNING] Gagal mengeksekusi sebagian SQL: " . substr($query, 0, 40) . "... Error: " . $ex->getMessage() . "\n";
                }
            }
            echo "[SUCCESS] Skema awal database.sql berhasil diimpor.\n\n";
        } else {
            echo "[ERROR] Berkas database.sql tidak ditemukan di $sqlFile. Melewati...\n\n";
        }
    } else {
        echo "[INFO] Tabel produk sudah ada. Melewati impor database.sql agar data Anda aman.\n\n";
    }
    
    // 5. Ensure product_images table exists
    echo "[STEP 6] Memastikan tabel `product_images` untuk fitur multi-foto sudah dibuat...\n";
    $sqlGalleryTable = "CREATE TABLE IF NOT EXISTS `product_images` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `product_id` INT UNSIGNED NOT NULL,
        `image_path` VARCHAR(255) NOT NULL,
        `sort_order` INT NOT NULL DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        CONSTRAINT `fk_product_images_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    $pdo->exec($sqlGalleryTable);
    echo "[SUCCESS] Tabel `product_images` siap digunakan.\n\n";
    
    echo "=========================================================\n";
    echo "         MIGRASI & SETUP SELESAI DENGAN SUKSES!          \n";
    echo "=========================================================\n";
    
} catch (Exception $e) {
    echo "[ERROR] Setup & Migrasi gagal dilakukan: " . $e->getMessage() . "\n";
    exit(1);
}
