-- =============================================
-- Steven IT Shop - Database Schema
-- MySQL 5.7+ compatible
-- =============================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Drop tables if they exist (in reverse dependency order)
DROP TABLE IF EXISTS `promotions`;
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `shipping_areas`;
DROP TABLE IF EXISTS `banners`;
DROP TABLE IF EXISTS `store_settings`;
DROP TABLE IF EXISTS `admins`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================
-- Table: admins
-- =============================================
CREATE TABLE `admins` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_admins_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: shipping_areas
-- =============================================
CREATE TABLE `shipping_areas` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `area_name` VARCHAR(100) NOT NULL,
    `regency` VARCHAR(100) NOT NULL DEFAULT 'Tana Toraja',
    `cost` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: users
-- =============================================
CREATE TABLE `users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(20) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `address` TEXT NOT NULL,
    `shipping_area_id` INT UNSIGNED DEFAULT NULL,
    `password` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_users_username` (`username`),
    UNIQUE KEY `uk_users_phone` (`phone`),
    CONSTRAINT `fk_users_shipping_area` FOREIGN KEY (`shipping_area_id`) REFERENCES `shipping_areas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: categories
-- =============================================
CREATE TABLE `categories` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `image` VARCHAR(255) NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_categories_slug` (`slug`),
    INDEX `idx_categories_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: products
-- =============================================
CREATE TABLE `products` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `category_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) NOT NULL,
    `sku` VARCHAR(100) NULL,
    `brand` VARCHAR(100) NULL,
    `model` VARCHAR(100) NULL,
    `description` TEXT NULL,
    `specification` TEXT NULL,
    `purchase_price` INT NOT NULL DEFAULT 0,
    `selling_price` INT NOT NULL DEFAULT 0,
    `promo_price` INT NOT NULL DEFAULT 0,
    `promo_active` TINYINT(1) NOT NULL DEFAULT 0,
    `promo_stock` INT NOT NULL DEFAULT 0,
    `promo_stock_initial` INT NOT NULL DEFAULT 0,
    `stock` INT NOT NULL DEFAULT 0,
    `status` ENUM('ready', 'po', 'habis') NOT NULL DEFAULT 'ready',
    `condition_type` ENUM('new', 'used') NOT NULL DEFAULT 'new',
    `warranty_note` VARCHAR(255) NULL,
    `image` VARCHAR(255) NULL,
    `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_products_slug` (`slug`),
    INDEX `idx_products_slug` (`slug`),
    INDEX `idx_products_category_id` (`category_id`),
    INDEX `idx_products_status` (`status`),
    CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: promotions
