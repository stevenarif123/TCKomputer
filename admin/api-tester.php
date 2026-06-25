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
            $adminCount = $pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
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
            
            if (!file_exists($phpunit)) {
                $phpunit = __DIR__ . '/../vendor/bin/phpunit';
            }
            
            if (file_exists($phpunit)) {
                $output = [];
                $returnVar = 0;
                
                $cmd = "php \"$phpunit\" --configuration \"$testDir/phpunit.xml\" 2>&1";
                exec($cmd, $output, $returnVar);
                
                if ($returnVar === 0) {
                    addResult($response, 'PHPUnit Business Logic Tests', true, 'All tests passed successfully');
                } else {
                    // Extract full error instead of just the last line to help debugging
                    $errorText = 'Unknown failure';
                    if (!empty($output)) {
                        $errorText = implode("<br>", array_slice($output, -5)); // get last 5 lines for context
                    }
                    addResult($response, 'PHPUnit Business Logic Tests', false, "Tests failed: " . $errorText);
                }
            } else {
                addResult($response, 'PHPUnit Binary Found', false, "phpunit.phar not found in $testDir");
            }
            break;

        case 'realtime-e2e':
            $response['success'] = true;
            $pdo = getDBConnection();
            $baseUrl = 'http://' . $_SERVER['HTTP_HOST'] . str_replace('/admin/api-tester.php', '', $_SERVER['PHP_SELF']);
            $cookieFile = tempnam(sys_get_temp_dir(), 'e2e_cookie');

            $doRequest = function($url, $postData = null) use ($cookieFile) {
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
                curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
                if ($postData !== null) {
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($postData) ? http_build_query($postData) : $postData);
                }
                $html = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                return ['code' => $httpCode, 'html' => $html];
            };

            $getCsrf = function($url) use ($doRequest) {
                $res = $doRequest($url);
                if (preg_match('/name="csrf_token" value="(.*?)"/', $res['html'], $matches)) {
                    return $matches[1];
                }
                return null;
            };

            try {
                // 1. Get Setup Data
                $shippingId = $pdo->query("SELECT id FROM shipping_areas LIMIT 1")->fetchColumn();
                if (!$shippingId) throw new Exception("No shipping area found in DB");

                $dummyEmail = 'robot_e2e_' . time() . '@test.com';
                $dummyPhone = '0812' . rand(10000000, 99999999);

                // 2. Register
                // Registration and Login forms are in the header modal, so we can get CSRF from index.php
                $csrf = $getCsrf($baseUrl . '/index.php');
                if (!$csrf) throw new Exception("Failed to get CSRF token from index.php");
                
                $res = $doRequest($baseUrl . '/actions/profile-register.php', [
                    'csrf_token' => $csrf,
                    'username' => 'robot_' . time(),
                    'name' => 'Tester Robot',
                    'email' => $dummyEmail,
                    'phone' => $dummyPhone,
                    'password' => 'password123',
                    'address' => 'Jl. E2E Testing No 1',
                    'shipping_area_id' => $shippingId
                ]);
                $respData = json_decode($res['html'], true);
                $regPass = ($respData['success'] ?? false) === true;
                addResult($response, 'Register Flow', $regPass, $regPass ? "Account created: $dummyEmail" : "Failed: " . ($respData['message'] ?? 'Unknown error'));
                if (!$regPass) throw new Exception("Registration failed");

                // 3. Login
                $csrf = $getCsrf($baseUrl . '/index.php');
                $res = $doRequest($baseUrl . '/actions/profile-login.php', [
                    'csrf_token' => $csrf,
                    'email' => $dummyEmail,
                    'password' => 'password123'
                ]);
                // Follow the redirect to check session
                $profRes = $doRequest($baseUrl . '/profile.php');
                $logPass = strpos(strtolower($profRes['html']), 'profil') !== false || strpos(strtolower($profRes['html']), 'keluar') !== false;
                addResult($response, 'Login Flow', $logPass, $logPass ? "Logged in successfully" : "Failed to login");
                if (!$logPass) throw new Exception("Login failed");

                // 4. Add to Cart
                $productId = $pdo->query("SELECT id FROM products WHERE stock > 0 AND status='published' LIMIT 1")->fetchColumn();
                if (!$productId) throw new Exception("No available products to test cart");

                $csrf = $getCsrf($baseUrl . '/product-detail.php?id=' . $productId);
                $res = $doRequest($baseUrl . '/actions/cart-add.php', [
                    'csrf_token' => $csrf,
                    'product_id' => $productId,
                    'quantity' => 1
                ]);
                $cartRes = $doRequest($baseUrl . '/cart.php');
                $cartPass = strpos($cartRes['html'], 'checkout') !== false;
                addResult($response, 'Add to Cart Flow', $cartPass, $cartPass ? "Product $productId added to cart" : "Cart is empty");
                if (!$cartPass) throw new Exception("Cart flow failed");

                // 5. Checkout Prep
                $csrf = $getCsrf($baseUrl . '/cart.php');
                // Build query string manually for array parameter
                $postPrep = "csrf_token=" . urlencode($csrf) . "&selected_items%5B%5D=" . urlencode($productId);
                $doRequest($baseUrl . '/actions/cart-checkout-prep.php', $postPrep);

                // 6. Checkout Process
                $csrf = $getCsrf($baseUrl . '/checkout.php');
                $doRequest($baseUrl . '/actions/checkout-process.php', [
                    'csrf_token' => $csrf,
                    'buyer_name' => 'Tester Robot',
                    'buyer_phone' => $dummyPhone,
                    'buyer_address' => 'Jl. E2E Testing No 1',
                    'shipping_area_id' => $shippingId,
                    'shipping_option' => 'local_courier',
                    'payment_method' => 'cod'
                ]);

                // 7. Verify Order Creation
                $orderId = $pdo->query("SELECT id FROM orders WHERE buyer_phone = '$dummyPhone' AND buyer_name = 'Tester Robot' ORDER BY id DESC LIMIT 1")->fetchColumn();
                $chkPass = (bool)$orderId;
                addResult($response, 'Checkout Flow', $chkPass, $chkPass ? "Order #$orderId created successfully" : "Failed to create order");

                // 8. Cleanup Database
                if ($orderId) {
                    $pdo->exec("DELETE FROM orders WHERE id = $orderId");
                }
                $pdo->exec("DELETE FROM users WHERE email = '$dummyEmail'");
                addResult($response, 'Database Cleanup', true, "Removed test order and user");

            } catch (Exception $e) {
                addResult($response, 'E2E Execution Error', false, $e->getMessage());
            }

            @unlink($cookieFile);
            break;

        default:
            throw new Exception("Invalid test action");
    }
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
