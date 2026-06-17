<?php
/**
 * Checkout Page - TC Komputer
 * Shopee-style simplified checkout: single column, mobile-first,
 * sticky bottom bar, auto-fill profile, inline product list.
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include configuration files
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/helpers.php';

// Redirect to cart if cart is empty
if (empty($_SESSION['cart'])) {
    redirect('cart.php', 'Keranjang belanja kosong. Silakan tambahkan produk terlebih dahulu.', 'warning');
}

// Redirect to cart if customer profile is not set (not logged in)
if (empty($_SESSION['customer_profile'])) {
    redirect('cart.php', 'Silakan masuk / atur profil Anda terlebih dahulu sebelum melakukan checkout.', 'warning');
}

// Redirect to cart if no items were selected for checkout
$checkoutItems = $_SESSION['checkout_items'] ?? [];
if (empty($checkoutItems)) {
    redirect('cart.php', 'Silakan pilih produk yang ingin di-checkout.', 'warning');
}

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

// Fetch cart items with current prices from database
$cart = $_SESSION['cart'];
$cartItems = [];
$subtotal = 0;
$totalQty = 0;

foreach ($cart as $productId => $item) {
    if (!in_array($productId, $checkoutItems)) {
        continue;
    }

    $stmt = $pdo->prepare(
        "SELECT id, category_id, name, selling_price, promo_price, promo_active, promo_stock, stock, status, image, is_active 
         FROM products WHERE id = ? AND is_active = 1"
    );
    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    if ($product) {
        $isPromo = $isGlobalFlashSaleActive && !empty($product['promo_active']) && !empty($product['promo_price']) && $product['promo_price'] > 0 && isset($product['promo_stock']) && $product['promo_stock'] > 0;
        $currentPrice = $isPromo ? (int)$product['promo_price'] : (int)$product['selling_price'];
        $quantity = (int)$item['quantity'];
        $itemSubtotal = $currentPrice * $quantity;
        $subtotal += $itemSubtotal;
        $totalQty += $quantity;

        $cartItems[] = [
            'product_id' => (int)$product['id'],
            'category_id' => (int)$product['category_id'],
            'name' => $product['name'],
            'image' => $product['image'],
            'price' => $currentPrice,
            'quantity' => $quantity,
            'subtotal' => $itemSubtotal,
        ];
    }
}

require_once __DIR__ . '/promotion/DiscountEngine.php';
$discountEngine = new DiscountEngine($pdo);
$promoResults = $discountEngine->applyDiscounts($cartItems, 0);
$baseDiscountAmount = $promoResults['discount_amount'];
$appliedPromos = $promoResults['applied_promotions'];

$freeShippingMax = 0;
$fsPromoName = '';
$stmtFsPromo = $pdo->query("SELECT name, discount_value, min_spend FROM promotions WHERE is_active=1 AND promo_type='free_shipping' AND start_date <= NOW() AND end_date >= NOW() LIMIT 1");
$fsPromoDb = $stmtFsPromo->fetch();
if ($fsPromoDb && $subtotal >= $fsPromoDb['min_spend']) {
    $freeShippingMax = (int)$fsPromoDb['discount_value'];
    $fsPromoName = $fsPromoDb['name'];
}

if (empty($cartItems)) {
    redirect('cart.php', 'Produk di keranjang tidak tersedia. Silakan periksa kembali.', 'warning');
}

// Load header after we are sure no redirect is needed
require_once __DIR__ . '/includes/header.php';

// Generate CSRF token
$csrfToken = generateCSRFToken();

// Fetch active shipping areas
$stmtAreas = $pdo->query("SELECT * FROM shipping_areas WHERE is_active = 1 ORDER BY area_name");
$shippingAreas = $stmtAreas->fetchAll();

// Fetch store settings
$stmtStoreInfo = $pdo->query("SELECT bank_account, cod_info, shipping_info, phone FROM store_settings LIMIT 1");
$storeInfo = $stmtStoreInfo->fetch();

// Customer profile
$profile = $_SESSION['customer_profile'];

// Get checkout errors
$checkoutErrors = $_SESSION['checkout_errors'] ?? [];
unset($_SESSION['checkout_errors']);
?>

<style>
    /* ===== Shopee-style Checkout ===== */
    .co-page { max-width: 680px; margin: 0 auto; padding: 16px 16px 100px; }
    
    .co-back { display: inline-flex; align-items: center; gap: 4px; font-size: 13px; font-weight: 600; color: #0058be; text-decoration: none; margin-bottom: 12px; }
    .co-back:hover { text-decoration: underline; }
    
    .co-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        margin-bottom: 12px;
        overflow: hidden;
    }
    .co-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 16px;
        border-bottom: 1px solid #f3f4f6;
    }
    .co-card-header h2 {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
        font-weight: 700;
        color: #0b1c30;
        margin: 0;
    }
    .co-card-header h2 .material-symbols-outlined { font-size: 20px; color: #0058be; }
    .co-card-body { padding: 14px 16px; }
    
    /* Form fields */
    .co-field { margin-bottom: 12px; }
    .co-field label { display: block; font-size: 11px; font-weight: 700; color: #45464d; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
    .co-field input, .co-field textarea, .co-field select {
        width: 100%; padding: 10px 12px; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 14px;
        font-family: 'Inter', sans-serif; color: #0b1c30; background: #fff; outline: none; transition: border-color 0.15s;
        box-sizing: border-box;
    }
    .co-field input:focus, .co-field textarea:focus, .co-field select:focus { border-color: #0058be; }
    .co-field textarea { resize: vertical; min-height: 60px; }
    .co-field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    
    /* Product list */
    .co-product { display: flex; gap: 12px; padding: 12px 0; }
    .co-product + .co-product { border-top: 1px solid #f3f4f6; }
    .co-product-img {
        width: 64px; height: 64px; border-radius: 8px; border: 1px solid #e5e7eb;
        overflow: hidden; flex-shrink: 0; background: #fff; display: flex; align-items: center; justify-content: center; padding: 4px;
    }
    .co-product-img img { width: 100%; height: 100%; object-fit: contain; }
    .co-product-info { flex: 1; min-width: 0; }
    .co-product-name { font-size: 13px; font-weight: 600; color: #0b1c30; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .co-product-meta { font-size: 12px; color: #76777d; margin-top: 2px; }
    .co-product-price { font-size: 14px; font-weight: 800; color: #0058be; margin-top: 4px; }
    
    /* Shipping & notes inside product card */
    .co-section-divider { border-top: 1px solid #f3f4f6; margin-top: 4px; padding-top: 14px; }
    .co-shipping-options { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
    .co-ship-opt {
        display: flex; flex-direction: column; padding: 10px 12px; border: 2px solid #e5e7eb;
        border-radius: 8px; cursor: pointer; transition: all 0.15s; position: relative;
    }
    .co-ship-opt:hover { border-color: #adc6ff; }
    .co-ship-opt.selected { border-color: #0058be; background: #eff4ff; }
    .co-ship-opt input { position: absolute; opacity: 0; pointer-events: none; }
    .co-ship-opt-title { font-size: 13px; font-weight: 700; color: #0b1c30; }
    .co-ship-opt-desc { font-size: 11px; color: #76777d; margin-top: 2px; }
    
    .co-notes-input {
        width: 100%; padding: 8px 12px; border: 1px solid #e5e7eb; border-radius: 8px;
        font-size: 13px; color: #0b1c30; font-family: 'Inter', sans-serif; resize: none;
        background: #fff; outline: none; transition: border-color 0.15s; box-sizing: border-box;
    }
    .co-notes-input:focus { border-color: #0058be; }
    .co-notes-label { font-size: 12px; font-weight: 600; color: #45464d; margin-bottom: 6px; display: flex; align-items: center; gap: 4px; }
    
    /* Payment */
    .co-pay-opt {
        display: flex; align-items: center; gap: 12px; padding: 12px 14px; border: 2px solid #e5e7eb;
        border-radius: 8px; cursor: pointer; transition: all 0.15s; position: relative;
    }
    .co-pay-opt + .co-pay-opt { margin-top: 8px; }
    .co-pay-opt:hover { border-color: #adc6ff; }
    .co-pay-opt.selected { border-color: #0058be; background: #eff4ff; }
    .co-pay-opt input[type="radio"] { width: 18px; height: 18px; accent-color: #0058be; flex-shrink: 0; cursor: pointer; }
    .co-pay-opt .material-symbols-outlined { font-size: 22px; color: #0058be; }
    .co-pay-opt-label { font-size: 13px; font-weight: 700; color: #0b1c30; flex: 1; }
    .co-pay-info { margin-top: 10px; animation: coFadeIn 0.25s ease; }
    
    /* Sticky bottom bar */
    .co-bottom-bar {
        position: fixed; bottom: 0; left: 0; right: 0; z-index: 80;
        background: #fff; border-top: 1px solid #e5e7eb;
        padding: 12px 16px; display: flex; align-items: center; justify-content: flex-end; gap: 16px;
        box-shadow: 0 -2px 12px rgba(0,0,0,0.06);
    }
    .co-bottom-bar .co-total-section { text-align: right; }
    .co-bottom-bar .co-total-label { font-size: 12px; color: #76777d; font-weight: 500; }
    .co-bottom-bar .co-total-value { font-size: 18px; font-weight: 900; color: #0058be; }
    .co-bottom-bar .co-submit-btn {
        padding: 12px 32px; background: #0058be; color: #fff; border: none; border-radius: 8px;
        font-size: 14px; font-weight: 800; cursor: pointer; transition: all 0.2s;
        display: flex; align-items: center; gap: 6px; white-space: nowrap;
    }
    .co-bottom-bar .co-submit-btn:hover { background: #004395; }
    .co-bottom-bar .co-submit-btn:disabled { opacity: 0.6; cursor: not-allowed; }
    .co-bottom-bar .co-submit-btn .co-spinner {
        display: none; width: 18px; height: 18px; border: 2px solid #fff; border-top-color: transparent;
        border-radius: 50%; animation: coSpin 0.6s linear infinite;
    }
    
    /* Error alert */
    .co-error-alert {
        background: #fef2f2; border: 1px solid #fecaca; border-radius: 10px; padding: 14px 16px;
        margin-bottom: 12px; display: flex; align-items: flex-start; gap: 10px;
    }
    .co-error-alert .material-symbols-outlined { color: #dc2626; font-size: 20px; flex-shrink: 0; margin-top: 1px; }
    .co-error-alert ul { margin: 4px 0 0; padding-left: 16px; font-size: 13px; color: #991b1b; line-height: 1.6; }
    .co-error-alert h4 { font-size: 13px; font-weight: 700; color: #991b1b; margin: 0; }
    
    /* Summary row */
    .co-summary { padding: 14px 16px; border-top: 1px solid #f3f4f6; }
    .co-summary-row { display: flex; justify-content: space-between; align-items: center; font-size: 13px; padding: 3px 0; }
    .co-summary-row .co-label { color: #76777d; }
    .co-summary-row .co-value { color: #0b1c30; font-weight: 600; }
    .co-summary-row.co-total { padding-top: 10px; margin-top: 8px; border-top: 1px dashed #e5e7eb; }
    .co-summary-row.co-total .co-label { font-weight: 700; color: #0b1c30; font-size: 14px; }
    .co-summary-row.co-total .co-value { font-weight: 900; color: #0058be; font-size: 16px; }
    
    @keyframes coFadeIn { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes coSpin { to { transform: rotate(360deg); } }
    
    @media (max-width: 640px) {
        .co-page { padding: 12px 12px 96px; }
        .co-field-row { grid-template-columns: 1fr; }
        .co-shipping-options { grid-template-columns: 1fr; }
        .co-bottom-bar { padding: 10px 12px; gap: 12px; }
        .co-bottom-bar .co-submit-btn { padding: 12px 20px; font-size: 13px; }
        .co-bottom-bar .co-total-value { font-size: 16px; }
    }
</style>

<div class="co-page animate-fade-in-up">
    <!-- Back link -->
    <a href="cart" class="co-back">
        <span class="material-symbols-outlined" style="font-size:18px;">arrow_back</span>
        Kembali ke Keranjang
    </a>

    <h1 style="font-size:22px; font-weight:900; color:#0b1c30; margin:0 0 14px; letter-spacing:-0.3px;">Checkout</h1>

    <?php if (!empty($checkoutErrors)): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const errors = <?= json_encode($checkoutErrors) ?>;
            const msg = errors.join(' | ');
            showToast('Terjadi Kesalahan', msg, 'error');
        });
    </script>
    <?php endif; ?>

    <form action="actions/checkout-process" method="POST" id="checkout-form">
        <input type="hidden" name="csrf_token" value="<?= sanitizeOutput($csrfToken) ?>">

        <!-- ===== CARD 1: Alamat Pengiriman ===== -->
        <div class="co-card" id="address-card">
            <!-- Dotted decorative line at the top, Shopee-style! -->
            <div style="height: 3px; background: repeating-linear-gradient(45deg, #ee4d2d, #ee4d2d 33px, #fff 0, #fff 41px, #0058be 0, #0058be 74px, #fff 0, #fff 82px); width: 100%;"></div>
            <div class="co-card-header" style="border-bottom: none; padding-bottom: 0;">
                <h2>
                    <span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1; color: #ee4d2d;">location_on</span>
                    Alamat Pengiriman
                </h2>
                <button type="button" onclick="openProfileModal(event)" style="font-size: 13px; font-weight: 700; color: #0058be; border: none; background: none; cursor: pointer; display: flex; align-items: center; gap: 4px; padding: 0;">
                    <span class="material-symbols-outlined" style="font-size: 16px;">edit</span> Ubah Alamat
                </button>
            </div>
            <div class="co-card-body" style="padding-top: 10px;">
                <?php
                // Fetch the user's shipping area name
                $userAreaName = 'Belum dipilih';
                $userRegencyName = '';
                $userShippingCost = 0;
                if (!empty($profile['shipping_area_id'])) {
                    $stmtUserArea = $pdo->prepare("SELECT area_name, regency, cost FROM shipping_areas WHERE id = ?");
                    $stmtUserArea->execute([$profile['shipping_area_id']]);
                    $userArea = $stmtUserArea->fetch();
                    if ($userArea) {
                        $userAreaName = $userArea['area_name'];
                        $userRegencyName = $userArea['regency'];
                        $userShippingCost = (int)$userArea['cost'];
                    }
                }
                ?>
                
                <?php if (empty($profile['address']) || empty($profile['shipping_area_id'])): ?>
                    <div style="background: #fff8f8; border: 1px dashed #fecaca; border-radius: 8px; padding: 16px; text-align: center;">
                        <p style="font-size: 13px; color: #dc2626; font-weight: 600; margin: 0 0 8px;">Alamat pengiriman atau area kirim belum diatur!</p>
                        <button type="button" onclick="openProfileModal(event)" style="padding: 6px 16px; background: #ee4d2d; color: #fff; border: none; border-radius: 6px; font-size: 12px; font-weight: 700; cursor: pointer;">
                            Lengkapi Alamat & Area Kirim
                        </button>
                    </div>
                <?php else: ?>
                    <div style="font-size: 13px; color: #0b1c30; line-height: 1.6;">
                        <div style="font-weight: 700; margin-bottom: 4px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                            <span><?= sanitizeOutput($profile['name'] ?? '') ?></span>
                            <span style="color: #d1d5db;">|</span>
                            <span style="font-weight: 500; color: #45464d;"><?= sanitizeOutput($profile['phone'] ?? '') ?></span>
                            <span style="background: #eff4ff; color: #0058be; font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 4px; margin-left: auto;">
                                Area: <?= sanitizeOutput($userRegencyName) ?>, <?= sanitizeOutput($userAreaName) ?>
                            </span>
                        </div>
                        <div style="color: #45464d;"><?= nl2br(sanitizeOutput($profile['address'] ?? '')) ?></div>
                    </div>
                <?php endif; ?>

                <!-- Hidden form inputs to be submitted to checkout-process.php -->
                <input type="hidden" id="buyer_name" name="buyer_name" value="<?= sanitizeOutput($profile['name'] ?? '') ?>">
                <input type="hidden" id="buyer_phone" name="buyer_phone" value="<?= sanitizeOutput($profile['phone'] ?? '') ?>">
                <input type="hidden" id="buyer_address" name="buyer_address" value="<?= sanitizeOutput($profile['address'] ?? '') ?>">
                <select id="shipping_area_id" name="shipping_area_id" style="display: none;" required>
                    <option value="">-- Pilih Area Pengiriman --</option>
                    <?php foreach ($shippingAreas as $area): ?>
                        <option value="<?= (int)$area['id'] ?>" data-cost="<?= (int)$area['cost'] ?>" <?= (isset($profile['shipping_area_id']) && $profile['shipping_area_id'] == $area['id']) ? 'selected' : '' ?>>
                            <?= sanitizeOutput($area['area_name']) ?> - <?= formatRupiah((int)$area['cost']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- ===== CARD 2: Produk Dipesan ===== -->
        <div class="co-card">
            <div class="co-card-header">
                <h2>
                    <span class="material-symbols-outlined">shopping_bag</span>
                    Produk Dipesan
                </h2>
                <span style="font-size:12px; color:#76777d; font-weight:600;"><?= $totalQty ?> barang</span>
            </div>
            <div class="co-card-body">
                <?php foreach ($cartItems as $cartItem): ?>
                <?php $itemImg = !empty($cartItem['image']) ? 'uploads/products/' . $cartItem['image'] : 'uploads/products/placeholder.png'; ?>
                <div class="co-product">
                    <div class="co-product-img">
                        <img alt="<?= sanitizeOutput($cartItem['name']) ?>" src="<?= $itemImg ?>">
                    </div>
                    <div class="co-product-info">
                        <div class="co-product-name"><?= sanitizeOutput($cartItem['name']) ?></div>
                        <div class="co-product-meta">x<?= (int)$cartItem['quantity'] ?></div>
                        <div class="co-product-price"><?= formatRupiah((int)$cartItem['subtotal']) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- Opsi Pengiriman -->
                <div class="co-section-divider">
                    <div class="co-notes-label">
                        <span class="material-symbols-outlined" style="font-size:16px; color:#0058be;">local_shipping</span>
                        Opsi Pengiriman
                    </div>
                    <div class="co-shipping-options">
                        <label class="co-ship-opt selected" onclick="selectShipOpt(this)">
                            <input checked name="shipping_option" type="radio" value="local_courier" required>
                            <span class="co-ship-opt-title">Diantar Kurir</span>
                            <span class="co-ship-opt-desc">Dikirim tim kurir TC Komputer</span>
                        </label>
                        <label class="co-ship-opt" onclick="selectShipOpt(this)">
                            <input name="shipping_option" type="radio" value="self_pickup">
                            <span class="co-ship-opt-title">Ambil Sendiri</span>
                            <span class="co-ship-opt-desc">Ambil di kantor TC Komputer</span>
                        </label>
                    </div>
                </div>

                <!-- Catatan -->
                <div class="co-section-divider">
                    <div class="co-notes-label">
                        <span class="material-symbols-outlined" style="font-size:16px; color:#76777d;">edit_note</span>
                        Catatan (opsional)
                    </div>
                    <textarea class="co-notes-input" name="order_notes" rows="2" placeholder="Contoh: Tolong packing kayu ya..."></textarea>
                </div>
            </div>

            <!-- Summary -->
            <div class="co-summary">
                <div class="co-summary-row">
                    <span class="co-label">Subtotal (<?= $totalQty ?> barang)</span>
                    <span class="co-value"><?= formatRupiah($subtotal) ?></span>
                </div>
                
                <div class="co-summary-row" id="co-discount-row" style="display: <?= ($baseDiscountAmount > 0) ? 'flex' : 'none' ?>;">
                    <span class="co-label" style="color: #ba1a1a;">Diskon Produk</span>
                    <span class="co-value" style="color: #ba1a1a;">- <?= formatRupiah($baseDiscountAmount) ?></span>
                </div>
                
                <div class="co-summary-row">
                    <span class="co-label">Ongkos Kirim</span>
                    <span class="co-value" id="co-shipping-display">Rp 0</span>
                </div>
                
                <div class="co-summary-row" id="co-freeship-row" style="display: none;">
                    <span class="co-label" style="color: #ba1a1a;">Diskon Ongkir</span>
                    <span class="co-value" id="co-freeship-display" style="color: #ba1a1a;">- Rp 0</span>
                </div>
                
                <div class="co-summary-row co-total">
                    <span class="co-label">Total Pesanan</span>
                    <span class="co-value" id="co-total-display"><?= formatRupiah($subtotal - $baseDiscountAmount) ?></span>
                </div>
                
                <?php
                $allPromos = $appliedPromos;
                if ($freeShippingMax > 0) {
                    $allPromos[] = $fsPromoName;
                }
                $allPromos = array_unique($allPromos);
                if (!empty($allPromos)):
                ?>
                <div style="font-size: 11px; color: #ba1a1a; margin-top: 8px; text-align: right;">
                    Promo Aktif: <?= sanitizeOutput(implode(', ', $allPromos)) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ===== CARD 3: Metode Pembayaran ===== -->
        <div class="co-card">
            <div class="co-card-header">
                <h2>
                    <span class="material-symbols-outlined">payments</span>
                    Metode Pembayaran
                </h2>
            </div>
            <div class="co-card-body">
                <label class="co-pay-opt selected" onclick="selectPayOpt(this)">
                    <input checked name="payment_method" type="radio" value="transfer" required>
                    <span class="material-symbols-outlined">account_balance</span>
                    <span class="co-pay-opt-label">Transfer Bank</span>
                </label>
                <label class="co-pay-opt" onclick="selectPayOpt(this)">
                    <input name="payment_method" type="radio" value="cod">
                    <span class="material-symbols-outlined">payments</span>
                    <span class="co-pay-opt-label">COD (Bayar di Tempat)</span>
                </label>

                <div id="co-pay-info"></div>
            </div>
        </div>

        <!-- Bank Info Card (below payment) -->
        <?php if (!empty($storeInfo['bank_account'])): ?>
        <div class="co-card" id="co-bank-card">
            <div class="co-card-body" style="display:flex; gap:10px; align-items:flex-start;">
                <span class="material-symbols-outlined" style="color:#0058be; font-size:20px; margin-top:1px;">account_balance</span>
                <div>
                    <div style="font-size:13px; font-weight:700; color:#0b1c30; margin-bottom:4px;">Informasi Rekening Bank</div>
                    <div style="font-size:12px; color:#45464d; line-height:1.6;"><?= nl2br(sanitizeOutput($storeInfo['bank_account'])) ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </form>
</div>

<!-- ===== STICKY BOTTOM BAR ===== -->
<div class="co-bottom-bar">
    <div class="co-total-section">
        <div class="co-total-label">Total Pesanan</div>
        <div class="co-total-value" id="co-bottom-total"><?= formatRupiah($subtotal) ?></div>
    </div>
    <button type="button" class="co-submit-btn" id="co-submit-btn" onclick="submitCheckout(this)">
        <span id="co-btn-text">Buat Pesanan</span>
        <span class="co-spinner" id="co-spinner"></span>
    </button>
</div>

<script>
    const coSubtotal = <?= (int)$subtotal ?>;
    const baseDiscountAmount = <?= (int)$baseDiscountAmount ?>;
    const freeShippingMax = <?= (int)$freeShippingMax ?>;
    const storeBankAccount = <?= json_encode($storeInfo['bank_account'] ?? '') ?>;
    const storePhone = <?= json_encode($storeInfo['phone'] ?? '082293924242') ?>;
    const storeCodInfo = <?= json_encode($storeInfo['cod_info'] ?? '') ?>;

    // ===== Format Currency =====
    function coFormatRp(val) {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val).replace("IDR", "Rp");
    }

    // ===== Shipping Calculation =====
    function updateCalculations() {
        const select = document.getElementById('shipping_area_id');
        const shipOpt = document.querySelector('input[name="shipping_option"]:checked')?.value;
        let shippingCost = 0;
        let shippingDiscount = 0;

        if (shipOpt === 'local_courier' && select && select.selectedIndex > 0) {
            shippingCost = parseInt(select.options[select.selectedIndex].getAttribute('data-cost'), 10) || 0;
        }

        // Apply free shipping promo
        if (shippingCost > 0 && freeShippingMax > 0) {
            shippingDiscount = Math.min(shippingCost, freeShippingMax);
            document.getElementById('co-freeship-row').style.display = 'flex';
            document.getElementById('co-freeship-display').textContent = '- ' + coFormatRp(shippingDiscount);
        } else {
            document.getElementById('co-freeship-row').style.display = 'none';
        }

        const total = coSubtotal - baseDiscountAmount + shippingCost - shippingDiscount;

        document.getElementById('co-shipping-display').textContent = coFormatRp(shippingCost);
        document.getElementById('co-total-display').textContent = coFormatRp(total);
        document.getElementById('co-bottom-total').textContent = coFormatRp(total);
    }

    document.getElementById('shipping_area_id').addEventListener('change', updateCalculations);

    // ===== Shipping Option Selection =====
    function selectShipOpt(el) {
        document.querySelectorAll('.co-ship-opt').forEach(opt => {
            opt.classList.remove('selected');
            opt.querySelector('input').checked = false;
        });
        el.classList.add('selected');
        el.querySelector('input').checked = true;
        updateCalculations();
    }

    // ===== Payment Selection =====
    function selectPayOpt(el) {
        document.querySelectorAll('.co-pay-opt').forEach(opt => {
            opt.classList.remove('selected');
            opt.querySelector('input[type="radio"]').checked = false;
        });
        el.classList.add('selected');
        el.querySelector('input[type="radio"]').checked = true;
        updatePayInfo();
    }

    function updatePayInfo() {
        const method = document.querySelector('input[name="payment_method"]:checked')?.value;
        const box = document.getElementById('co-pay-info');
        const bankCard = document.getElementById('co-bank-card');

        if (method === 'transfer') {
            box.innerHTML = `
                <div class="co-pay-info" style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:12px 14px; display:flex; gap:8px; align-items:flex-start;">
                    <span class="material-symbols-outlined" style="color:#2563eb; font-size:18px; margin-top:1px; font-variation-settings:'FILL' 1;">info</span>
                    <div style="font-size:12px; color:#1e40af; line-height:1.6;">
                        <strong>Petunjuk Transfer:</strong><br>
                        Setelah mengonfirmasi pesanan, lakukan transfer ke rekening yang tertera di bawah. Lalu kirim bukti transfer ke WhatsApp <strong>${storePhone}</strong>.
                    </div>
                </div>`;
            if (bankCard) bankCard.style.display = '';
        } else if (method === 'cod') {
            box.innerHTML = `
                <div class="co-pay-info" style="background:#fff7ed; border:1px solid #fed7aa; border-radius:8px; padding:12px 14px; display:flex; gap:8px; align-items:flex-start;">
                    <span class="material-symbols-outlined" style="color:#ea580c; font-size:18px; margin-top:1px; font-variation-settings:'FILL' 1;">local_shipping</span>
                    <div style="font-size:12px; color:#9a3412; line-height:1.6;">
                        <strong>Pembayaran COD:</strong><br>
                        ${storeCodInfo ? storeCodInfo.replace(/\n/g, '<br>') : 'Bayar tunai langsung kepada kurir saat pesanan tiba. Mohon siapkan uang pas.'}
                    </div>
                </div>`;
            if (bankCard) bankCard.style.display = 'none';
        } else {
            box.innerHTML = '';
            if (bankCard) bankCard.style.display = '';
        }
    }

    // ===== Submit =====
    function submitCheckout(btn) {
        const form = document.getElementById('checkout-form');

        // Custom validation check for address and area kirim
        const areaId = document.getElementById('shipping_area_id').value;
        const address = document.getElementById('buyer_address').value;
        const name = document.getElementById('buyer_name').value;
        const phone = document.getElementById('buyer_phone').value;

        if (!areaId || !address || !name || !phone) {
            if (typeof showToast === 'function') {
                showToast("Alamat Belum Lengkap", "Silakan lengkapi alamat dan area pengiriman Anda terlebih dahulu.");
            }
            openProfileModal({ preventDefault: () => {} });
            return;
        }

        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        // Show spinner
        document.getElementById('co-btn-text').style.display = 'none';
        document.getElementById('co-spinner').style.display = 'block';
        btn.disabled = true;

        if (typeof showToast === 'function') {
            showToast("Memproses", "Memproses pesanan Anda...");
        }

        setTimeout(() => { form.submit(); }, 600);
    }

    // ===== Init =====
    window.addEventListener('load', () => {
        updateCalculations();
        updatePayInfo();
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
