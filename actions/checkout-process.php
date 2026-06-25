<?php
/**
 * Checkout Process Action
 * Handles the complete checkout flow: validation, order creation, stock update.
 * Only accepts POST requests with valid CSRF token.
 */

session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

$pdo = getDBConnection();
cleanupCartSession($pdo);

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../checkout', 'Metode request tidak valid.', 'error');
}

// Validate CSRF token
$csrfToken = $_POST['csrf_token'] ?? '';
if (!validateCSRFToken($csrfToken)) {
    redirect('../checkout', 'Token keamanan tidak valid. Silakan coba lagi.', 'error');
}

// Check cart is not empty and checkout items exist
if (empty($_SESSION['cart']) || empty($_SESSION['checkout_items'])) {
    redirect('../cart', 'Keranjang belanja atau pilihan produk kosong.', 'error');
}

$checkoutItems = $_SESSION['checkout_items'];

// ============================================
// Input Validation
// ============================================
$errors = [];

// Buyer name: trim, required, 3-100 chars
$buyerName = trim($_POST['buyer_name'] ?? '');
if (empty($buyerName)) {
    $errors[] = 'Nama pembeli wajib diisi.';
} elseif (mb_strlen($buyerName, 'UTF-8') < 3 || mb_strlen($buyerName, 'UTF-8') > 100) {
    $errors[] = 'Nama pembeli harus 3-100 karakter.';
}

// Buyer phone: required, valid Indonesian phone
$buyerPhone = trim($_POST['buyer_phone'] ?? '');
if (empty($buyerPhone)) {
    $errors[] = 'Nomor telepon wajib diisi.';
} elseif (!isValidPhoneNumber($buyerPhone)) {
    $errors[] = 'Format nomor telepon tidak valid (gunakan format 08xx atau +628xx).';
}

// Buyer address: trim, required, 10-500 chars
$buyerAddress = trim($_POST['buyer_address'] ?? '');
if (empty($buyerAddress)) {
    $errors[] = 'Alamat wajib diisi.';
} elseif (mb_strlen($buyerAddress, 'UTF-8') < 10) {
    $errors[] = 'Alamat minimal 10 karakter.';
} elseif (mb_strlen($buyerAddress, 'UTF-8') > 500) {
    $errors[] = 'Alamat maksimal 500 karakter.';
}

// Shipping area: required, must be active shipping area
$shippingAreaId = (int)($_POST['shipping_area_id'] ?? 0);
if ($shippingAreaId <= 0) {
    $errors[] = 'Area pengiriman wajib dipilih.';
}

// Payment method: required, must be cod|transfer
$paymentMethod = $_POST['payment_method'] ?? '';
$validPaymentMethods = ['cod', 'transfer'];
if (empty($paymentMethod) || !in_array($paymentMethod, $validPaymentMethods, true)) {
    $errors[] = 'Metode pembayaran tidak valid.';
}

// Shipping option: required, must be self_pickup|local_courier
$shippingOption = $_POST['shipping_option'] ?? '';
$validShippingOptions = ['self_pickup', 'local_courier'];
if (empty($shippingOption) || !in_array($shippingOption, $validShippingOptions, true)) {
    $errors[] = 'Opsi pengiriman tidak valid.';
}

// If validation errors exist, redirect back
if (!empty($errors)) {
    $_SESSION['checkout_errors'] = $errors;
    redirect('../checkout');
}

// ============================================
// Database Validation & Order Processing
// ============================================

// Validate shipping area exists and is active
$stmt = $pdo->prepare("SELECT id, cost FROM shipping_areas WHERE id = ? AND is_active = 1");
$stmt->execute([$shippingAreaId]);
$shippingArea = $stmt->fetch();

if (!$shippingArea) {
    $_SESSION['checkout_errors'] = ['Area pengiriman tidak valid atau tidak aktif.'];
    redirect('../checkout');
}

$shippingCost = (int)$shippingArea['cost'];

// Fetch flash sale state
$stmtFs = $pdo->query("SELECT flash_sale_active, flash_sale_end FROM store_settings LIMIT 1");
$fsSettings = $stmtFs->fetch();
$fsSeconds = 0;
if (!empty($fsSettings['flash_sale_end'])) {
    $endTime = strtotime($fsSettings['flash_sale_end']);
    if ($endTime > time()) {
        $fsSeconds = $endTime - time();
    }
}
$isGlobalFlashSaleActive = !empty($fsSettings['flash_sale_active']) && $fsSeconds > 0;

