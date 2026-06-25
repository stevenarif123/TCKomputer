<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/admin-auth.php';

requireAdmin();

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$response = ['success' => false, 'message' => 'Invalid action', 'details' => []];

function addResult(&$response, $name, $status, $message = '') {
    $response['details'][] = [
        'name' => $name,
        'status' => $status ? 'PASS' : 'FAIL',
        'message' => $message
    ];
    if (!$status) {
        $response['success'] = false;
    }
}

try {
    switch ($action) {
        case 'env':
            $response['success'] = true;
            // Check PHP Version
            $phpVersion = PHP_VERSION;
            $phpPass = version_compare($phpVersion, '8.0.0', '>=');
            addResult($response, 'PHP Version (>= 8.0)', $phpPass, "Current version: $phpVersion");

            // Check Uploads Directory
            $uploadDir = __DIR__ . '/../uploads';
            $uploadPass = is_writable($uploadDir);
            addResult($response, 'Uploads Directory Writable', $uploadPass, $uploadPass ? 'OK' : 'Check permissions for /uploads');

            // Check .env
            $envPath = __DIR__ . '/../.env';
            $envPass = file_exists($envPath);
            addResult($response, '.env Config File Exists', $envPass, $envPass ? 'OK' : 'Missing .env file');
            break;

        case 'db':
            $response['success'] = true;
            $pdo = getDBConnection();
            addResult($response, 'Database Connection', true, 'PDO Connected');

            // Check Foreign Key
            $stmt = $pdo->query("
                SELECT CONSTRAINT_NAME 
                FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'order_items' 
                AND COLUMN_NAME = 'order_id'
            ");
            $fk = $stmt->fetchColumn();
            addResult($response, 'Foreign Key Constraint (order_items)', (bool)$fk, $fk ? 'Found: ' . $fk : 'Missing constraint');

            // Check Orphaned Items
            $stmt = $pdo->query("SELECT COUNT(*) FROM order_items WHERE order_id NOT IN (SELECT id FROM orders)");
            $orphans = (int)$stmt->fetchColumn();
            addResult($response, 'Orphaned Order Items', $orphans === 0, $orphans === 0 ? 'Clean' : "Found $orphans ghost items");

            // Check minimal config
            $adminCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
            addResult($response, 'Admin Account Exists', $adminCount > 0, "Admins found: $adminCount");

            $shippingCount = $pdo->query("SELECT COUNT(*) FROM shipping_areas")->fetchColumn();
            addResult($response, 'Shipping Areas Configured', $shippingCount > 0, "Areas found: $shippingCount");
            break;

        case 'e2e':
            $response['success'] = true;
            $baseUrl = 'http://' . $_SERVER['HTTP_HOST'] . str_replace('/admin/api-tester.php', '', $_SERVER['PHP_SELF']);
            
            $pagesToTest = [
                '/' => 'Home Page',
                '/products' => 'Products Catalog',
                '/categories' => 'Categories List',
                '/cart' => 'Shopping Cart',
                '/faq' => 'FAQ Page'
            ];

            foreach ($pagesToTest as $path => $name) {
                $url = $baseUrl . $path;
                
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                $html = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                $pass = ($httpCode >= 200 && $httpCode < 400);
                $errorMsg = '';
                
                if ($pass && $html) {
                    if (strpos(strtolower($html), 'fatal error') !== false || strpos(strtolower($html), 'parse error') !== false) {
                        $pass = false;
                        $errorMsg = 'Found PHP Error in output';
                    }
                }

                if (!$pass) {
                    $errorMsg = $errorMsg ?: "HTTP Error Code: $httpCode";
                }

                addResult($response, "Page Load: $name", $pass, $pass ? "HTTP $httpCode OK" : $errorMsg);
            }
            break;

        case 'unit':
            $response['success'] = true;
            $testDir = __DIR__ . '/../testing';
            $phpunit = $testDir . '/phpunit.phar';
            
            if (file_exists($phpunit)) {
                $output = [];
                $returnVar = 0;
                
                $cmd = "php \"$phpunit\" --configuration \"$testDir/phpunit.xml\" 2>&1";
                exec($cmd, $output, $returnVar);
                
                if ($returnVar === 0) {
                    addResult($response, 'PHPUnit Business Logic Tests', true, 'All tests passed successfully');
                } else {
                    $lastLine = end($output) ?: 'Unknown failure';
                    foreach (array_reverse($output) as $line) {
                        if (strpos($line, 'Tests:') !== false || strpos($line, 'ERRORS!') !== false || strpos($line, 'FAILURES!') !== false) {
                            $lastLine = $line;
                            break;
                        }
                    }
                    addResult($response, 'PHPUnit Business Logic Tests', false, "Tests failed: " . strip_tags($lastLine));
                }
            } else {
                addResult($response, 'PHPUnit Binary Found', false, "phpunit.phar not found in $testDir");
            }
            break;

        default:
            throw new Exception("Invalid test action");
    }
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
