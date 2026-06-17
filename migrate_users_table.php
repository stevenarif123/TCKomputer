<?php
/**
 * Database Migration: Add shipping_area_id to users table
 * Upload this file to your production server and open it in your browser to execute.
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migrasi Database: Tabel Users</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-6">
    <div class="bg-white rounded-2xl shadow-xl border border-slate-100 max-w-lg w-full p-8">
        <h1 class="text-xl font-extrabold text-slate-800 mb-6 flex items-center gap-2">
            <span class="text-2xl">⚙️</span> Migrasi Database: Tabel Users
        </h1>
        
        <div class="space-y-4">
            <?php
            try {
                $pdo = getDBConnection();
                $output = [];
                
                // 1. Check if column shipping_area_id exists in users table
                $usersCols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
                
                if (!in_array('shipping_area_id', $usersCols, true)) {
                    // Add shipping_area_id column
                    $pdo->exec("ALTER TABLE users ADD COLUMN shipping_area_id INT UNSIGNED DEFAULT NULL AFTER address");
                    $output[] = [
                        'type' => 'success',
                        'title' => 'Kolom Ditambahkan',
                        'desc' => 'Kolom `shipping_area_id` berhasil ditambahkan ke tabel `users`.'
                    ];
                    
                    // Add foreign key constraint
                    try {
                        $pdo->exec("ALTER TABLE users ADD CONSTRAINT fk_users_shipping_area FOREIGN KEY (shipping_area_id) REFERENCES shipping_areas(id) ON DELETE SET NULL");
                        $output[] = [
                            'type' => 'success',
                            'title' => 'Constraint Foreign Key Ditambahkan',
                            'desc' => 'Kunci tamu (FK) fk_users_shipping_area berhasil disetup ke tabel `shipping_areas`.'
                        ];
                    } catch (Exception $fkEx) {
                        $output[] = [
                            'type' => 'warning',
                            'title' => 'Foreign Key Gagal',
                            'desc' => 'Kolom ditambahkan, tetapi penambahan foreign key gagal (mungkin sudah ada atau tipe data mismatch): ' . htmlspecialchars($fkEx->getMessage())
                        ];
                    }
                } else {
                    $output[] = [
                        'type' => 'info',
                        'title' => 'Sudah Up-to-Date',
                        'desc' => 'Kolom `shipping_area_id` sudah ada di tabel `users` pada database Anda.'
                    ];
                }
                
                // Print results
                foreach ($output as $out) {
                    $bgColor = 'bg-slate-50 border-slate-200 text-slate-700';
                    $icon = 'ℹ️';
                    if ($out['type'] === 'success') {
                        $bgColor = 'bg-emerald-50 border-emerald-200 text-emerald-800';
                        $icon = '✅';
                    } elseif ($out['type'] === 'warning') {
                        $bgColor = 'bg-amber-50 border-amber-200 text-amber-800';
                        $icon = '⚠️';
                    }
                    
                    echo "<div class='p-4 border rounded-xl {$bgColor} flex gap-3 items-start'>";
                    echo "<span class='text-lg shrink-0'>{$icon}</span>";
                    echo "<div>";
                    echo "<p class='font-bold text-sm'>{$out['title']}</p>";
                    echo "<p class='text-xs mt-0.5 opacity-90'>{$out['desc']}</p>";
                    echo "</div>";
                    echo "</div>";
                }
                
                echo "<div class='mt-6 p-4 bg-blue-50 border border-blue-200 rounded-xl text-blue-800 flex gap-3 items-start'>";
                echo "<span class='text-lg shrink-0'>🎉</span>";
                echo "<div>";
                echo "<p class='font-bold text-sm'>Migrasi Selesai</p>";
                echo "<p class='text-xs mt-0.5 opacity-90'>Database Anda sekarang kompatibel dengan pembaruan profil dan registrasi kecamatan.</p>";
                echo "</div>";
                echo "</div>";
                
            } catch (Exception $e) {
                echo "<div class='p-4 border border-red-200 bg-red-50 text-red-800 rounded-xl flex gap-3 items-start'>";
                echo "<span class='text-lg shrink-0'>❌</span>";
                echo "<div>";
                echo "<p class='font-bold text-sm'>Migrasi Gagal</p>";
                echo "<p class='text-xs mt-0.5 opacity-90'>" . htmlspecialchars($e->getMessage()) . "</p>";
                echo "</div>";
                echo "</div>";
            }
            ?>
        </div>
        
        <p class="text-[10px] text-center text-slate-400 mt-8">
            Silakan hapus file ini dari server Anda setelah migrasi selesai untuk alasan keamanan.
        </p>
    </div>
</body>
</html>
