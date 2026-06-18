<?php
/**
 * Production Migration Script for FAQ System
 * Upload this file to production and run it via browser: https://yourdomain.com/migrate_faq.php
 * WARNING: Delete this file after successful migration.
 */

require_once __DIR__ . '/config/db.php';

$pdo = getDBConnection();
$messages = [];

try {
    $pdo->beginTransaction();

    // 1. Create FAQ categories table
    $sql1 = "
        CREATE TABLE IF NOT EXISTS `faq_categories` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(100) NOT NULL,
            `description` TEXT NULL,
            `icon` VARCHAR(100) NULL COMMENT 'Material Symbol icon name, e.g. shopping_cart',
            `sort_order` INT NOT NULL DEFAULT 0,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sql1);
    $messages[] = "Table `faq_categories` created or already exists.";

    // 2. Create FAQs table
    $sql2 = "
        CREATE TABLE IF NOT EXISTS `faqs` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `faq_category_id` INT UNSIGNED NOT NULL,
            `question` VARCHAR(500) NOT NULL,
            `answer` TEXT NOT NULL,
            `sort_order` INT NOT NULL DEFAULT 0,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_faqs_category_id` (`faq_category_id`),
            INDEX `idx_faqs_sort_order` (`sort_order`),
            CONSTRAINT `fk_faqs_category` FOREIGN KEY (`faq_category_id`)
                REFERENCES `faq_categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sql2);
    $messages[] = "Table `faqs` created or already exists.";

    // 3. Seed FAQ categories and entries if no data exists yet
    $categoryCount = (int) $pdo->query("SELECT COUNT(*) FROM `faq_categories`")->fetchColumn();
    if ($categoryCount === 0) {
        $categories = [
            ['Pemesanan', 'Pertanyaan seputar cara memesan produk', 'shopping_cart', 1, 1],
            ['Pengiriman', 'Pertanyaan seputar pengiriman dan estimasi waktu', 'local_shipping', 2, 1],
            ['Pembayaran', 'Pertanyaan seputar metode dan konfirmasi pembayaran', 'payments', 3, 1],
            ['Produk & Garansi', 'Pertanyaan seputar produk, kondisi, dan garansi', 'verified_user', 4, 1],
            ['Akun & Keamanan', 'Pertanyaan seputar akun pengguna dan keamanan data', 'shield_person', 5, 1],
        ];

        $categoryStmt = $pdo->prepare("INSERT INTO `faq_categories` (`name`, `description`, `icon`, `sort_order`, `is_active`) VALUES (?, ?, ?, ?, ?)");
        $categoryIds = [];
        foreach ($categories as $category) {
            $categoryStmt->execute($category);
            $categoryIds[$category[0]] = (int) $pdo->lastInsertId();
        }
        $messages[] = "Seeded 5 FAQ categories.";

        $faqs = [
            ['Pemesanan', 'Bagaimana cara memesan produk di TC Komputer?', "Anda dapat memesan produk dengan langkah berikut:\n1. Pilih produk yang diinginkan dari halaman Produk atau Kategori\n2. Klik tombol \"Keranjang\" atau \"Beli Sekarang\"\n3. Atur jumlah barang di halaman Keranjang\n4. Klik \"Checkout\" dan isi data pengiriman\n5. Pilih metode pembayaran dan opsi pengiriman\n6. Konfirmasi pesanan Anda", 1, 1],
            ['Pemesanan', 'Apakah saya harus membuat akun untuk memesan?', 'Ya, Anda perlu mendaftar akun terlebih dahulu untuk melakukan pemesanan. Pendaftaran cukup mudah — klik tombol "Masuk" di pojok kanan atas, lalu pilih tab "Daftar Akun". Isi username, nomor HP, nama lengkap, dan password Anda.', 2, 1],
            ['Pemesanan', 'Bagaimana cara membatalkan pesanan?', 'Untuk membatalkan pesanan, silakan hubungi customer service kami melalui WhatsApp di nomor yang tertera di halaman utama. Pembatalan hanya dapat dilakukan jika pesanan belum diproses (status "Menunggu Konfirmasi").', 3, 1],
            ['Pengiriman', 'Berapa lama estimasi pengiriman?', "Estimasi pengiriman tergantung pada area tujuan:\n- Area Makale dan sekitarnya: 1 hari kerja\n- Area Tana Toraja lainnya: 1-2 hari kerja\n- Area Toraja Utara: 2-3 hari kerja\n\nPengiriman dilakukan setiap hari Senin hingga Sabtu.", 1, 1],
            ['Pengiriman', 'Berapa biaya ongkos kirim?', 'Biaya ongkos kirim bervariasi tergantung area pengiriman, mulai dari GRATIS untuk area Makale hingga Rp 30.000 untuk area yang lebih jauh. Anda dapat melihat estimasi ongkir di halaman detail produk atau saat checkout.', 2, 1],
            ['Pengiriman', 'Apakah bisa ambil sendiri di toko?', 'Ya! Kami menyediakan opsi Self Pickup (Ambil di Toko). Pilih opsi "Ambil di Toko (Self Pickup)" saat checkout dan ongkos kirim menjadi Rp 0. Anda akan dihubungi saat pesanan siap diambil.', 3, 1],
            ['Pembayaran', 'Metode pembayaran apa saja yang tersedia?', "TC Komputer menyediakan 3 metode pembayaran:\n1. Transfer Bank — Transfer ke rekening BCA atau Mandiri kami\n2. COD (Cash on Delivery) — Bayar saat barang diterima\n3. Bayar di Tempat — Pembayaran langsung saat ambil di toko", 1, 1],
            ['Pembayaran', 'Bagaimana cara konfirmasi pembayaran transfer?', "Setelah melakukan transfer, hubungi CS kami via WhatsApp dengan menyertakan:\n- Kode pesanan (format SIT-XXXXXXXX-XXXX)\n- Bukti transfer\n- Nama pengirim\n\nAdmin akan memverifikasi dan memproses pesanan Anda.", 2, 1],
            ['Produk & Garansi', 'Apakah semua produk bergaransi?', 'Sebagian besar produk kami bergaransi resmi dari distributor atau manufaktur. Informasi garansi tercantum di halaman detail masing-masing produk. Untuk produk tanpa garansi resmi, kami memberikan garansi toko.', 1, 1],
            ['Produk & Garansi', 'Apa perbedaan status "Ready" dan "Pre-Order"?', 'Status "Ready" berarti produk tersedia langsung di toko dan bisa langsung dikirim. Status "Pre-Order" berarti produk perlu dipesan terlebih dahulu dari supplier, dengan estimasi waktu yang akan diinformasikan.', 2, 1],
            ['Produk & Garansi', 'Apakah menjual produk bekas/second?', 'Ya, beberapa produk kami berstatus "Bekas" (Used). Kondisi produk dicantumkan dengan jelas pada halaman detail produk. Semua produk bekas sudah melalui pengecekan kualitas.', 3, 1],
            ['Akun & Keamanan', 'Bagaimana cara mengubah data profil saya?', 'Klik ikon profil di pojok kanan atas, lalu pilih "Profil Saya". Anda dapat mengubah nama, email, alamat, dan area pengiriman dari menu tersebut.', 1, 1],
            ['Akun & Keamanan', 'Apakah data saya aman?', 'Ya, keamanan data pelanggan adalah prioritas kami. Kami menggunakan enkripsi password (bcrypt), CSRF protection, prepared statements untuk mencegah SQL injection, dan sanitisasi output untuk mencegah XSS.', 2, 1],
        ];

        $faqStmt = $pdo->prepare("INSERT INTO `faqs` (`faq_category_id`, `question`, `answer`, `sort_order`, `is_active`) VALUES (?, ?, ?, ?, ?)");
        foreach ($faqs as $faq) {
            $faqStmt->execute([$categoryIds[$faq[0]], $faq[1], $faq[2], $faq[3], $faq[4]]);
        }
        $messages[] = "Seeded 13 FAQ entries.";
    } else {
        $messages[] = "FAQ seed data skipped because `faq_categories` already contains data.";
    }

    $pdo->commit();
    $messages[] = "Migration completed successfully!";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $messages[] = "ERROR: " . $e->getMessage();
}

// Display results
echo "<h1>FAQ Migration Result</h1><ul>";
foreach ($messages as $msg) {
    $color = strpos($msg, 'ERROR') !== false ? 'red' : 'green';
    echo "<li style='color: $color;'>$msg</li>";
}
echo "</ul><br><p><strong>Please delete this file (migrate_faq.php) after running it for security.</strong></p>";
