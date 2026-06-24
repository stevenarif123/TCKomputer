<?php
/**
 * Shopping Cart Page - Steven IT Shop
 * Displays cart items with quantities, prices, subtotals, and cart total.
 * Allows quantity update and item removal.
 */

require_once __DIR__ . '/includes/header.php';

// If not coming from a "Buy Now" direct flow, clear the checkout selection so all items are selected by default
if (!isset($_GET['buy_now'])) {
    unset($_SESSION['checkout_items']);
}

// Generate CSRF token
$csrfToken = generateCSRFToken();

// Read cart from session
$cart = $_SESSION['cart'] ?? [];

// Fetch current prices from database
$cartItems = [];
$cartTotal = 0;

if (!empty($cart)) {
    foreach ($cart as $productId => $item) {
        $stmt = $pdo->prepare(
            "SELECT id, category_id, name, slug, selling_price, promo_price, promo_active, promo_stock, stock, status, image, is_active 
             FROM products WHERE id = ?"
        );
        $stmt->execute([$productId]);
        $product = $stmt->fetch();

        if ($product && $product['is_active']) {
            $isPromo = $isGlobalFlashSaleActive && !empty($product['promo_active']) && !empty($product['promo_price']) && $product['promo_price'] > 0 && isset($product['promo_stock']) && $product['promo_stock'] > 0;
            $currentPrice = $isPromo ? (int)$product['promo_price'] : (int)$product['selling_price'];
            $quantity = (int)$item['quantity'];

            // Cap quantity for ready products if it exceeds stock
            if ($product['status'] === 'ready' && $product['stock'] > 0 && $quantity > $product['stock']) {
                $quantity = (int)$product['stock'];
                $_SESSION['cart'][$productId]['quantity'] = $quantity;
            }

            $isOutOfStock = ($product['status'] === 'habis') || ($product['status'] === 'ready' && $product['stock'] <= 0);
            $subtotal = $currentPrice * $quantity;

            // Determine if checked
            $isChecked = false;
            if (!$isOutOfStock) {
                if (isset($_SESSION['checkout_items'])) {
                    $isChecked = in_array((int)$product['id'], $_SESSION['checkout_items']);
                } else {
                    $isChecked = true;
                }
            }

            $cartItems[] = [
                'product_id' => (int)$product['id'],
                'category_id' => (int)$product['category_id'],
                'name' => $product['name'],
                'slug' => $product['slug'],
                'image' => $product['image'],
                'price' => $currentPrice,
                'quantity' => $quantity,
                'subtotal' => $subtotal,
                'stock' => (int)$product['stock'],
                'status' => $product['status'],
                'is_out_of_stock' => $isOutOfStock,
                'is_checked' => $isChecked,
            ];
        }
    }

    // Now calculate checked items totals
    $checkedItems = [];
    $cartCount = 0;
    foreach ($cartItems as $item) {
        if ($item['is_checked']) {
            $cartTotal += $item['subtotal'];
            $cartCount += $item['quantity'];
            $checkedItems[] = $item;
        }
    }

    // Evaluate Discounts on checked items
    require_once __DIR__ . '/promotion/DiscountEngine.php';
    $discountEngine = new DiscountEngine($pdo);
    $promoResults = $discountEngine->applyDiscounts($checkedItems, 0); // Cart doesn't know shipping yet
    $discountAmount = $promoResults['discount_amount'];
    $appliedPromos = $promoResults['applied_promotions'];
    $freeItemId = $promoResults['free_item_id'];
    
    $freeItem = null;
    if ($freeItemId) {
        $stmtFree = $pdo->prepare("SELECT name, image FROM products WHERE id = ?");
        $stmtFree->execute([$freeItemId]);
        $freeItem = $stmtFree->fetch();
    }
}
?>