-- =============================================
CREATE TABLE `promotions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `promo_type` ENUM('free_shipping', 'category_discount', 'free_item', 'cart_discount') NOT NULL,
    `discount_type` ENUM('percentage', 'fixed') NOT NULL DEFAULT 'fixed',
    `discount_value` INT NOT NULL DEFAULT 0,
    `min_spend` INT NOT NULL DEFAULT 0,
    `target_category_id` INT UNSIGNED NULL,
    `free_item_id` INT UNSIGNED NULL,
    `start_date` DATETIME NOT NULL,
    `end_date` DATETIME NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_promo_category` FOREIGN KEY (`target_category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_promo_free_item` FOREIGN KEY (`free_item_id`) REFERENCES `products` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: orders
-- =============================================
CREATE TABLE `orders` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_code` VARCHAR(17) NOT NULL,
    `buyer_name` VARCHAR(100) NOT NULL,
    `buyer_phone` VARCHAR(20) NOT NULL,
    `buyer_address` TEXT NOT NULL,
    `shipping_area_id` INT UNSIGNED NOT NULL,
    `shipping_cost` INT NOT NULL DEFAULT 0,
    `discount_amount` INT NOT NULL DEFAULT 0,
    `applied_promotions` TEXT NULL,
    `subtotal` INT NOT NULL DEFAULT 0,
    `total` INT NOT NULL DEFAULT 0,
    `payment_method` ENUM('cod', 'transfer', 'pay_on_delivery') NOT NULL,
    `payment_status` ENUM('belum_dibayar', 'menunggu_konfirmasi', 'sudah_dibayar', 'cod') NOT NULL DEFAULT 'belum_dibayar',
    `order_status` ENUM('menunggu_konfirmasi', 'diproses', 'siap_diantar', 'dikirim', 'selesai', 'dibatalkan') NOT NULL DEFAULT 'menunggu_konfirmasi',
    `shipping_option` ENUM('self_pickup', 'local_delivery', 'local_courier') NOT NULL,
    `order_notes` TEXT NULL,
    `admin_notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_orders_order_code` (`order_code`),
    INDEX `idx_orders_order_code` (`order_code`),
    INDEX `idx_orders_buyer_phone` (`buyer_phone`),
    CONSTRAINT `fk_orders_shipping_area` FOREIGN KEY (`shipping_area_id`) REFERENCES `shipping_areas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: order_items
-- =============================================
CREATE TABLE `order_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `product_name` VARCHAR(255) NOT NULL,
    `product_price` INT NOT NULL DEFAULT 0,
    `quantity` INT NOT NULL DEFAULT 1,
    `subtotal` INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    INDEX `idx_order_items_order_id` (`order_id`),
    INDEX `idx_order_items_product_id` (`product_id`),
    CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: store_settings
-- =============================================
CREATE TABLE `store_settings` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `store_name` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(20) NOT NULL,
    `address` TEXT NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `logo` VARCHAR(255) NULL,
    `bank_account` TEXT NULL,
    `cod_info` TEXT NULL,
    `shipping_info` TEXT NULL,
    `footer_text` TEXT NULL,
    `flash_sale_end` DATETIME NULL,
    `flash_sale_title` VARCHAR(255) NULL,
    `flash_sale_subtitle` VARCHAR(255) NULL,
    `flash_sale_active` TINYINT(1) NOT NULL DEFAULT 0,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: banners
-- =============================================
CREATE TABLE `banners` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `image` VARCHAR(255) NOT NULL,
    `link_url` VARCHAR(2048) NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================
-- Seed Data
-- =============================================

-- =============================================
-- Admin Account
-- Password: admin123 (bcrypt hashed)
-- =============================================
INSERT INTO `admins` (`name`, `email`, `password`, `created_at`) VALUES
('Admin TC Komputer', 'admin@tckomputer.com', '$2y$12$W3tB7KOXRe1vgrNRYmOuOOip6xKM/sZZsT8gr7BJRtNmMtat92Cy.', NOW());

-- =============================================
-- Buyer Account
-- Password: user123 (bcrypt hashed)
-- =============================================
INSERT INTO `users` (`username`, `phone`, `name`, `email`, `address`, `shipping_area_id`, `password`, `created_at`) VALUES
('steven', '082293924242', 'HERMANTO STEVEN LISU', 'steven@tckomputer.com', 'Jl. Teknologi No. 88, Kota Komputerindo, Jawa Timur 60123', 1, '$2y$12$omr9H76he2t8lhLDYo3IoOVOSoDdlBu8BD3YwCeGjcO/ItENGkplK', NOW());

-- =============================================
-- Categories
-- =============================================
INSERT INTO `categories` (`name`, `slug`, `description`, `image`, `is_active`, `sort_order`, `created_at`) VALUES
('Laptop Accessories', 'laptop-accessories', 'Aksesoris laptop seperti tas, cooling pad, screen protector, dan lainnya', NULL, 1, 1, NOW()),
('Phone Accessories', 'phone-accessories', 'Aksesoris handphone seperti case, tempered glass, holder, dan lainnya', NULL, 1, 2, NOW()),
('Cables & Converters', 'cables-converters', 'Kabel data, kabel HDMI, converter, adapter, dan lainnya', NULL, 1, 3, NOW()),
('Peripherals', 'peripherals', 'Mouse, keyboard, headset, webcam, speaker, dan perangkat input/output lainnya', NULL, 1, 4, NOW()),
('Storage', 'storage', 'Flashdisk, SSD, HDD, memory card, dan media penyimpanan lainnya', NULL, 1, 5, NOW()),
('Printers & Ink', 'printers-ink', 'Printer, tinta, toner, kertas, dan perlengkapan cetak lainnya', NULL, 1, 6, NOW()),
('Service Tools', 'service-tools', 'Obeng set, solder, pasta thermal, toolkit servis komputer dan laptop', NULL, 1, 7, NOW());

-- =============================================
-- Products
-- =============================================
INSERT INTO `products` (`category_id`, `name`, `slug`, `sku`, `brand`, `model`, `description`, `specification`, `purchase_price`, `selling_price`, `stock`, `status`, `condition_type`, `warranty_note`, `image`, `is_featured`, `is_active`, `created_at`) VALUES
-- Laptop Accessories (category_id = 1)
(1, 'Tas Laptop 14 inch Anti Air', 'tas-laptop-14-inch-anti-air', 'LA-001', 'Targus', 'City Smart', 'Tas laptop 14 inch dengan bahan anti air, cocok untuk mobilitas tinggi', 'Ukuran: 14 inch\nBahan: Polyester anti air\nWarna: Hitam\nKompartemen: 3', 120000, 185000, 15, 'ready', 'new', 'Garansi 1 bulan', NULL, 1, 1, NOW()),
(1, 'Cooling Pad Laptop 2 Fan', 'cooling-pad-laptop-2-fan', 'LA-002', 'Cooler Master', 'NotePal X2', 'Cooling pad dengan 2 kipas untuk laptop hingga 15.6 inch', 'Fan: 2x 140mm\nUkuran: 15.6 inch\nUSB Port: 1\nMaterial: Metal mesh', 85000, 135000, 8, 'ready', 'new', 'Garansi 6 bulan', NULL, 1, 1, NOW()),
(1, 'Screen Protector Laptop 15.6 inch', 'screen-protector-laptop-15-6-inch', 'LA-003', 'Generic', 'SP-156', 'Anti gores layar laptop 15.6 inch anti glare', 'Ukuran: 15.6 inch\nTipe: Anti Glare\nBahan: PET Film', 25000, 45000, 0, 'habis', 'new', NULL, NULL, 0, 1, NOW()),

-- Phone Accessories (category_id = 2)
(2, 'Case iPhone 15 Pro Max Silicone', 'case-iphone-15-pro-max-silicone', 'PA-001', 'Apple', 'MagSafe Silicone', 'Case silicone original untuk iPhone 15 Pro Max', 'Material: Silicone\nKompatibel: iPhone 15 Pro Max\nFitur: MagSafe', 450000, 650000, 5, 'ready', 'new', 'Garansi 1 bulan', NULL, 1, 1, NOW()),
(2, 'Tempered Glass Samsung Galaxy S24', 'tempered-glass-samsung-galaxy-s24', 'PA-002', 'Whitestone', 'Dome Glass', 'Tempered glass premium UV curved untuk Samsung Galaxy S24', 'Ketebalan: 0.33mm\n9H Hardness\nUV Adhesive\nFull Cover', 35000, 65000, 20, 'ready', 'new', NULL, NULL, 0, 1, NOW()),
(2, 'Phone Holder Motor Waterproof', 'phone-holder-motor-waterproof', 'PA-003', 'Generic', 'WP-Mount', 'Holder HP untuk motor anti air dengan mount stang', 'Ukuran HP: 4.7-6.8 inch\nWaterproof: IPX6\nMount: Stang motor', 40000, 75000, 0, 'po', 'new', 'Garansi 1 bulan', NULL, 0, 1, NOW()),

-- Cables & Converters (category_id = 3)
(3, 'Kabel USB-C to USB-C 100W 2M', 'kabel-usb-c-to-usb-c-100w-2m', 'CC-001', 'Baseus', 'Tungsten Gold', 'Kabel USB-C ke USB-C 100W PD fast charging 2 meter', 'Panjang: 2 meter\nDaya: 100W PD\nData: USB 2.0\nBahan: Nylon braided', 45000, 89000, 25, 'ready', 'new', 'Garansi 6 bulan', NULL, 1, 1, NOW()),
(3, 'HDMI Cable 4K 3 Meter', 'hdmi-cable-4k-3-meter', 'CC-002', 'Vention', 'HDMI 2.0', 'Kabel HDMI 2.0 support 4K 60Hz panjang 3 meter', 'Versi: HDMI 2.0\nResolusi: 4K@60Hz\nPanjang: 3 meter\nKonektor: Gold plated', 35000, 65000, 12, 'ready', 'new', 'Garansi 1 tahun', NULL, 0, 1, NOW()),
(3, 'USB Hub 4 Port USB 3.0', 'usb-hub-4-port-usb-3-0', 'CC-003', 'Orico', 'W5PH4-U3', 'USB Hub 4 port USB 3.0 dengan kabel 1 meter', 'Port: 4x USB 3.0\nKecepatan: 5Gbps\nKabel: 1 meter\nPower: Bus powered', 65000, 115000, 0, 'po', 'new', 'Garansi 1 tahun', NULL, 0, 1, NOW()),

-- Peripherals (category_id = 4)
(4, 'Mouse Wireless Logitech M331', 'mouse-wireless-logitech-m331', 'PR-001', 'Logitech', 'M331 Silent Plus', 'Mouse wireless silent click dengan sensor 1000 DPI', 'Sensor: 1000 DPI\nKoneksi: 2.4GHz USB receiver\nBaterai: 1x AA\nFitur: Silent click', 180000, 275000, 10, 'ready', 'new', 'Garansi resmi 1 tahun', NULL, 1, 1, NOW()),
(4, 'Keyboard Mechanical TKL RGB', 'keyboard-mechanical-tkl-rgb', 'PR-002', 'Rexus', 'Daiva D68SF', 'Keyboard mechanical TKL hot-swappable RGB', 'Switch: Gateron Yellow\nLayout: TKL 68 keys\nBacklight: RGB\nKoneksi: USB-C', 350000, 520000, 3, 'ready', 'new', 'Garansi 1 tahun', NULL, 1, 1, NOW()),
(4, 'Headset Gaming 7.1 Surround', 'headset-gaming-7-1-surround', 'PR-003', 'HyperX', 'Cloud Stinger', 'Headset gaming 7.1 virtual surround lightweight', 'Driver: 50mm\nSurround: 7.1 Virtual\nMicrophone: Swivel-to-mute\nBerat: 275g', 400000, 599000, 0, 'habis', 'new', 'Garansi resmi 2 tahun', NULL, 0, 1, NOW()),

-- Storage (category_id = 5)
(5, 'SSD NVMe 512GB PCIe Gen4', 'ssd-nvme-512gb-pcie-gen4', 'ST-001', 'Samsung', '980 Pro', 'SSD NVMe M.2 512GB PCIe Gen 4 kecepatan baca 7000MB/s', 'Kapasitas: 512GB\nInterface: PCIe Gen 4 NVMe\nBaca: 7000MB/s\nTulis: 5000MB/s', 750000, 950000, 6, 'ready', 'new', 'Garansi 5 tahun', NULL, 1, 1, NOW()),
(5, 'Flashdisk 64GB USB 3.2', 'flashdisk-64gb-usb-3-2', 'ST-002', 'SanDisk', 'Ultra Fit', 'Flashdisk mini 64GB USB 3.2 kecepatan 130MB/s', 'Kapasitas: 64GB\nInterface: USB 3.2 Gen 1\nKecepatan: 130MB/s\nDesain: Ultra compact', 75000, 125000, 30, 'ready', 'new', 'Garansi 5 tahun', NULL, 0, 1, NOW()),
(5, 'MicroSD 256GB A2 V30', 'microsd-256gb-a2-v30', 'ST-003', 'Samsung', 'EVO Select', 'MicroSD 256GB UHS-I A2 V30 untuk HP dan kamera', 'Kapasitas: 256GB\nKelas: A2, V30, U3\nBaca: 160MB/s\nTulis: 120MB/s', 280000, 385000, 0, 'po', 'new', 'Garansi 5 tahun', NULL, 0, 1, NOW()),

-- Printers & Ink (category_id = 6)
(6, 'Tinta Epson 003 Black Original', 'tinta-epson-003-black-original', 'PI-001', 'Epson', '003 Black', 'Tinta original Epson 003 warna hitam untuk printer L-series', 'Volume: 65ml\nWarna: Black\nKompatibel: L1110, L3110, L3150, L5190', 80000, 115000, 20, 'ready', 'new', NULL, NULL, 0, 1, NOW()),
(6, 'Cartridge HP 680 Black', 'cartridge-hp-680-black', 'PI-002', 'HP', '680 Black', 'Cartridge original HP 680 warna hitam', 'Tipe: Ink Advantage\nWarna: Black\nYield: 480 halaman', 130000, 185000, 8, 'ready', 'new', NULL, NULL, 0, 1, NOW()),
(6, 'Printer Epson L121 EcoTank', 'printer-epson-l121-ecotank', 'PI-003', 'Epson', 'L121', 'Printer inkjet Epson L121 dengan sistem infus bawaan', 'Tipe: Inkjet\nFungsi: Print only\nSistem Tinta: EcoTank\nResolusi: 720x720 dpi', 1400000, 1750000, 2, 'ready', 'new', 'Garansi resmi 2 tahun', NULL, 1, 1, NOW()),

-- Service Tools (category_id = 7)
(7, 'Obeng Set 25 in 1 Precision', 'obeng-set-25-in-1-precision', 'SV-001', 'Jakemy', 'JM-8166', 'Set obeng presisi 25 in 1 untuk servis laptop dan HP', 'Jumlah mata: 25 pcs\nBahan: S2 Steel\nCase: Aluminium\nMagnetik: Ya', 55000, 89000, 15, 'ready', 'new', 'Garansi 3 bulan', NULL, 0, 1, NOW()),
(7, 'Pasta Thermal Arctic MX-4', 'pasta-thermal-arctic-mx-4', 'SV-002', 'Arctic', 'MX-4', 'Pasta thermal premium untuk CPU/GPU, 4 gram', 'Berat: 4 gram\nKonduktivitas: 8.5 W/mK\nNon-electrical conductive\nDurabilitas: 8 tahun', 65000, 105000, 10, 'ready', 'new', NULL, NULL, 0, 1, NOW()),
(7, 'Solder Station Digital 60W', 'solder-station-digital-60w', 'SV-003', 'Hakko', 'FX-888D', 'Solder station digital dengan pengaturan suhu', 'Daya: 60W\nSuhu: 200-480°C\nDisplay: Digital LED\nTip: T18 series', 1800000, 2350000, 0, 'po', 'new', 'Garansi 1 tahun', NULL, 0, 1, NOW());

-- =============================================
-- Shipping Areas
-- =============================================
INSERT INTO `shipping_areas` (`area_name`, `regency`, `cost`, `is_active`, `created_at`) VALUES
('Makale', 'Tana Toraja', 0, 1, NOW()),
('Makale Utara', 'Tana Toraja', 0, 1, NOW()),
('Makale Selatan', 'Tana Toraja', 0, 1, NOW()),
('Sangalla', 'Tana Toraja', 10000, 1, NOW()),
('Sangalla Selatan', 'Tana Toraja', 10000, 1, NOW()),
('Sangalla Utara', 'Tana Toraja', 10000, 1, NOW()),
('Rantetayo', 'Tana Toraja', 10000, 1, NOW()),
('Mengkendek', 'Tana Toraja', 10000, 1, NOW()),
('Gandangbatu Sillanan', 'Tana Toraja', 10000, 1, NOW()),
('Kurra', 'Tana Toraja', 20000, 1, NOW()),
('Malimbong Balepe', 'Tana Toraja', 20000, 1, NOW()),
('Rembon', 'Tana Toraja', 30000, 1, NOW()),
('Bittuang', 'Tana Toraja', 30000, 1, NOW()),
('Masanda', 'Tana Toraja', 30000, 1, NOW()),
('Bonggakaradeng', 'Tana Toraja', 30000, 1, NOW()),
('Simbuang', 'Tana Toraja', 30000, 1, NOW()),
('Mappak', 'Tana Toraja', 30000, 1, NOW()),
('Rantepao', 'Toraja Utara', 20000, 1, NOW()),
('Tallunglipu', 'Toraja Utara', 20000, 1, NOW()),
('Kesu', 'Toraja Utara', 20000, 1, NOW()),
('Sanggalangi', 'Toraja Utara', 30000, 1, NOW()),
('Tikala', 'Toraja Utara', 30000, 1, NOW()),
('Tondon', 'Toraja Utara', 30000, 1, NOW()),
('Sopai', 'Toraja Utara', 30000, 1, NOW()),
('Sa\'dan', 'Toraja Utara', 30000, 1, NOW()),
('Nanggala', 'Toraja Utara', 30000, 1, NOW()),
('Buntao', 'Toraja Utara', 30000, 1, NOW()),
('Rindingallo', 'Toraja Utara', 30000, 1, NOW()),
('Kapala Pitu', 'Toraja Utara', 30000, 1, NOW()),
('Baruppu', 'Toraja Utara', 30000, 1, NOW()),
('Ambil di Toko (Self Pickup)', 'Tana Toraja', 0, 1, NOW());

-- =============================================
-- Store Settings
-- =============================================
INSERT INTO `store_settings` (`store_name`, `phone`, `address`, `email`, `logo`, `bank_account`, `cod_info`, `shipping_info`, `footer_text`, `flash_sale_end`, `flash_sale_title`, `flash_sale_subtitle`, `flash_sale_active`, `updated_at`) VALUES
('TC Komputer', '081234567890', 'Jl. Teknologi No. 88, Kota Komputerindo, Jawa Timur 60123', 'info@tckomputer.com', NULL, 'BCA: 1234567890 a.n. TC Komputer\nMandiri: 0987654321 a.n. TC Komputer', 'Pembayaran COD dilakukan saat barang diterima. Mohon siapkan uang pas.', 'Pengiriman dilakukan setiap hari Senin-Sabtu. Estimasi pengiriman 1-2 hari kerja untuk area kota.', '© 2026 TC Komputer. Toko komputer dan aksesoris terlengkap.', '2026-12-31 23:59:59', 'Flash Sale', 'Berakhir dalam:', 1, NOW());

-- =============================================
-- Banners
-- =============================================
INSERT INTO `banners` (`title`, `description`, `image`, `link_url`, `sort_order`, `is_active`, `created_at`) VALUES
('Promo Aksesoris Laptop', 'Diskon hingga 30% untuk semua aksesoris laptop selama bulan ini', 'banner-promo-laptop.jpg', 'category?slug=laptop-accessories', 1, 1, NOW()),
('Produk Baru - Peripheral Gaming', 'Koleksi keyboard mechanical dan mouse gaming terbaru sudah tersedia', 'banner-peripheral-gaming.jpg', 'category?slug=peripherals', 2, 1, NOW()),
('Gratis Ongkir Dalam Kota', 'Gratis ongkos kirim untuk pembelian di atas Rp 200.000 dalam kota', 'banner-free-shipping.jpg', 'products', 3, 1, NOW());