// Re-validate cart items are still purchasable
$subtotal = 0;
$validatedItems = [];

foreach ($checkoutItems as $productId) {
    if (!isset($_SESSION['cart'][$productId])) continue;
    $item = $_SESSION['cart'][$productId];

    $stmt = $pdo->prepare(
        "SELECT id, category_id, name, selling_price, promo_price, promo_active, promo_stock, stock, status, is_active 
         FROM products WHERE id = ? AND is_active = 1"
    );
    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    // Product must exist and be active
    if (!$product) {
        unset($_SESSION['cart'][$productId]);
        if (($key = array_search($productId, $_SESSION['checkout_items'])) !== false) {
            unset($_SESSION['checkout_items'][$key]);
            $_SESSION['checkout_items'] = array_values($_SESSION['checkout_items']);
        }
        $_SESSION['checkout_errors'] = ['Produk tidak tersedia: ' . sanitizeOutput($item['name'])];
        redirect('../checkout');
    }

    // Product must be purchasable (ready or po, not habis)
    if ($product['status'] === 'habis') {
        if (($key = array_search($productId, $_SESSION['checkout_items'])) !== false) {
            unset($_SESSION['checkout_items'][$key]);
            $_SESSION['checkout_items'] = array_values($_SESSION['checkout_items']);
        }
        $_SESSION['checkout_errors'] = ['Produk habis: ' . sanitizeOutput($product['name'])];
        redirect('../checkout');
    }

    // For ready products, check stock sufficiency
    if ($product['status'] === 'ready' && $product['stock'] < $item['quantity']) {
        $_SESSION['cart'][$productId]['quantity'] = (int)$product['stock'];
        if ($product['stock'] <= 0) {
            if (($key = array_search($productId, $_SESSION['checkout_items'])) !== false) {
                unset($_SESSION['checkout_items'][$key]);
                $_SESSION['checkout_items'] = array_values($_SESSION['checkout_items']);
            }
        }
        $_SESSION['checkout_errors'] = ['Stok tidak cukup untuk: ' . sanitizeOutput($product['name']) . ' (tersedia: ' . $product['stock'] . ')'];
        redirect('../checkout');
    }

    $isPromo = $isGlobalFlashSaleActive && !empty($product['promo_active']) && !empty($product['promo_price']) && $product['promo_price'] > 0 && isset($product['promo_stock']) && $product['promo_stock'] > 0;
    $currentPrice = $isPromo ? (int)$product['promo_price'] : (int)$product['selling_price'];

    $itemSubtotal = $currentPrice * (int)$item['quantity'];
    $subtotal += $itemSubtotal;

    $validatedItems[] = [
        'product_id' => (int)$product['id'],
        'category_id' => (int)$product['category_id'],
        'product_name' => $product['name'],
        'product_price' => $currentPrice,
        'quantity' => (int)$item['quantity'],
        'subtotal' => $itemSubtotal,
        'status' => $product['status'],
        'is_promo' => $isPromo,
    ];
}

require_once __DIR__ . '/../promotion/DiscountEngine.php';
$effectiveShippingCost = ($shippingOption === 'self_pickup') ? 0 : $shippingCost;
$discountEngine = new DiscountEngine($pdo);
$promoResults = $discountEngine->applyDiscounts($validatedItems, $effectiveShippingCost);

$discountAmount = $promoResults['discount_amount'];
$appliedPromotions = !empty($promoResults['applied_promotions']) ? implode(', ', $promoResults['applied_promotions']) : null;
$freeItemId = $promoResults['free_item_id'];
$finalShippingCost = $promoResults['new_shipping_cost'];

$serviceFee = 1000; // Biaya Layanan
$total = $subtotal - $discountAmount + $finalShippingCost + $serviceFee;

