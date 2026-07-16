<?php
// Disable output buffering if any
while (ob_get_level() > 0) {
    ob_end_clean();
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "Step 1: start<br>";
flush();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['admin_id'] = 1;
$_ENV['DB_HOST'] = '127.0.0.1';
$_SERVER['DB_HOST'] = '127.0.0.1';

echo "Step 2: session set<br>";
flush();

echo "Step 3: requiring db.php<br>";
flush();
require_once __DIR__ . '/../config/db.php';

echo "Step 4: requiring helpers.php<br>";
flush();
require_once __DIR__ . '/../config/helpers.php';

echo "Step 5: requiring admin-auth.php<br>";
flush();
require_once __DIR__ . '/../config/admin-auth.php';

echo "Step 6: requiring import.php<br>";
flush();
require_once __DIR__ . '/../config/import.php';

echo "Step 7: requiring product-import.php<br>";
flush();
require_once 'product-import.php';

echo "Step 8: finished<br>";
flush();
