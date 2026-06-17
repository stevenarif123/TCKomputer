<?php
/**
 * Cart Calculation Action - TC Komputer
 * Receives an array of selected product IDs and calculates
 * the subtotal, discounts, and grand total.
 */

session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

header('Content-Type: application/json');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Metode tidak diizinkan']);
    exit;
}

$selectedIds = $_POST['selected_ids'] ?? [];
if (!is_array($selectedIds)) {
    $selectedIds = [];
}

// Read cart from session
$cart = $_SESSION['cart'] ?? [];

$pdo = getDBConnection();

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

$cartItems = [];
$cartTotal = 0;
$totalItems = 0;

if (!empty($cart)) {
    foreach ($cart as $productId => $item) {
        if (!in_array($productId, $selectedIds)) {
            continue; // Only process selected items
        }

        $stmt = $pdo->prepare(
            "SELECT id, category_id, name, selling_price, promo_price, promo_active, promo_stock, stock, status, image, is_active 
             FROM products WHERE id = ?"
        );
        $stmt->execute([$productId]);
        $product = $stmt->fetch();

        if ($product && $product['is_active']) {
            $isPromo = $isGlobalFlashSaleActive && !empty($product['promo_active']) && !empty($product['promo_price']) && $product['promo_price'] > 0 && isset($product['promo_stock']) && $product['promo_stock'] > 0;
            $currentPrice = $isPromo ? (int)$product['promo_price'] : (int)$product['selling_price'];
            $quantity = (int)$item['quantity'];
            $subtotal = $currentPrice * $quantity;
            $cartTotal += $subtotal;
            $totalItems += $quantity;

            $cartItems[] = [
                'product_id' => (int)$product['id'],
                'category_id' => (int)$product['category_id'],
                'name' => $product['name'],
                'image' => $product['image'],
                'price' => $currentPrice,
                'quantity' => $quantity,
                'subtotal' => $subtotal,
                'stock' => (int)$product['stock'],
                'status' => $product['status'],
            ];
        }
    }
}

// Evaluate Discounts
require_once __DIR__ . '/../promotion/DiscountEngine.php';
$discountEngine = new DiscountEngine($pdo);
$promoResults = $discountEngine->applyDiscounts($cartItems, 0);
$discountAmount = $promoResults['discount_amount'];
$appliedPromos = $promoResults['applied_promotions'];
$freeItemId = $promoResults['free_item_id'];

$freeItemData = null;
if ($freeItemId) {
    $stmtFree = $pdo->prepare("SELECT name, image FROM products WHERE id = ?");
    $stmtFree->execute([$freeItemId]);
    $freeItemData = $stmtFree->fetch();
}

echo json_encode([
    'success' => true,
    'total_items' => $totalItems,
    'subtotal' => $cartTotal,
    'subtotal_formatted' => formatRupiah($cartTotal),
    'discount_amount' => $discountAmount,
    'discount_formatted' => formatRupiah($discountAmount),
    'applied_promos' => implode(', ', $appliedPromos),
    'has_free_item' => $freeItemData !== false && $freeItemData !== null,
    'free_item_name' => $freeItemData ? sanitizeOutput($freeItemData['name']) : '',
    'free_item_image' => $freeItemData && !empty($freeItemData['image']) ? 'uploads/products/' . sanitizeOutput($freeItemData['image']) : 'uploads/products/placeholder.png',
    'grand_total' => $cartTotal - $discountAmount,
    'grand_total_formatted' => formatRupiah($cartTotal - $discountAmount)
]);
