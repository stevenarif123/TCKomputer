<?php
/**
 * TC Komputer - Production Server Diagnostics Tool
 * Displays configuration states, environment variables, database connectivity, and errors.
 */

// Enable full error reporting to screen
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>TC Komputer - Diagnostik Server</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; max-width: 800px; margin: 40px auto; padding: 0 20px; color: #333; }
        h1 { border-bottom: 2px solid #0058be; color: #0058be; padding-bottom: 10px; }
        .section { background: #f8f9ff; border: 1px solid #dce9ff; border-radius: 12px; padding: 20px; margin-bottom: 20px; }
        .status { font-weight: bold; padding: 4px 8px; border-radius: 4px; }
        .status-ok { background: #d4edda; color: #155724; }
        .status-error { background: #f8d7da; color: #721c24; }
        pre { background: #131b2e; color: #fff; padding: 15px; border-radius: 8px; overflow-x: auto; font-family: monospace; }
        .tips { background: #fff3cd; border: 1px solid #ffeeba; color: #856404; padding: 15px; border-radius: 8px; }
        ul { padding-left: 20px; }
        li { margin-bottom: 10px; }
    </style>
</head>
<body>
    <h1>Diagnostik Sistem & Database</h1>

    <div class="section">
        <h2>1. Informasi PHP & Server</h2>
        <ul>
            <li><strong>Versi PHP:</strong> <?php echo PHP_VERSION; ?></li>
            <li><strong>Sistem Operasi:</strong> <?php echo PHP_OS; ?></li>
            <li><strong>Server API (SAPI):</strong> <?php echo PHP_SAPI; ?></li>
            <li><strong>PDO Extension:</strong> 
                <?php if (class_exists('PDO')): ?>
                    <span class="status status-ok">Aktif</span>
                <?php else: ?>
                    <span class="status status-error">TIDAK AKTIF</span>
                <?php endif; ?>
            </li>
            <li><strong>PDO MySQL Driver:</strong> 
                <?php if (class_exists('PDO') && in_array('mysql', PDO::getAvailableDrivers())): ?>
                    <span class="status status-ok">Aktif</span>
                <?php else: ?>
                    <span class="status status-error">TIDAK AKTIF</span>
                <?php endif; ?>
            </li>
            <li><strong>putenv() Function:</strong> 
                <?php if (function_exists('putenv')): ?>
                    <span class="status status-ok">Aktif</span>
                <?php else: ?>
                    <span class="status status-error">DITOLAK/TIDAK AKTIF</span>
                <?php endif; ?>
            </li>
            <li><strong>getenv() Function:</strong> 
                <?php if (function_exists('getenv')): ?>
                    <span class="status status-ok">Aktif</span>
                <?php else: ?>
                    <span class="status status-error">DITOLAK/TIDAK AKTIF</span>
                <?php endif; ?>
            </li>
        </ul>
    </div>

    <div class="section">
        <h2>2. Berkas Konfigurasi (.env)</h2>
        <?php
        $envPath = __DIR__ . '/.env';
        if (file_exists($envPath)): ?>
            <span class="status status-ok">Ditemukan (.env)</span>
            <p>Membaca variabel yang terdefinisi di `.env` (Sandi disensor):</p>
            <pre><?php
            $lines = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines !== false) {
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line) || strpos($line, '#') === 0 || strpos($line, '=') === false) {
                        continue;
                    }
                    list($name, $value) = explode('=', $line, 2);
                    $name = trim($name);
                    $value = trim($value);
                    if (stripos($name, 'pass') !== false || stripos($name, 'secret') !== false) {
                        $value = '[SENSOR/DISEMBUNYIKAN]';
                    }
                    echo htmlspecialchars("$name = $value") . "\n";
                }
            }
            ?></pre>
        <?php else: ?>
            <span class="status status-error">Tidak Ditemukan (.env)</span>
            <p class="tips">Harap salin `.env.example` menjadi `.env` di root direktori server Anda dan sesuaikan isinya.</p>
        <?php endif; ?>
    </div>

    <div class="section">
        <h2>3. Pengujian Koneksi Database</h2>
        <?php
        try {
            require_once __DIR__ . '/config/db.php';
            $pdo = getDBConnection();
            
            // Query simple test
            $stmt = $pdo->query("SELECT store_name FROM store_settings LIMIT 1");
            $storeName = $stmt->fetchColumn();
            
            echo '<span class="status status-ok">Koneksi Berhasil!</span>';
            echo '<p>Berhasil membaca tabel store_settings. Nama Toko: <strong>' . htmlspecialchars($storeName ?: 'TC Komputer') . '</strong></p>';
        } catch (Exception $e) {
            echo '<span class="status status-error">Koneksi Gagal!</span>';
            echo '<p>Pesan Error: <pre>' . htmlspecialchars($e->getMessage()) . '</pre></p>';
            $pdo = null;
        } catch (Error $err) {
            echo '<span class="status status-error">Kesalahan Fatal PHP!</span>';
            echo '<p>Pesan Error: <pre>' . htmlspecialchars($err->getMessage()) . '</pre></p>';
            $pdo = null;
        }
        ?>
    </div>

    <?php if (isset($pdo) && $pdo !== null): ?>
    <div class="section">
        <h2>4. Pengujian Query Database (index.php & header.php)</h2>
        <p>Memeriksa ketersediaan tabel dan struktur kolom yang digunakan oleh halaman utama:</p>
        <ul>
            <?php
            $queries = [
                'Store Settings (header.php)' => "SELECT store_name, logo, phone, email, address, flash_sale_end, flash_sale_title, flash_sale_subtitle, flash_sale_active FROM store_settings LIMIT 1",
                'Banners (index.php)' => "SELECT * FROM banners WHERE is_active=1 ORDER BY sort_order ASC",
                'Categories (index.php)' => "SELECT * FROM categories WHERE is_active=1 ORDER BY sort_order ASC",
                'Subnav Categories (header.php)' => "SELECT name, slug FROM categories WHERE is_active = 1 ORDER BY sort_order ASC LIMIT 8",
                'Featured Products (index.php)' => "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.is_featured=1 AND p.is_active=1 ORDER BY p.created_at DESC LIMIT 8",
                'Newest Products (index.php)' => "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.is_active=1 ORDER BY p.created_at DESC LIMIT 8",
                'Flash Sale Products (index.php)' => "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.is_active=1 AND p.promo_active=1 AND p.promo_price > 0 ORDER BY p.id ASC LIMIT 6"
            ];

            foreach ($queries as $name => $sql) {
                try {
                    $stmt = $pdo->query($sql);
                    $rows = $stmt->fetchAll();
                    echo "<li><strong>$name:</strong> <span class=\"status status-ok\">Berhasil (" . count($rows) . " data)</span></li>";
                } catch (Exception $e) {
                    echo "<li><strong>$name:</strong> <span class=\"status status-error\">GAGAL</span><br><pre style=\"margin-top: 5px;\">" . htmlspecialchars($e->getMessage()) . "</pre></li>";
                }
            }
            ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="section tips">
        <h3>💡 Tips jika tetap mendapatkan HTTP 500:</h3>
        <ol>
            <li><strong>Masalah .htaccess:</strong> Beberapa server hosting memblokir perintah <code>Options -Indexes</code>. Coba edit file <code>.htaccess</code> di root folder, temukan baris <code>Options -Indexes</code> dan tambahkan tanda pagar <code>#</code> di depannya menjadi <code># Options -Indexes</code>.</li>
            <li><strong>Log Error:</strong> Periksa berkas <code>error_log</code> yang biasanya dibuat otomatis di root folder situs oleh server untuk melihat letak baris error yang sebenarnya.</li>
        </ol>
    </div>
</body>
</html>
