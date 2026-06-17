-- SQL Migration: Add Users Table for Username/Phone + Password Authentication
-- Jalankan file SQL ini pada database production Anda untuk menerapkan pembaruan.

CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(20) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `address` TEXT NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_users_username` (`username`),
    UNIQUE KEY `uk_users_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SEED DUMMY USER: Akun pembeli default 'steven' dengan password 'user123'
-- Anda dapat menjalankan ini jika ingin membuat akun percobaan default secara langsung.
INSERT INTO `users` (`username`, `phone`, `name`, `email`, `address`, `password`, `created_at`)
SELECT 'steven', '082293924242', 'HERMANTO STEVEN LISU', 'steven@tckomputer.com', 'Jl. Teknologi No. 88, Kota Komputerindo, Jawa Timur 60123', '$2y$10$wN1FvWcE6s8aJ.c5vG.3tuxh8uW7hS5G7aC1I2eC3sB4R5Q6eE7e2', NOW()
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM `users` WHERE `username` = 'steven' OR `phone` = '082293924242'
);

-- MIGRATION: Impor data pembeli lama dari tabel orders ke tabel users
-- Menghindari hilangnya akses riwayat pesanan bagi pelanggan yang sudah pernah bertransaksi.
-- Username default akan dibuat dengan format: user_nomorhp (contoh: user_08123456789)
-- Email default akan dibuat dengan format: nomorhp@tckomputer.com
-- Kata sandi default diset ke 'user123' (dapat diubah pembeli setelah masuk di menu Edit Profil).
INSERT INTO `users` (`username`, `phone`, `name`, `email`, `address`, `password`, `created_at`)
SELECT 
    CONCAT('user_', REPLACE(REPLACE(REPLACE(buyer_phone, '+', ''), ' ', ''), '-', '')) AS username,
    buyer_phone AS phone,
    MAX(buyer_name) AS name,
    CONCAT(REPLACE(REPLACE(REPLACE(buyer_phone, '+', ''), ' ', ''), '-', ''), '@tckomputer.com') AS email,
    MAX(buyer_address) AS address,
    '$2y$10$wN1FvWcE6s8aJ.c5vG.3tuxh8uW7hS5G7aC1I2eC3sB4R5Q6eE7e2' AS password,
    NOW()
FROM `orders`
WHERE buyer_phone NOT IN (SELECT phone FROM `users`)
GROUP BY buyer_phone;