<div class="max-w-max-width mx-auto px-4 md:px-margin-desktop py-3 md:py-lg animate-fade-in-up">
    <!-- Breadcrumbs -->
    <nav class="flex items-center gap-xs text-body-sm text-on-surface-variant mb-3 md:mb-lg">
        <a class="hover:text-secondary transition-colors" href="index">Beranda</a>
        <span class="material-symbols-outlined text-[16px] text-outline-variant">chevron_right</span>
        <span class="text-on-surface font-semibold">Keranjang Belanja</span>
    </nav>

    <h1 class="text-headline-lg font-black text-on-background tracking-tight mb-3 md:mb-md">Keranjang Belanja</h1>

    <?php if (empty($cartItems)): ?>
        <!-- Empty Cart Message -->
        <div class="bg-white border border-outline-variant/60 rounded-xl p-6 md:p-12 text-center max-w-md mx-auto space-y-md">
            <span class="material-symbols-outlined text-6xl text-on-surface-variant/40">shopping_cart</span>
            <div>
                <h2 class="text-headline-md font-extrabold text-on-background">Keranjang Belanja Kosong</h2>
                <p class="text-body-sm text-on-surface-variant mt-1">Anda belum menambahkan produk apa pun ke keranjang belanja Anda.</p>
            </div>
            <a href="products" class="px-xl py-3 bg-secondary text-white font-bold text-label-md rounded-xl hover:bg-secondary-container transition-all inline-block shadow-md">
                Cari Produk
            </a>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-gutter">
            <!-- Left side: Cart Items List -->
            <div class="lg:col-span-8 space-y-sm">
                <!-- Select All -->
                <?php
                $hasActiveItems = false;
                $allActiveChecked = true;
                foreach ($cartItems as $item) {
                    if (!$item['is_out_of_stock']) {
                        $hasActiveItems = true;
                        if (!$item['is_checked']) {
                            $allActiveChecked = false;
                        }
                    }
                }
                $isSelectAllChecked = $hasActiveItems && $allActiveChecked;
                ?>
                <div class="bg-white p-3 sm:p-4 rounded-xl border border-outline-variant/40 flex items-center gap-3">
                    <input type="checkbox" id="select-all" class="w-5 h-5 rounded border-gray-300 text-secondary focus:ring-secondary cursor-pointer" <?= $isSelectAllChecked ? 'checked' : '' ?>>
                    <label for="select-all" class="font-bold text-body-sm sm:text-body-md text-on-background cursor-pointer select-none">Pilih Semua Produk</label>
                </div>

                <?php foreach ($cartItems as $cartItem): ?>
                <?php
                $itemImg = !empty($cartItem['image']) ? 'uploads/products/' . $cartItem['image'] : 'uploads/products/placeholder.png';
                ?>
                <div class="bg-white p-3 sm:p-4 rounded-xl border border-outline-variant/40 flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4 relative">
                    <div class="flex gap-3 sm:gap-4 w-full">
                        <!-- Checkbox & Image Wrapper -->
                        <div class="flex items-center gap-3 sm:gap-4 shrink-0">
                            <input type="checkbox" name="selected_cart_items[]" value="<?= (int)$cartItem['product_id'] ?>" class="cart-item-checkbox w-5 h-5 rounded border-gray-300 text-secondary focus:ring-secondary cursor-pointer disabled:cursor-not-allowed disabled:opacity-50 shrink-0" <?= $cartItem['is_out_of_stock'] ? 'disabled' : '' ?> <?= $cartItem['is_checked'] ? 'checked' : '' ?>>
                            <a href="product-detail?slug=<?= sanitizeOutput($cartItem['slug'] ?? '') ?>" class="w-20 h-20 bg-surface-container rounded-lg overflow-hidden flex-shrink-0 flex items-center justify-center border border-outline-variant/30 p-2 bg-white hover:border-secondary transition-colors">
                                <img class="w-full h-full object-contain" src="<?= $itemImg ?>" alt="<?= sanitizeOutput($cartItem['name']) ?>"/>
                            </a>
                        </div>
                        
                        <!-- Details -->
                        <div class="flex-grow min-w-0 flex flex-col justify-center">
                            <a href="product-detail?slug=<?= sanitizeOutput($cartItem['slug'] ?? '') ?>" class="font-bold text-body-sm sm:text-body-md text-on-background line-clamp-2 sm:truncate leading-tight mb-1 sm:mb-0 hover:text-secondary transition-colors"><?= sanitizeOutput($cartItem['name']) ?></a>
                            <?php if ($cartItem['is_out_of_stock']): ?>
                                <span class="inline-block self-start px-2 py-0.5 text-[9px] font-bold bg-error/10 text-error rounded-md mt-1 mb-0.5">Stok Habis / Tidak Tersedia</span>
                            <?php endif; ?>
                            <p class="text-body-sm text-secondary font-black mb-0.5 sm:mt-0.5"><?= formatRupiah($cartItem['price']) ?></p>
                            <p class="text-[10px] sm:text-[11px] text-on-surface-variant">Stok: <?= (int)$cartItem['stock'] ?> unit</p>
                        </div>
                    </div>

                    <!-- Actions & Subtotal -->
                    <div class="flex items-center justify-between sm:justify-end gap-3 w-full sm:w-auto mt-1 sm:mt-0 pt-2 sm:pt-0 border-t border-outline-variant/40 sm:border-t-0">
                        <!-- Quantity adjust -->
                        <div class="flex items-center gap-2">
                            <form action="actions/cart-update" method="POST" class="flex items-center border border-outline-variant rounded-lg bg-white p-1 shrink-0">
                                <input type="hidden" name="csrf_token" value="<?= sanitizeOutput($csrfToken) ?>">
                                <input type="hidden" name="product_id" value="<?= (int)$cartItem['product_id'] ?>">
                                <button type="submit" name="quantity" value="<?= $cartItem['quantity'] - 1 ?>" class="w-7 h-7 sm:w-8 sm:h-8 rounded-md hover:bg-surface-container flex items-center justify-center font-bold text-on-surface transition-colors disabled:opacity-50" <?= ($cartItem['is_out_of_stock'] || $cartItem['quantity'] <= 1) ? 'disabled' : '' ?>>-</button>
                                <span class="w-8 sm:w-10 text-center font-bold text-xs sm:text-body-sm"><?= (int)$cartItem['quantity'] ?></span>
                                <button type="submit" name="quantity" value="<?= $cartItem['quantity'] + 1 ?>" class="w-7 h-7 sm:w-8 sm:h-8 rounded-md hover:bg-surface-container flex items-center justify-center font-bold text-on-surface transition-colors disabled:opacity-50" <?= ($cartItem['is_out_of_stock'] || $cartItem['quantity'] >= $cartItem['stock']) ? 'disabled' : '' ?>>+</button>
                            </form>
                        </div>

                        <div class="flex items-center gap-3 sm:gap-4">
                            <!-- Subtotal -->
                            <div class="text-right sm:min-w-[100px]">
                                <span class="text-[9px] sm:text-[10px] text-on-surface-variant block uppercase font-bold">Subtotal</span>
                                <span class="text-xs sm:text-body-md font-black text-on-background"><?= formatRupiah($cartItem['subtotal']) ?></span>
                            </div>

                            <!-- Remove -->
                            <form action="actions/cart-remove" method="POST" class="inline">
                                <input type="hidden" name="csrf_token" value="<?= sanitizeOutput($csrfToken) ?>">
                                <input type="hidden" name="product_id" value="<?= (int)$cartItem['product_id'] ?>">
                                <button type="submit" class="p-1.5 sm:p-2 text-error hover:bg-error/10 rounded-full transition-all flex items-center" title="Hapus Item">
                                    <span class="material-symbols-outlined text-[18px] sm:text-md">delete</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Right side: Summary Sidebar -->
            <div class="lg:col-span-4">
                <div class="bg-white p-6 rounded-xl border border-outline-variant/40 space-y-md">
                    <h2 class="text-headline-md font-extrabold text-on-background border-b border-outline-variant/30 pb-2">Ringkasan Belanja</h2>
                    
                    <div class="space-y-sm">
                        <div class="flex justify-between text-body-sm font-semibold">
                            <span class="text-on-surface-variant">Total Barang</span>
                            <span class="text-on-background" id="summary-total-items"><?= (int)$cartCount ?> unit</span>
                        </div>
                        <div class="flex justify-between text-body-sm font-semibold">
                            <span class="text-on-surface-variant">Subtotal</span>
                            <span class="text-on-background" id="summary-subtotal"><?= formatRupiah($cartTotal) ?></span>
                        </div>

                        <!-- Discount Promo Container -->
                        <div id="summary-discount-container" class="<?= $discountAmount > 0 ? '' : 'hidden' ?>">
                            <div class="flex justify-between text-body-sm font-semibold text-error">
                                <span class="flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[14px]">local_offer</span>
                                    Diskon Promo
                                </span>
                                <span id="summary-discount">- <?= formatRupiah($discountAmount) ?></span>
                            </div>
                            <div id="summary-applied-promos" class="text-[10px] text-error/80 leading-tight <?= !empty($appliedPromos) ? '' : 'hidden' ?>">
                                Promo Aktif: <?= sanitizeOutput(implode(', ', $appliedPromos ?? [])) ?>
                            </div>
                        </div>

                        <!-- Free Item Container -->
                        <div id="summary-free-item-container" class="mt-2 p-3 bg-emerald-50 border border-emerald-100 rounded-lg flex gap-3 items-center <?= $freeItem ? '' : 'hidden' ?>">
                            <div class="w-10 h-10 bg-white rounded-md border border-emerald-200 overflow-hidden shrink-0 p-1 flex items-center justify-center">
                                <img id="summary-free-item-img" src="<?= !empty($freeItem['image']) ? 'uploads/products/'.$freeItem['image'] : 'uploads/products/placeholder.png' ?>" class="max-w-full max-h-full object-contain">
                            </div>
                            <div class="flex-grow">
                                <div class="text-[10px] font-black text-emerald-600 uppercase tracking-wider mb-0.5">Bonus Gratis!</div>
                                <div id="summary-free-item-name" class="text-xs font-bold text-emerald-900 line-clamp-1"><?= sanitizeOutput($freeItem['name'] ?? '') ?></div>
                            </div>
                        </div>

                        <div class="flex justify-between text-body-lg font-black pt-sm border-t border-outline-variant/30">
                            <span>Total Harga</span>
                            <span class="text-secondary" id="summary-grand-total"><?= formatRupiah($cartTotal - $discountAmount) ?></span>
                        </div>
                    </div>

                    <form id="checkout-prep-form" action="actions/cart-checkout-prep" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= sanitizeOutput($csrfToken) ?>">
                        <div id="checkout-hidden-inputs"></div>
                        
                        <?php if (isset($_SESSION['customer_profile']) && is_array($_SESSION['customer_profile'])): ?>
                            <button type="submit" onclick="return submitCheckout(event)" class="w-full bg-secondary hover:bg-secondary-container text-white py-3.5 rounded-lg font-bold text-label-md transition-colors flex items-center justify-center gap-sm text-center">
                                Lanjut ke Checkout
                                <span class="material-symbols-outlined">arrow_forward</span>
                            </button>
                        <?php else: ?>
                            <button type="button" onclick="openProfileModal(event); showToast('Profil', 'Silakan masuk / atur profil terlebih dahulu untuk checkout.');" class="w-full bg-secondary hover:bg-secondary-container text-white py-3.5 rounded-lg font-bold text-label-md transition-colors flex items-center justify-center gap-sm text-center">
                                Lanjut ke Checkout
                                <span class="material-symbols-outlined">arrow_forward</span>
                            </button>
                        <?php endif; ?>
                    </form>

                    <a href="products" class="w-full border border-secondary text-secondary py-3.5 rounded-xl font-bold text-label-md transition-all active:scale-95 flex items-center justify-center gap-sm text-center">
                        Lanjutkan Belanja
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckbox = document.getElementById('select-all');
    const itemCheckboxes = document.querySelectorAll('.cart-item-checkbox');

    function updateSummary() {
        const selectedIds = Array.from(itemCheckboxes)
            .filter(cb => cb.checked)
            .map(cb => cb.value);

        if (selectedIds.length === 0) {
            document.getElementById('summary-total-items').textContent = '0 unit';
            document.getElementById('summary-subtotal').textContent = 'Rp 0';
            document.getElementById('summary-discount-container').classList.add('hidden');
            document.getElementById('summary-free-item-container').classList.add('hidden');
            document.getElementById('summary-grand-total').textContent = 'Rp 0';
            return;
        }

        const formData = new FormData();
        selectedIds.forEach(id => formData.append('selected_ids[]', id));

        fetch('actions/cart-calc.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('summary-total-items').textContent = data.total_items + ' unit';
                document.getElementById('summary-subtotal').textContent = data.subtotal_formatted;
                
                if (data.discount_amount > 0) {
                    document.getElementById('summary-discount-container').classList.remove('hidden');
                    document.getElementById('summary-discount').textContent = '- ' + data.discount_formatted;
                    const appliedPromosEl = document.getElementById('summary-applied-promos');
                    if (data.applied_promos) {
                        appliedPromosEl.classList.remove('hidden');
                        appliedPromosEl.textContent = 'Promo Aktif: ' + data.applied_promos;
                    } else {
                        appliedPromosEl.classList.add('hidden');
                    }
                } else {
                    document.getElementById('summary-discount-container').classList.add('hidden');
                }

                if (data.has_free_item) {
                    document.getElementById('summary-free-item-container').classList.remove('hidden');
                    document.getElementById('summary-free-item-img').src = data.free_item_image;
                    document.getElementById('summary-free-item-name').textContent = data.free_item_name;
                } else {
                    document.getElementById('summary-free-item-container').classList.add('hidden');
                }

                document.getElementById('summary-grand-total').textContent = data.grand_total_formatted;
            }
        })
        .catch(err => console.error('Error calculating cart:', err));
    }

    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            itemCheckboxes.forEach(cb => {
                if (!cb.disabled) {
                    cb.checked = selectAllCheckbox.checked;
                }
            });
            updateSummary();
        });
    }

    itemCheckboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            if (selectAllCheckbox) {
                const activeCbs = Array.from(itemCheckboxes).filter(c => !c.disabled);
                selectAllCheckbox.checked = activeCbs.length > 0 && activeCbs.every(c => c.checked);
            }
            updateSummary();
        });
    });
});

function submitCheckout(e) {
    const selectedIds = Array.from(document.querySelectorAll('.cart-item-checkbox:checked')).map(cb => cb.value);
    
    if (selectedIds.length === 0) {
        e.preventDefault();
        showToast('Peringatan', 'Pilih setidaknya satu produk untuk di-checkout', 'warning');
        return false;
    }
    
    const hiddenInputsContainer = document.getElementById('checkout-hidden-inputs');
    hiddenInputsContainer.innerHTML = '';
    
    selectedIds.forEach(id => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'selected_items[]';
        input.value = id;
        hiddenInputsContainer.appendChild(input);
    });
    
    return true;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