// ============================================
// Create Order in Transaction
// ============================================
try {
    $pdo->beginTransaction();

    // Generate unique order code
    $orderCode = generateOrderCode($pdo);

    // Determine initial payment status
    $paymentStatus = ($paymentMethod === 'cod') ? 'cod' : 'belum_dibayar';

    // Optional order notes
    $orderNotes = isset($_POST['order_notes']) ? trim($_POST['order_notes']) : null;
    if ($orderNotes === '') {
        $orderNotes = null;
    }

    // Insert order record
    $stmt = $pdo->prepare(
        "INSERT INTO orders (order_code, buyer_name, buyer_phone, buyer_address,
         shipping_area_id, shipping_cost, discount_amount, applied_promotions, subtotal, total, payment_method,
         payment_status, order_status, shipping_option, order_notes, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'menunggu_konfirmasi', ?, ?, NOW())"
    );
    $stmt->execute([
        $orderCode,
        $buyerName,
        $buyerPhone,
        $buyerAddress,
        $shippingAreaId,
        $finalShippingCost,
        $discountAmount,
        $appliedPromotions,
        $subtotal,
        $total,
        $paymentMethod,
        $paymentStatus,
        $shippingOption,
        $orderNotes
    ]);

    $orderId = (int)$pdo->lastInsertId();

    // Insert order items and update stock
    foreach ($validatedItems as $item) {
        // Insert order item
        $stmt = $pdo->prepare(
            "INSERT INTO order_items (order_id, product_id, product_name, 
             product_price, quantity, subtotal)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $orderId,
            $item['product_id'],
            $item['product_name'],
            $item['product_price'],
            $item['quantity'],
            $item['subtotal']
        ]);

        // Decrease stock only for 'ready' products
        if ($item['status'] === 'ready') {
            if ($item['is_promo']) {
                $stmt = $pdo->prepare(
                    "UPDATE products 
                     SET stock = stock - ?, 
                         promo_stock = GREATEST(0, promo_stock - ?), 
                         updated_at = NOW() 
                     WHERE id = ?"
                );
                $stmt->execute([$item['quantity'], $item['quantity'], $item['product_id']]);
            } else {
                $stmt = $pdo->prepare(
                    "UPDATE products SET stock = stock - ?, updated_at = NOW() WHERE id = ?"
                );
                $stmt->execute([$item['quantity'], $item['product_id']]);
            }

            // Auto-set status to 'habis' if stock reaches 0
            $stmt = $pdo->prepare(
                "UPDATE products SET status = 'habis' WHERE id = ? AND stock <= 0"
            );
            $stmt->execute([$item['product_id']]);
        }
    }

    // Insert free item if any
    if ($freeItemId) {
        $stmtFree = $pdo->prepare("SELECT name FROM products WHERE id = ?");
        $stmtFree->execute([$freeItemId]);
        $freeProduct = $stmtFree->fetch();
        if ($freeProduct) {
            $stmt = $pdo->prepare(
                "INSERT INTO order_items (order_id, product_id, product_name, product_price, quantity, subtotal)
                 VALUES (?, ?, ?, 0, 1, 0)"
            );
            $stmt->execute([$orderId, $freeItemId, $freeProduct['name'] . ' (Bonus Gratis)']);
            
            $stmt = $pdo->prepare("UPDATE products SET stock = stock - 1 WHERE id = ?");
            $stmt->execute([$freeItemId]);
        }
    }

    // Commit transaction
    $pdo->commit();

    // Clear cart and checkout state from session
    unset($_SESSION['cart']);
    unset($_SESSION['checkout_items']);
    unset($_SESSION['checkout_errors']);

    // Save/Update Customer Profile in Session
    $_SESSION['customer_profile'] = [
        'name' => $buyerName,
        'email' => $_SESSION['customer_profile']['email'] ?? '',
        'phone' => $buyerPhone,
        'address' => $buyerAddress,
    ];

    // Add Order Code to History in Session
    if (!isset($_SESSION['my_orders'])) {
        $_SESSION['my_orders'] = [];
    }
    if (!in_array($orderCode, $_SESSION['my_orders'], true)) {
        $_SESSION['my_orders'][] = $orderCode;
    }

    // Create Notification about new order
    if (!isset($_SESSION['notifications'])) {
        $_SESSION['notifications'] = [];
    }
    $_SESSION['notifications'][] = [
        'title' => 'Pesanan Baru Berhasil',
        'message' => 'Pesanan Anda dengan kode ' . $orderCode . ' telah berhasil dibuat.',
        'time' => date('H:i'),
        'unread' => true
    ];

    // Redirect to order success page
    redirect('../order-success?code=' . urlencode($orderCode), 'Pesanan berhasil dibuat!', 'success');

} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollBack();

    // Log error for debugging
    error_log('Checkout error: ' . $e->getMessage());

    // Preserve cart, show error
    $_SESSION['checkout_errors'] = ['Terjadi kesalahan saat memproses pesanan. Silakan coba lagi.'];
    redirect('../checkout');
}
