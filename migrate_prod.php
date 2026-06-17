<?php
/**
 * Database Migration Tool for Production (All-in-One)
 * Upload this file to your production server and run it in the browser to update the database schema.
 * REMOVE this file from the server after running for security.
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';

$output = [];
$migrationExecuted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_migration'])) {
    $migrationExecuted = true;
    try {
        $pdo = getDBConnection();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // ==========================================
        // 1. MIGRATION FOR shipping_areas TABLE
        // ==========================================
        
        // Check if 'regency' column exists in shipping_areas
        $areasCols = $pdo->query("SHOW COLUMNS FROM shipping_areas")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('regency', $areasCols, true)) {
            $pdo->exec("ALTER TABLE shipping_areas ADD COLUMN regency VARCHAR(100) NOT NULL DEFAULT 'Tana Toraja' AFTER area_name");
            $output[] = [
                'type' => 'success',
                'title' => 'Kolom `regency` Ditambahkan',
                'desc' => 'Kolom `regency` berhasil ditambahkan ke tabel `shipping_areas`.'
            ];
        } else {
            $output[] = [
                'type' => 'info',
                'title' => 'Kolom `regency` Sudah Ada',
                'desc' => 'Kolom `regency` sudah ada di tabel `shipping_areas`.'
            ];
        }

        // Deactivate old mock shipping areas (preserving them for old orders)
        // We deactivate areas that don't have a specific regency value or are part of old setup
        $pdo->exec("UPDATE shipping_areas SET is_active = 0 WHERE regency = 'Tana Toraja' AND area_name IN (
            'Kota - Dalam Kota (0-3 km)',
            'Kota - Pinggir Kota (3-7 km)',
            'Kecamatan Sekitar (7-15 km)',
            'Luar Kota (15-30 km)',
            'Kabupaten Terdekat (30-50 km)'
        )");
        $output[] = [
            'type' => 'success',
            'title' => 'Area Pengiriman Lama Dinonaktifkan',
            'desc' => 'Wilayah pengiriman default lama dinonaktifkan agar tidak muncul di checkout pembeli.'
        ];

        // Define the 30 new Toraja districts (Kecamatan)
        $districts = [
            // === TANA TORAJA (17 Kecamatan) ===
            ['area_name' => 'Makale', 'regency' => 'Tana Toraja', 'cost' => 0],
            ['area_name' => 'Makale Utara', 'regency' => 'Tana Toraja', 'cost' => 0],
            ['area_name' => 'Makale Selatan', 'regency' => 'Tana Toraja', 'cost' => 0],
            ['area_name' => 'Sangalla', 'regency' => 'Tana Toraja', 'cost' => 10000],
            ['area_name' => 'Sangalla Selatan', 'regency' => 'Tana Toraja', 'cost' => 10000],
            ['area_name' => 'Sangalla Utara', 'regency' => 'Tana Toraja', 'cost' => 10000],
            ['area_name' => 'Rantetayo', 'regency' => 'Tana Toraja', 'cost' => 10000],
            ['area_name' => 'Mengkendek', 'regency' => 'Tana Toraja', 'cost' => 10000],
            ['area_name' => 'Gandangbatu Sillanan', 'regency' => 'Tana Toraja', 'cost' => 10000],
            ['area_name' => 'Kurra', 'regency' => 'Tana Toraja', 'cost' => 20000],
            ['area_name' => 'Malimbong Balepe', 'regency' => 'Tana Toraja', 'cost' => 20000],
            ['area_name' => 'Rembon', 'regency' => 'Tana Toraja', 'cost' => 30000],
            ['area_name' => 'Bittuang', 'regency' => 'Tana Toraja', 'cost' => 30000],
            ['area_name' => 'Masanda', 'regency' => 'Tana Toraja', 'cost' => 30000],
            ['area_name' => 'Bonggakaradeng', 'regency' => 'Tana Toraja', 'cost' => 30000],
            ['area_name' => 'Simbuang', 'regency' => 'Tana Toraja', 'cost' => 30000],
            ['area_name' => 'Mappak', 'regency' => 'Tana Toraja', 'cost' => 30000],

            // === TORAJA UTARA (13 Kecamatan) ===
            ['area_name' => 'Rantepao', 'regency' => 'Toraja Utara', 'cost' => 20000],
            ['area_name' => 'Tallunglipu', 'regency' => 'Toraja Utara', 'cost' => 20000],
            ['area_name' => 'Kesu', 'regency' => 'Toraja Utara', 'cost' => 20000],
            ['area_name' => 'Sanggalangi', 'regency' => 'Toraja Utara', 'cost' => 30000],
            ['area_name' => 'Tikala', 'regency' => 'Toraja Utara', 'cost' => 30000],
            ['area_name' => 'Tondon', 'regency' => 'Toraja Utara', 'cost' => 30000],
            ['area_name' => 'Sopai', 'regency' => 'Toraja Utara', 'cost' => 30000],
            ['area_name' => 'Sa\'dan', 'regency' => 'Toraja Utara', 'cost' => 30000],
            ['area_name' => 'Nanggala', 'regency' => 'Toraja Utara', 'cost' => 30000],
            ['area_name' => 'Buntao', 'regency' => 'Toraja Utara', 'cost' => 30000],
            ['area_name' => 'Rindingallo', 'regency' => 'Toraja Utara', 'cost' => 30000],
            ['area_name' => 'Kapala Pitu', 'regency' => 'Toraja Utara', 'cost' => 30000],
            ['area_name' => 'Baruppu', 'regency' => 'Toraja Utara', 'cost' => 30000],
            
            // === Self Pickup ===
            ['area_name' => 'Ambil di Toko (Self Pickup)', 'regency' => 'Tana Toraja', 'cost' => 0]
        ];

        // Insert districts checking existence to prevent duplicates
        $checkStmt = $pdo->prepare("SELECT id FROM shipping_areas WHERE area_name = ? AND regency = ?");
        $insertStmt = $pdo->prepare("INSERT INTO shipping_areas (area_name, regency, cost, is_active) VALUES (?, ?, ?, 1)");
        
        $insertedCount = 0;
        foreach ($districts as $district) {
            $checkStmt->execute([$district['area_name'], $district['regency']]);
            if (!$checkStmt->fetch()) {
                $insertStmt->execute([
                    $district['area_name'],
                    $district['regency'],
                    $district['cost']
                ]);
                $insertedCount++;
            }
        }

        $output[] = [
            'type' => 'success',
            'title' => 'Kecamatan Toraja Disetup',
            'desc' => "Berhasil memverifikasi wilayah. Menambahkan $insertedCount kecamatan baru ke tabel `shipping_areas`."
        ];

        // ==========================================
        // 2. MIGRATION FOR users TABLE
        // ==========================================
        
        // Check if column shipping_area_id exists in users table
        $usersCols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('shipping_area_id', $usersCols, true)) {
            // Add column
            $pdo->exec("ALTER TABLE users ADD COLUMN shipping_area_id INT UNSIGNED DEFAULT NULL AFTER address");
            $output[] = [
                'type' => 'success',
                'title' => 'Kolom `shipping_area_id` Ditambahkan',
                'desc' => 'Kolom `shipping_area_id` berhasil ditambahkan ke tabel `users`.'
            ];
            
            // Add foreign key constraint
            try {
                $pdo->exec("ALTER TABLE users ADD CONSTRAINT fk_users_shipping_area FOREIGN KEY (shipping_area_id) REFERENCES shipping_areas(id) ON DELETE SET NULL ON UPDATE CASCADE");
                $output[] = [
                    'type' => 'success',
                    'title' => 'Constraint Foreign Key Ditambahkan',
                    'desc' => 'Kunci tamu (FK) fk_users_shipping_area berhasil disetup dari tabel `users` ke `shipping_areas`.'
                ];
            } catch (Exception $fkEx) {
                $output[] = [
                    'type' => 'warning',
                    'title' => 'Foreign Key Gagal',
                    'desc' => 'Kolom ditambahkan, tetapi pembuatan constraint foreign key gagal: ' . htmlspecialchars($fkEx->getMessage())
                ];
            }
        } else {
            $output[] = [
                'type' => 'info',
                'title' => 'Kolom `shipping_area_id` Sudah Ada',
                'desc' => 'Kolom `shipping_area_id` sudah ada di tabel `users`.'
            ];
        }

    } catch (Exception $e) {
        $output[] = [
            'type' => 'error',
            'title' => 'Migrasi Gagal',
            'desc' => 'Terjadi kesalahan sistem: ' . htmlspecialchars($e->getMessage())
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migrasi Database Produksi - TC Komputer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-4 sm:p-8">
    <div class="bg-white rounded-2xl shadow-xl border border-slate-100 max-w-xl w-full overflow-hidden">
        <!-- Banner Header -->
        <div class="bg-gradient-to-r from-blue-600 to-indigo-700 px-8 py-8 text-white">
            <div class="flex items-center gap-3">
                <span class="text-3xl">⚙️</span>
                <div>
                    <h1 class="text-xl font-extrabold tracking-tight">Database Migration Tool</h1>
                    <p class="text-xs text-blue-100 mt-1">TC Komputer Production Update Utility</p>
                </div>
            </div>
        </div>

        <div class="p-8">
            <?php if (!$migrationExecuted): ?>
                <div class="bg-amber-50 border border-amber-200 text-amber-900 rounded-xl p-4 mb-6 flex gap-3 items-start">
                    <span class="text-lg shrink-0">⚠️</span>
                    <div class="text-xs space-y-1">
                        <p class="font-bold">Konfirmasi Pembaruan Database</p>
                        <p class="leading-relaxed opacity-90">Script ini akan mengubah struktur tabel database Anda untuk mendukung fitur pembagian wilayah **Tana Toraja** dan **Toraja Utara**.</p>
                        <ul class="list-disc pl-4 mt-2 space-y-1 font-medium">
                            <li>Menambahkan kolom <code class="bg-amber-100/80 px-1 rounded">regency</code> pada tabel <code class="bg-amber-100/80 px-1 rounded">shipping_areas</code></li>
                            <li>Menonaktifkan tarif ongkir lama tanpa merusak history order</li>
                            <li>Memasukkan 30 kecamatan baru Toraja ke tabel <code class="bg-amber-100/80 px-1 rounded">shipping_areas</code></li>
                            <li>Menambahkan kolom <code class="bg-amber-100/80 px-1 rounded">shipping_area_id</code> dan relasi Foreign Key pada tabel <code class="bg-amber-100/80 px-1 rounded">users</code></li>
                        </ul>
                    </div>
                </div>

                <form action="" method="POST" class="text-center">
                    <input type="hidden" name="run_migration" value="1">
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3.5 px-6 rounded-xl transition-all shadow-md hover:shadow-lg focus:outline-none flex justify-center items-center gap-2 text-sm">
                        🚀 Jalankan Migrasi Sekarang
                    </button>
                </form>
            <?php else: ?>
                <h3 class="text-sm font-bold text-slate-800 mb-4">Hasil Migrasi:</h3>
                <div class="space-y-3">
                    <?php
                    $hasError = false;
                    foreach ($output as $out) {
                        $bgColor = 'bg-slate-50 border-slate-200 text-slate-700';
                        $icon = 'ℹ️';
                        if ($out['type'] === 'success') {
                            $bgColor = 'bg-emerald-50 border-emerald-200 text-emerald-800';
                            $icon = '✅';
                        } elseif ($out['type'] === 'warning') {
                            $bgColor = 'bg-amber-50 border-amber-200 text-amber-800';
                            $icon = '⚠️';
                        } elseif ($out['type'] === 'error') {
                            $bgColor = 'bg-red-50 border-red-200 text-red-800';
                            $icon = '❌';
                            $hasError = true;
                        }
                        
                        echo "<div class='p-4 border rounded-xl {$bgColor} flex gap-3 items-start animate-fade-in-up'>";
                        echo "<span class='text-lg shrink-0'>{$icon}</span>";
                        echo "<div>";
                        echo "<p class='font-bold text-sm'>{$out['title']}</p>";
                        echo "<p class='text-xs mt-0.5 opacity-90 leading-relaxed'>{$out['desc']}</p>";
                        echo "</div>";
                        echo "</div>";
                    }
                    ?>
                </div>

                <?php if (!$hasError): ?>
                    <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-xl text-blue-900 flex gap-3 items-start">
                        <span class="text-lg shrink-0">🎉</span>
                        <div>
                            <p class="font-bold text-sm">Migrasi Selesai & Sukses!</p>
                            <p class="text-xs mt-0.5 opacity-90 leading-relaxed">Seluruh perubahan basis data berhasil disinkronkan. Aplikasi web Anda kini sepenuhnya siap digunakan.</p>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="mt-6 flex gap-3">
                    <a href="index" class="w-full bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold py-3 px-4 rounded-xl transition-colors text-center text-xs">
                        Kembali ke Beranda
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <div class="bg-slate-50 border-t border-slate-100 px-8 py-4 text-center">
            <p class="text-[10px] text-red-500 font-semibold uppercase tracking-wider">
                🛡️ HAPUS FILE INI (`migrate_prod.php`) DARI SERVER ANDA SETELAH MIGRASI SELESAI
            </p>
        </div>
    </div>
</body>
</html>
