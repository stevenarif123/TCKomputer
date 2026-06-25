<?php
/**
 * Cart Checkout Preparation - TC Komputer
 * Receives selected items from cart.php and saves them into a session
 * variable before redirecting to checkout.php.
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

$pdo = getDBConnection();
cleanupCartSession($pdo);

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../cart', 'Metode tidak valid', 'error');
}

// Validate CSRF
$csrfToken = $_POST['csrf_token'] ?? '';
if (!validateCSRFToken($csrfToken)) {
    redirect('../cart', 'Sesi telah kadaluarsa, silakan coba lagi', 'error');
}

$selectedItems = $_POST['selected_items'] ?? [];

if (empty($selectedItems) || !is_array($selectedItems)) {
    redirect('../cart', 'Pilih setidaknya satu produk untuk di-checkout', 'warning');
}

// Ensure items are integers and filter out any invalid ones
$validIds = [];
foreach ($selectedItems as $id) {
    $id = (int)$id;
    if ($id > 0 && isset($_SESSION['cart'][$id])) {
        $validIds[] = $id;
    }
}

if (!empty($validIds)) {
    $inClause = implode(',', array_fill(0, count($validIds), '?'));
    try {
        $stmt = $pdo->prepare("SELECT id, status, stock FROM products WHERE id IN ($inClause) AND is_active = 1");
        $stmt->execute($validIds);
        $products = $stmt->fetchAll(PDO::FETCH_UNIQUE);

        $finalValidIds = [];
        foreach ($validIds as $id) {
            if (isset($products[$id])) {
                $prod = $products[$id];
                $quantity = $_SESSION['cart'][$id]['quantity'];

                // Validate status and stock
                if ($prod['status'] !== 'habis') {
                    if ($prod['status'] === 'ready') {
                        if ($prod['stock'] <= 0) {
                            // Skip out of stock
                            continue;
                        }
                        if ($quantity > $prod['stock']) {
                            // Cap quantity to stock
                            $_SESSION['cart'][$id]['quantity'] = (int)$prod['stock'];
                        }
                    }
                    $finalValidIds[] = $id;
                }
            }
        }
        $validIds = $finalValidIds;
    } catch (Exception $e) {
        // Fallback: keep validIds as is if query fails
    }
}

if (empty($validIds)) {
    redirect('../cart', 'Produk yang dipilih tidak valid, habis, atau tidak tersedia', 'error');
}

// === DIAGNOSTIC: Log what items are being set for checkout ===
$debugLog = date('Y-m-d H:i:s') . " === CART-CHECKOUT-PREP DEBUG ===\n";
$debugLog .= "POST selected_items: " . json_encode($_POST['selected_items'] ?? 'NOT SET') . "\n";
$debugLog .= "validIds after filtering: " . json_encode($validIds) . "\n";
$debugLog .= "SESSION cart keys: " . json_encode(array_keys($_SESSION['cart'] ?? [])) . "\n";
$debugLog .= "SESSION checkout_items BEFORE: " . json_encode($_SESSION['checkout_items'] ?? 'NOT SET') . "\n";
$debugLog .= "=== END CART-CHECKOUT-PREP DEBUG ===\n\n";
@file_put_contents(__DIR__ . '/../debug/checkout_debug.log', $debugLog, FILE_APPEND);

// Store the valid selected items into session
$_SESSION['checkout_items'] = $validIds;

// Redirect to checkout
header("Location: ../checkout");
exit;
