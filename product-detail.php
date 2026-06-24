<?php
/**
 * Product Detail Page - Revamped
 * Displays full product information with premium visual aesthetics.
 * Shows related product recommendations and dynamic shipping cost estimation.
 */

require_once __DIR__ . '/includes/header.php';

// Get slug from URL parameter
$slug = $_GET['slug'] ?? '';

if (empty($slug)) {
    http_response_code(404);
    ?>
    <div class="max-w-max-width mx-auto px-4 md:px-margin-desktop py-xl text-center">
        <h1 class="text-headline-xl font-black text-on-background">404</h1>
        <p class="text-body-lg text-on-surface-variant mb-md">Produk tidak ditemukan atau telah dihapus.</p>
        <a href="products" class="px-md py-3 bg-secondary text-white font-bold rounded-xl shadow-md inline-block">Kembali ke Produk</a>
    </div>
    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Query product by slug (only active products)
$stmt = $pdo->prepare(
    "SELECT p.*, c.name as category_name 
     FROM products p 
     LEFT JOIN categories c ON p.category_id = c.id 
     WHERE p.slug = ? AND p.is_active = 1"
);
$stmt->execute([$slug]);
$product = $stmt->fetch();

if (!$product) {
    http_response_code(404);
    ?>
    <div class="max-w-max-width mx-auto px-4 md:px-margin-desktop py-xl text-center">
        <h1 class="text-headline-xl font-black text-on-background">404</h1>
        <p class="text-body-lg text-on-surface-variant mb-md">Produk tidak ditemukan atau telah dihapus.</p>
        <a href="products" class="px-md py-3 bg-secondary text-white font-bold rounded-xl shadow-md inline-block">Kembali ke Produk</a>
    </div>
    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Generate CSRF token
$csrfToken = generateCSRFToken();

// Determine product image and fetch additional images for gallery
$stmtImages = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id = ? ORDER BY sort_order ASC");
$stmtImages->execute([$product['id']]);
$additionalImages = $stmtImages->fetchAll(PDO::FETCH_COLUMN);

// Build list of all images (main image + additional images)
$allImages = [];
if (!empty($product['image'])) {
    $allImages[] = 'uploads/products/' . sanitizeOutput($product['image']);
} else {
    $allImages[] = 'uploads/products/placeholder.png';
}

foreach ($additionalImages as $addImg) {
    if (!empty($addImg)) {
        $allImages[] = 'uploads/products/' . sanitizeOutput($addImg);
    }
}

$productImage = $allImages[0];

// Fetch active shipping areas for calculator
$stmtAreas = $pdo->query("SELECT area_name, regency, cost FROM shipping_areas WHERE is_active = 1 ORDER BY area_name ASC");
$shippingAreas = $stmtAreas->fetchAll();

// Fetch Related Products (same category, excluding current product, limit 4)
$relatedProducts = [];
if (!empty($product['category_id'])) {
    $stmtRelated = $pdo->prepare(
        "SELECT p.*, c.name as category_name 
         FROM products p 
         LEFT JOIN categories c ON p.category_id = c.id 
         WHERE p.category_id = ? AND p.id != ? AND p.is_active = 1 
         ORDER BY p.id DESC LIMIT 4"
    );
    $stmtRelated->execute([$product['category_id'], $product['id']]);
    $relatedProducts = $stmtRelated->fetchAll();
}

// Fallback: If less than 4 related products, fetch other active products to complete the 4 suggestions
if (count($relatedProducts) < 4) {
    $needed = 4 - count($relatedProducts);
    $excludeIds = [$product['id']];
    foreach ($relatedProducts as $rp) {
        $excludeIds[] = $rp['id'];
    }
    
    $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));
    
    $stmtFallback = $pdo->prepare(
        "SELECT p.*, c.name as category_name 
         FROM products p 
         LEFT JOIN categories c ON p.category_id = c.id 
         WHERE p.id NOT IN ($placeholders) AND p.is_active = 1 
         ORDER BY p.is_featured DESC, p.id DESC LIMIT $needed"
    );
    $stmtFallback->execute($excludeIds);
    $fallbackProducts = $stmtFallback->fetchAll();
    
    $relatedProducts = array_merge($relatedProducts, $fallbackProducts);
}

// Define promo status and active price (only active if global flash sale is running)
$isPromo = $isGlobalFlashSaleActive && !empty($product['promo_active']) && !empty($product['promo_price']) && $product['promo_price'] > 0 && isset($product['promo_stock']) && $product['promo_stock'] > 0;
$activePrice = $isPromo ? (int)$product['promo_price'] : (int)$product['selling_price'];

// Compute state for marketplace UX components
$parsedSpec = parseSpecification($product['specification'] ?? '');
$savingsAmount = $isPromo ? max(0, (int)$product['selling_price'] - (int)$product['promo_price']) : 0;
$discountPercentage = $isPromo ? round((($product['selling_price'] - $product['promo_price']) / $product['selling_price']) * 100) : 0;
?>

<style>
    /* Styling tab content visibility with animations */
    .tab-content {
        display: none !important;
    }
    .tab-content.active {
        display: block !important;
        animation: tabFadeInUp 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }
    
    .spec-tab {
        border-bottom: 3px solid transparent;
        transition: all 0.25s ease;
    }
    .spec-tab.active-tab {
        border-bottom-color: #0058be !important;
        color: #0058be !important;
    }
    
    @keyframes tabFadeInUp {
        from {
            opacity: 0;
            transform: translateY(8px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .animate-pulse-slow {
        animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }
</style>

<div class="max-w-max-width mx-auto px-4 md:px-margin-desktop py-3 md:py-lg animate-fade-in-up pb-24 lg:pb-lg">
    <!-- Breadcrumbs -->
    <nav class="flex items-center gap-xs text-body-sm text-on-surface-variant mb-3 md:mb-lg">
        <a class="hover:text-secondary transition-colors" href="index">Beranda</a>
        <span class="material-symbols-outlined text-[16px] text-outline-variant">chevron_right</span>
        <a class="hover:text-secondary transition-colors" href="products">Produk</a>
        <?php if (!empty($product['category_name'])): ?>
            <span class="material-symbols-outlined text-[16px] text-outline-variant">chevron_right</span>
            <span class="text-on-surface-variant"><?= sanitizeOutput($product['category_name']) ?></span>
        <?php endif; ?>
        <span class="material-symbols-outlined text-[16px] text-outline-variant">chevron_right</span>
        <span class="text-on-surface font-semibold truncate max-w-[150px] sm:max-w-xs"><?= sanitizeOutput($product['name']) ?></span>
    </nav>
    
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 md:gap-8 items-start">
        <!-- Left Column: Gallery (lg:col-span-4) -->
        <section class="lg:col-span-4 space-y-4">
            <div onclick="openLightbox()" class="zoom-container aspect-square bg-white rounded-xl border border-gray-200 overflow-hidden flex items-center justify-center relative group cursor-zoom-in transition-all duration-200">
                <!-- Sharp Foreground Image -->
                <img id="main-product-image" alt="<?= sanitizeOutput($product['name']) ?>" class="zoom-image max-w-[90%] max-h-[90%] object-contain z-10 transition-transform duration-300 relative" src="<?= $productImage ?>"/>
                
                <!-- Slider Arrows for Main Preview -->
                <?php if (count($allImages) > 1): ?>
                    <button onclick="navigateMainImage(-1, event)" class="absolute left-2.5 top-1/2 -translate-y-1/2 bg-white/80 hover:bg-white text-on-surface hover:text-secondary w-10 h-10 rounded-full flex items-center justify-center border border-gray-200/50 shadow-md backdrop-blur-sm transition-all duration-200 opacity-90 md:opacity-0 md:group-hover:opacity-100 z-30 pointer-events-auto cursor-pointer" aria-label="Gambar Sebelumnya">
                        <span class="material-symbols-outlined font-bold">chevron_left</span>
                    </button>
                    <button onclick="navigateMainImage(1, event)" class="absolute right-2.5 top-1/2 -translate-y-1/2 bg-white/80 hover:bg-white text-on-surface hover:text-secondary w-10 h-10 rounded-full flex items-center justify-center border border-gray-200/50 shadow-md backdrop-blur-sm transition-all duration-200 opacity-90 md:opacity-0 md:group-hover:opacity-100 z-30 pointer-events-auto cursor-pointer" aria-label="Gambar Berikutnya">
                        <span class="material-symbols-outlined font-bold">chevron_right</span>
                    </button>
                <?php endif; ?>
            </div>
            
            <!-- Gallery Thumbnails -->
            <div class="flex flex-wrap gap-2">
                <?php foreach ($allImages as $idx => $imgUrl): ?>
                    <button onclick="setProductImage(this, '<?= $imgUrl ?>')" class="gallery-thumb w-16 h-16 bg-white rounded-lg <?= $idx === 0 ? 'border-2 border-secondary' : 'border border-outline-variant/60' ?> overflow-hidden p-1 transition-colors">
                        <img alt="Thumbnail <?= $idx + 1 ?>" class="w-full h-full object-cover rounded-md" src="<?= $imgUrl ?>"/>
                    </button>
                <?php endforeach; ?>
            </div>
        </section>
        
        <!-- Center Column: Product Main Info & Specifications (lg:col-span-5) -->
        <section class="lg:col-span-5 space-y-3 md:space-y-6">
            <div class="space-y-2">
                <!-- Category Tag -->
                <?php if (!empty($product['category_name'])): ?>
                    <span class="inline-block text-[10px] font-extrabold text-secondary uppercase tracking-widest px-2.5 py-1 bg-secondary/5 rounded-md">
                        <?= sanitizeOutput($product['category_name']) ?>
                    </span>
                <?php endif; ?>
                
                <h1 class="text-xl md:text-2xl font-black text-on-background tracking-tight leading-tight"><?= sanitizeOutput($product['name']) ?></h1>
            </div>
            
            <!-- Price Section -->
            <div class="space-y-3">
                <?php if ($isPromo): ?>
                    <!-- Premium Flash Sale Ticking Banner -->
                    <div class="bg-gradient-to-r from-red-600 to-orange-500 text-white rounded-xl p-3 flex justify-between items-center shadow-sm select-none">
                        <div class="flex items-center gap-1.5 font-black text-xs uppercase tracking-wider">
                            <span class="material-symbols-outlined text-sm font-bold flex-shrink-0 animate-pulse">campaign</span>
                            ⚡ Flash Sale Deal
                        </div>
                        <?php if ($fsSeconds > 0): ?>
                            <div class="text-[11px] font-bold text-white/95">
                                Berakhir dalam: <span id="detail-fs-timer" class="font-mono bg-white text-red-600 px-2 py-0.5 rounded font-black text-xs">00:00:00</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="flex items-baseline gap-2 flex-wrap">
                        <p class="text-2xl font-black text-red-600 leading-none tracking-tight"><?= formatRupiah($activePrice) ?></p>
                        <p class="text-xs font-semibold text-on-surface-variant/60 line-through"><?= formatRupiah((int)$product['selling_price']) ?></p>
                        <span class="px-2 py-0.5 bg-red-100 text-red-600 rounded text-xs font-black">-<?= $discountPercentage ?>%</span>
                        <span class="px-2 py-0.5 bg-green-100 text-green-700 rounded text-xs font-black">
                            Hemat <?= formatRupiah($savingsAmount) ?>
                        </span>
                    </div>
                <?php else: ?>
                    <div class="flex items-baseline gap-2">
                        <p class="text-2xl font-black text-secondary leading-none tracking-tight"><?= formatRupiah($activePrice) ?></p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Trust Badge Strip -->
            <div id="trust-badge-strip" class="flex flex-wrap gap-2 text-[10px] font-bold text-on-surface-variant my-2 lg:my-3">
                <div class="flex items-center gap-1 bg-green-50 text-green-700 px-2.5 py-1.5 rounded-full border border-green-100 whitespace-nowrap">
                    <span class="material-symbols-outlined text-[14px]">verified_user</span>
                    <span>100% Asli &amp; Bergaransi</span>
                </div>
                <div class="flex items-center gap-1 bg-blue-50 text-blue-700 px-2.5 py-1.5 rounded-full border border-blue-100 whitespace-nowrap">
                    <span class="material-symbols-outlined text-[14px]">inventory_2</span>
                    <span>Packing Aman</span>
                </div>
                <div class="flex items-center gap-1 bg-purple-50 text-purple-700 px-2.5 py-1.5 rounded-full border border-purple-100 whitespace-nowrap">
                    <span class="material-symbols-outlined text-[14px]">support_agent</span>
                    <span>Bisa Konsultasi</span>
                </div>
                <div class="flex items-center gap-1 bg-orange-50 text-orange-700 px-2.5 py-1.5 rounded-full border border-orange-100 whitespace-nowrap">
                    <span class="material-symbols-outlined text-[14px]">local_shipping</span>
                    <span>Pengiriman Terpercaya</span>
                </div>
            </div>

            <!-- Quick Benefit Summary -->
            <div id="quick-benefit-summary" class="grid grid-cols-2 sm:flex sm:flex-wrap gap-2 text-[11px] font-bold text-on-surface-variant my-2 lg:my-3">
                <?php if ($product['status'] === 'ready' && $product['stock'] > 0): ?>
                    <div class="flex items-center gap-1">
                        <span class="material-symbols-outlined text-[16px] text-green-600">check_circle</span>
                        <span>Ready Stock</span>
                    </div>
                <?php endif; ?>
                <div class="flex items-center gap-1">
                    <span class="material-symbols-outlined text-[16px] text-secondary">workspace_premium</span>
                    <span>Garansi Toko</span>
                </div>
                <div class="flex items-center gap-1">
                    <span class="material-symbols-outlined text-[16px] text-secondary">package_2</span>
                    <span>Packing Aman</span>
                </div>
                <div class="flex items-center gap-1">
                    <span class="material-symbols-outlined text-[16px] text-secondary">headset_mic</span>
                    <span>Konsultasi Gratis</span>
                </div>
            </div>

            <!-- Tabs (Description & Specifications) -->
            <div class="mt-6 bg-white border border-gray-200/80 rounded-xl p-4 md:p-6 shadow-sm">
                <div class="space-y-4">
                <div class="flex border-b border-gray-200 gap-6">
                    <button onclick="setTab(this, 'deskripsi')" class="spec-tab pb-2.5 text-xs active-tab font-bold text-secondary">Deskripsi</button>
                    <button onclick="setTab(this, 'spesifikasi')" class="spec-tab pb-2.5 text-xs text-on-surface-variant hover:text-secondary font-bold">Spesifikasi</button>
                </div>
                
                <!-- Deskripsi Content -->
                <div id="deskripsi-tab" class="tab-content active leading-relaxed text-xs text-on-surface-variant">
                    <?php if (!empty($product['description'])): ?>
                        <?= nl2br(sanitizeOutput($product['description'])) ?>
                    <?php else: ?>
                        <p class="text-on-surface-variant italic">Tidak ada deskripsi produk.</p>
                    <?php endif; ?>
                </div>
                
                <!-- Spesifikasi Content -->
                <div id="spesifikasi-tab" class="tab-content leading-relaxed text-xs text-on-surface-variant">
                    <?php if (!empty($parsedSpec['parsed'])): ?>
                        <div class="overflow-hidden border border-gray-200 rounded-lg mb-4">
                            <table class="w-full text-left border-collapse">
                                <tbody>
                                    <?php foreach ($parsedSpec['parsed'] as $index => $row): ?>
                                        <tr class="<?= $index % 2 === 0 ? 'bg-slate-50' : 'bg-white' ?> border-b border-gray-100 last:border-0">
                                            <th class="py-2.5 px-4 w-1/3 font-bold text-on-surface-variant border-r border-gray-100 align-top">
                                                <?= sanitizeOutput($row['key']) ?>
                                            </th>
                                            <td class="py-2.5 px-4 text-on-surface align-top">
                                                <?= sanitizeOutput($row['value']) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if (!empty($parsedSpec['unparsed'])): ?>
                            <div class="pt-2 text-on-surface-variant">
                                <?= nl2br(sanitizeOutput($parsedSpec['unparsed'])) ?>
                            </div>
                        <?php endif; ?>
                    <?php elseif (!empty($product['specification'])): ?>
                        <?= nl2br(sanitizeOutput($product['specification'])) ?>
                    <?php else: ?>
                        <p class="text-on-surface-variant italic">Tidak ada spesifikasi khusus.</p>
                    <?php endif; ?>
                </div>
                </div>
            </div>
        </section>
        
        <!-- Right Column: Sticky Purchase Box (lg:col-span-3) -->
        <section class="hidden lg:block lg:col-span-3 lg:sticky lg:top-24">
            <div class="bg-white border border-gray-200 rounded-xl p-5 space-y-4 shadow-sm">
                <h3 class="text-sm font-extrabold text-on-background border-b border-gray-100 pb-2">Atur Pembelian</h3>
                
                <!-- Stock Info & Condition -->
                <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
                    <div class="flex items-center gap-1.5">
                        <?php if ($product['status'] === 'ready' && $product['stock'] > 0): ?>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-green-100 text-green-700 rounded font-bold text-[10px]">
                                Ready (<?= (int)$product['stock'] ?>)
                            </span>
                        <?php elseif ($product['status'] === 'po'): ?>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-blue-100 text-blue-700 rounded font-bold text-[10px]">
                                Pre-Order
                            </span>
                        <?php else: ?>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-red-100 text-red-700 rounded font-bold text-[10px]">
                                Stok Habis
                            </span>
                        <?php endif; ?>
                    </div>
                    <span class="text-[10px] bg-slate-100 px-2 py-0.5 rounded font-extrabold text-on-surface-variant">Kondisi: <?= $product['condition_type'] === 'new' ? 'Baru' : 'Bekas' ?></span>
                </div>
                
                <?php if ($product['status'] === 'ready' || $product['status'] === 'po'): ?>
                    <!-- Quantity Selector -->
                    <div class="space-y-1">
                        <label class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider block">Jumlah Barang</label>
                        <div class="flex items-center border border-gray-200 rounded-lg p-1 bg-white justify-between">
                            <button onclick="decrementQty()" class="w-8 h-8 rounded-lg hover:bg-slate-100 flex items-center justify-center font-bold text-on-surface transition-colors select-none">
                                <span class="material-symbols-outlined text-sm">remove</span>
                            </button>
                            <span id="qty-indicator" class="w-10 text-center font-bold text-xs text-on-background">1</span>
                            <button onclick="incrementQty()" class="w-8 h-8 rounded-lg hover:bg-slate-100 flex items-center justify-center font-bold text-on-surface transition-colors select-none">
                                <span class="material-symbols-outlined text-sm">add</span>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Subtotal Display -->
                    <div class="flex items-baseline justify-between pt-1">
                        <span class="text-[10px] font-bold text-on-surface-variant uppercase">Subtotal</span>
                        <span id="total-price" class="text-sm font-black text-secondary"><?= formatRupiah($activePrice) ?></span>
                    </div>
                    
                    <!-- Nested Shipping Calculator -->
                    <?php if (!empty($shippingAreas)): ?>
                    <div class="border-t border-gray-100 pt-3 space-y-2">
                        <div class="flex items-center gap-1 text-xs font-bold text-on-background">
                            <span class="material-symbols-outlined text-sm text-secondary">local_shipping</span>
                            Estimasi Ongkir
                        </div>
                        <div class="space-y-1.5">
                            <select id="shipping-city" onchange="calculateShipping()" class="w-full bg-white border border-gray-200 rounded-lg px-2.5 py-1.5 text-xs focus:border-secondary outline-none">
                                <?php foreach ($shippingAreas as $area): ?>
                                    <option value="<?= (int)$area['cost'] ?>"><?= sanitizeOutput($area['area_name']) ?> (<?= sanitizeOutput($area['regency']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <p id="shipping-cost" class="text-[11px] font-black text-on-surface-variant text-right">Rp 0</p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Add to Cart Form -->
                    <form id="add-to-cart-form" action="actions/cart-add" method="POST" class="space-y-2 pt-2">
                        <input type="hidden" name="csrf_token" value="<?= sanitizeOutput($csrfToken) ?>">
                        <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
                        <input type="hidden" id="form-quantity" name="quantity" value="1">
                        
                        <button type="submit" class="w-full bg-white border-2 border-secondary text-secondary py-2.5 rounded-lg font-bold text-xs hover:bg-secondary/5 transition-colors flex items-center justify-center gap-1">
                            <span class="material-symbols-outlined text-sm">add_shopping_cart</span>
                            Keranjang
                        </button>
                        <button type="button" onclick="buyNow()" class="w-full bg-secondary text-white py-2.5 rounded-lg font-bold text-xs hover:bg-secondary-container transition-colors flex items-center justify-center gap-1">
                            <span class="material-symbols-outlined text-sm">flash_on</span>
                            Beli Sekarang
                        </button>
                    </form>
                <?php else: ?>
                    <div class="bg-red-50 p-3 rounded-lg border border-red-100 text-center text-xs">
                        <p class="font-bold text-red-600">Stok Habis Terjual</p>
                    </div>
                <?php endif; ?>
                
                <!-- Trust propositions list -->
                <div class="border-t border-gray-100 pt-3 space-y-2 text-[10px] text-on-surface-variant font-medium">
                    <!-- Trust badges deduplicated into Trust Badge Strip -->
                </div>
            </div>
        </section>
    </div>
    
    <!-- Related Products Section -->
    <?php if (!empty($relatedProducts)): ?>
    <section class="mt-lg md:mt-2xl pt-md md:pt-xl border-t border-outline-variant/30">
        <div class="flex items-center justify-between mb-lg flex-wrap gap-2">
            <div>
                <h2 class="text-headline-sm font-black text-on-background tracking-tight">Rekomendasi Untuk Anda</h2>
                <p class="text-body-sm text-on-surface-variant">Temukan produk menarik lainnya yang mungkin Anda butuhkan</p>
            </div>
            <a href="products" class="text-body-sm font-bold text-secondary hover:underline flex items-center gap-1">
                Lihat Semua Produk
                <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
            </a>
        </div>
        
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 md:gap-lg">
            <?php foreach ($relatedProducts as $rp): 
                $rpImage = !empty($rp['image']) 
                    ? 'uploads/products/' . sanitizeOutput($rp['image']) 
                    : 'uploads/products/placeholder.png';
                $rpPrice = ($isGlobalFlashSaleActive && !empty($rp['promo_active']) && !empty($rp['promo_price']) && $rp['promo_price'] > 0) ? (int)$rp['promo_price'] : (int)$rp['selling_price'];
                $rpIsPromo = ($isGlobalFlashSaleActive && !empty($rp['promo_active']) && !empty($rp['promo_price']) && $rp['promo_price'] > 0);
            ?>
                <div class="tech-card bg-white rounded-lg border border-outline-variant/40 overflow-hidden flex flex-col h-full group transition-colors">
                    <a href="product-detail?slug=<?= sanitizeOutput($rp['slug']) ?>" class="block relative aspect-square bg-white flex items-center justify-center p-4 overflow-hidden">
                        <!-- Product image -->
                        <img class="max-w-[85%] max-h-[85%] object-contain z-10 transition-transform duration-300" src="<?= $rpImage ?>" alt="<?= sanitizeOutput($rp['name']) ?>">
                        
                        <?php if ($rpIsPromo): ?>
                            <span class="absolute top-3 left-3 bg-error text-white text-[9.5px] font-extrabold px-2 py-0.5 rounded bg-error text-white text-[9.5px] font-extrabold px-2 py-0.5 z-20">PROMO</span>
                        <?php endif; ?>
                    </a>
                    <div class="p-md flex flex-col flex-grow">
                        <span class="text-[9px] font-bold text-secondary uppercase tracking-wider block mb-1"><?= sanitizeOutput($rp['category_name'] ?? 'Komputer') ?></span>
                        <a href="product-detail?slug=<?= sanitizeOutput($rp['slug']) ?>" class="text-body-sm font-bold text-on-background line-clamp-2 hover:text-secondary transition-colors mb-2 flex-grow"><?= sanitizeOutput($rp['name']) ?></a>
                        
                        <div class="flex items-baseline gap-1.5 flex-wrap">
                            <p class="text-body-md font-black text-secondary"><?= formatRupiah($rpPrice) ?></p>
                            <?php if ($rpIsPromo): ?>
                                <p class="text-[11px] font-medium text-on-surface-variant/60 line-through"><?= formatRupiah((int)$rp['selling_price']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
</div>

<!-- Mobile Sticky CTA Bar — only visible below lg breakpoint -->
<?php if ($product['status'] === 'ready' || $product['status'] === 'po'): ?>
<div id="mobile-sticky-cta" class="fixed bottom-0 left-0 right-0 z-40 bg-white border-t border-gray-200 px-4 py-3 lg:hidden">
    <div class="flex items-center justify-between gap-3 max-w-max-width mx-auto">
        <!-- Price Summary -->
        <div class="flex-shrink-0">
            <p class="text-xs text-on-surface-variant">Total</p>
            <p id="mobile-cta-price" class="text-sm font-black text-secondary"><?= formatRupiah($activePrice) ?></p>
        </div>
        <!-- Action Buttons -->
        <div class="flex gap-2 flex-1 max-w-xs">
            <button type="button" onclick="openPurchaseSheet()" 
                class="flex-1 bg-white border-2 border-secondary text-secondary py-2.5 rounded-lg font-bold text-xs">
                Keranjang
            </button>
            <button type="button" onclick="openPurchaseSheet()" 
                class="flex-1 bg-secondary text-white py-2.5 rounded-lg font-bold text-xs">
                Beli Sekarang
            </button>
        </div>
    </div>
</div>
<?php else: ?>
<div id="mobile-sticky-cta" class="fixed bottom-0 left-0 right-0 z-40 bg-white border-t border-gray-200 px-4 py-3 lg:hidden">
    <div class="flex items-center justify-between gap-3 max-w-max-width mx-auto">
        <!-- Price Summary Only (buttons hidden when product status is 'habis') -->
        <div class="flex-shrink-0">
            <p class="text-xs text-on-surface-variant">Total</p>
            <p id="mobile-cta-price" class="text-sm font-black text-secondary"><?= formatRupiah($activePrice) ?></p>
        </div>
        <div class="flex gap-2 flex-1 max-w-xs justify-end">
            <span class="text-xs font-bold text-red-600">Stok Habis</span>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Mobile Purchase Bottom Sheet -->
<div id="purchase-sheet-overlay" class="fixed inset-0 z-[60] bg-black/50 hidden opacity-0 transition-opacity duration-300" onclick="closePurchaseSheet()"></div>
<div id="purchase-sheet" class="fixed bottom-0 left-0 right-0 z-[60] bg-white rounded-t-2xl transform translate-y-full transition-transform duration-300 lg:hidden flex flex-col max-h-[90vh]">
    <div class="flex items-center justify-between p-4 border-b border-gray-100">
        <h3 class="font-bold text-on-background">Atur Pembelian</h3>
        <button type="button" onclick="closePurchaseSheet()" class="text-on-surface-variant hover:text-on-background w-8 h-8 flex items-center justify-center rounded-full bg-slate-100">
            <span class="material-symbols-outlined text-lg">close</span>
        </button>
    </div>
    <div class="p-4 overflow-y-auto space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
            <div class="flex items-center gap-1.5">
                <?php if ($product['status'] === 'ready' && $product['stock'] > 0): ?>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-green-100 text-green-700 rounded font-bold text-[10px]">
                        Ready (<?= (int)$product['stock'] ?>)
                    </span>
                <?php elseif ($product['status'] === 'po'): ?>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-blue-100 text-blue-700 rounded font-bold text-[10px]">
                        Pre-Order
                    </span>
                <?php else: ?>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-red-100 text-red-700 rounded font-bold text-[10px]">
                        Stok Habis
                    </span>
                <?php endif; ?>
            </div>
            <span class="text-[10px] bg-slate-100 px-2 py-0.5 rounded font-extrabold text-on-surface-variant">Kondisi: <?= $product['condition_type'] === 'new' ? 'Baru' : 'Bekas' ?></span>
        </div>
        <?php if ($product['status'] === 'ready' || $product['status'] === 'po'): ?>
        <div class="space-y-2">
            <div class="flex items-center justify-between">
                <label class="text-[11px] font-bold text-on-surface-variant uppercase tracking-wider">Jumlah</label>
                <div class="flex items-center border border-gray-200 rounded-lg p-1 bg-white justify-between w-28">
                    <button type="button" onclick="decrementQty()" class="w-7 h-7 rounded-md hover:bg-slate-100 flex items-center justify-center font-bold text-on-surface transition-colors select-none">
                        <span class="material-symbols-outlined text-sm">remove</span>
                    </button>
                    <span id="mobile-qty-indicator" class="w-8 text-center font-bold text-xs text-on-background">1</span>
                    <button type="button" onclick="incrementQty()" class="w-7 h-7 rounded-md hover:bg-slate-100 flex items-center justify-center font-bold text-on-surface transition-colors select-none">
                        <span class="material-symbols-outlined text-sm">add</span>
                    </button>
                </div>
            </div>
        </div>
        <div class="flex items-baseline justify-between pt-2 border-t border-gray-100">
            <span class="text-[11px] font-bold text-on-surface-variant uppercase">Subtotal</span>
            <span id="mobile-sheet-total-price" class="text-sm font-black text-secondary"><?= formatRupiah($activePrice) ?></span>
        </div>
        <?php endif; ?>
    </div>
    <?php if ($product['status'] === 'ready' || $product['status'] === 'po'): ?>
    <div class="p-4 border-t border-gray-100 bg-white">
        <form id="mobile-add-to-cart-form" action="actions/cart-add" method="POST" class="flex gap-2">
            <input type="hidden" name="csrf_token" value="<?= sanitizeOutput($csrfToken) ?>">
            <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
            <input type="hidden" id="mobile-form-quantity" name="quantity" value="1">
            <button type="submit" class="flex-1 bg-white border-2 border-secondary text-secondary py-2.5 rounded-lg font-bold text-xs">
                Keranjang
            </button>
            <button type="button" onclick="buyNowMobile()" class="flex-1 bg-secondary text-white py-2.5 rounded-lg font-bold text-xs">
                Beli Sekarang
            </button>
        </form>
    </div>
    <?php endif; ?>
</div>

<!-- Lightbox Modal Overlay UI -->
<div id="lightbox-modal" class="fixed inset-0 z-50 bg-black/90 flex flex-col items-center justify-center p-4 md:p-8 hidden opacity-0" onclick="closeLightbox()">
    <button class="absolute top-6 right-6 text-white hover:text-outline-variant flex items-center justify-center w-12 h-12 bg-white/10 rounded-full backdrop-blur-md active:scale-95 transition-transform z-50" onclick="closeLightbox(event)">
        <span class="material-symbols-outlined text-2xl">close</span>
    </button>
    
    <!-- Lightbox Content Wrapper (Relative to wrap image and navigation close next to it) -->
    <div class="relative max-w-full max-h-[85vh] flex items-center justify-center px-12 md:px-16" onclick="event.stopPropagation()">
        <!-- Lightbox Navigation Arrows -->
        <?php if (count($allImages) > 1): ?>
            <button onclick="navigateLightbox(-1, event)" class="absolute left-0 text-white hover:text-secondary flex items-center justify-center w-10 h-10 md:w-12 md:h-12 bg-white/10 hover:bg-white/20 border border-white/20 rounded-full backdrop-blur-md active:scale-95 transition-all cursor-pointer z-50" aria-label="Gambar Sebelumnya">
                <span class="material-symbols-outlined text-2xl md:text-3xl">chevron_left</span>
            </button>
            <button onclick="navigateLightbox(1, event)" class="absolute right-0 text-white hover:text-secondary flex items-center justify-center w-10 h-10 md:w-12 md:h-12 bg-white/10 hover:bg-white/20 border border-white/20 rounded-full backdrop-blur-md active:scale-95 transition-all cursor-pointer z-50" aria-label="Gambar Berikutnya">
                <span class="material-symbols-outlined text-2xl md:text-3xl">chevron_right</span>
            </button>
        <?php endif; ?>

        <img id="lightbox-image" class="max-w-full max-h-[80vh] object-contain rounded-xl select-none" src="" />
    </div>
</div>

<script>
    // Global Variables
    let currentPrice = <?= (int)$activePrice ?>;
    let qty = 1;
    let maxQty = <?= $product['status'] === 'ready' ? (int)$product['stock'] : 999 ?>;
    
    // Gallery state variables
    const productImages = <?= json_encode($allImages) ?>;
    let currentImageIndex = 0;

    // Gallery switching with visual borders
    function setProductImage(buttonEl, url) {
        // Find index of image URL in array
        const index = productImages.indexOf(url);
        if (index !== -1) {
            currentImageIndex = index;
        }

        document.querySelectorAll('.gallery-thumb').forEach(btn => {
            btn.classList.remove('border-secondary', 'border-2');
            btn.classList.add('border-outline-variant/60', 'border');
        });
        
        if (buttonEl) {
            buttonEl.classList.remove('border-outline-variant/60', 'border');
            buttonEl.classList.add('border-secondary', 'border-2');
        } else {
            // Highlight thumbnail programmatically
            const thumbs = document.querySelectorAll('.gallery-thumb');
            if (thumbs[currentImageIndex]) {
                thumbs[currentImageIndex].classList.remove('border-outline-variant/60', 'border');
                thumbs[currentImageIndex].classList.add('border-secondary', 'border-2');
            }
        }
        
        document.getElementById('main-product-image').src = url;
    }

    // Navigate main preview image via arrows
    function navigateMainImage(direction, event) {
        if (event) event.stopPropagation(); // Prevent opening lightbox
        
        currentImageIndex += direction;
        if (currentImageIndex < 0) {
            currentImageIndex = productImages.length - 1;
        } else if (currentImageIndex >= productImages.length) {
            currentImageIndex = 0;
        }
        
        const nextUrl = productImages[currentImageIndex];
        setProductImage(null, nextUrl);
    }

    // Lightbox modal functions
    function openLightbox() {
        const lightbox = document.getElementById('lightbox-modal');
        const mainImg = document.getElementById('main-product-image');
        const lbImg = document.getElementById('lightbox-image');
        
        // Sync current index
        const currentSrc = mainImg.getAttribute('src');
        const index = productImages.indexOf(currentSrc);
        if (index !== -1) {
            currentImageIndex = index;
        }
        
        lbImg.src = mainImg.src;
        lightbox.classList.remove('hidden');
        setTimeout(() => {
            lightbox.style.opacity = '1';
        }, 10);
    }

    // Navigate lightbox preview via arrows
    function navigateLightbox(direction, event) {
        if (event) event.stopPropagation(); // Prevent closing lightbox
        
        currentImageIndex += direction;
        if (currentImageIndex < 0) {
            currentImageIndex = productImages.length - 1;
        } else if (currentImageIndex >= productImages.length) {
            currentImageIndex = 0;
        }
        
        const nextUrl = productImages[currentImageIndex];
        setProductImage(null, nextUrl);
        document.getElementById('lightbox-image').src = nextUrl;
    }

    // Keyboard navigation for lightbox
    document.addEventListener('keydown', (e) => {
        const lightbox = document.getElementById('lightbox-modal');
        if (lightbox && !lightbox.classList.contains('hidden')) {
            if (e.key === 'ArrowRight' || e.key === 'Right') {
                navigateLightbox(1);
            } else if (e.key === 'ArrowLeft' || e.key === 'Left') {
                navigateLightbox(-1);
            } else if (e.key === 'Escape' || e.key === 'Esc') {
                closeLightbox();
            }
        }
    });

    // Close lightbox modal
    function closeLightbox(event) {
        if (event) event.stopPropagation();
        const lightbox = document.getElementById('lightbox-modal');
        lightbox.style.opacity = '0';
        setTimeout(() => {
            lightbox.classList.add('hidden');
        }, 350);
    }

    // Tab switcher with smooth bottom borders
    function setTab(buttonEl, tabId) {
        document.querySelectorAll('.spec-tab').forEach(btn => {
            btn.classList.remove('active-tab', 'text-secondary');
            btn.classList.add('text-on-surface-variant');
        });
        buttonEl.classList.add('active-tab', 'text-secondary');
        buttonEl.classList.remove('text-on-surface-variant');

        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.remove('active');
        });
        document.getElementById(`${tabId}-tab`).classList.add('active');
    }

    // Quantity selectors and calculation
    function formatCurrency(val) {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val).replace("IDR", "Rp");
    }

    function updateCalculations() {
        const desktopQty = document.getElementById('qty-indicator');
        if (desktopQty) desktopQty.textContent = qty;
        
        const mobileQty = document.getElementById('mobile-qty-indicator');
        if (mobileQty) mobileQty.textContent = qty;
        
        const desktopFormQty = document.getElementById('form-quantity');
        if (desktopFormQty) desktopFormQty.value = qty;
        
        const mobileFormQty = document.getElementById('mobile-form-quantity');
        if (mobileFormQty) mobileFormQty.value = qty;

        const total = currentPrice * qty;
        const formattedTotal = formatCurrency(total);
        
        const desktopTotal = document.getElementById('total-price');
        if (desktopTotal) desktopTotal.textContent = formattedTotal;
        
        const mobileSheetTotal = document.getElementById('mobile-sheet-total-price');
        if (mobileSheetTotal) mobileSheetTotal.textContent = formattedTotal;
        
        updateMobileCtaPrice();
    }

    function updateMobileCtaPrice() {
        const mobilePriceEl = document.getElementById('mobile-cta-price');
        if (mobilePriceEl) {
            mobilePriceEl.textContent = formatCurrency(currentPrice * qty);
        }
    }

    // Quantity controls
    function incrementQty() {
        if (qty < maxQty) {
            qty++;
            updateCalculations();
        } else {
            showToast("Stok Terbatas", "Anda tidak dapat menambahkan barang melebihi stok tersedia.");
        }
    }

    function decrementQty() {
        if (qty > 1) {
            qty--;
            updateCalculations();
        }
    }

    // Shipping cost calculator
    function calculateShipping() {
        const select = document.getElementById('shipping-city');
        if (!select) return;
        const cost = parseInt(select.value, 10);
        document.getElementById('shipping-cost').innerHTML = `${formatCurrency(cost)} <span class="text-body-sm font-semibold text-on-surface-variant">(Lokal)</span>`;
    }

    // Buy Now - submit form normally with buy_now = 1
    function buyNow() {
        const form = document.getElementById('add-to-cart-form');
        if (!form) return;
        submitBuyNowForm(form, 'buy-now-input');
    }

    function buyNowMobile() {
        const form = document.getElementById('mobile-add-to-cart-form');
        if (!form) return;
        submitBuyNowForm(form, 'mobile-buy-now-input');
    }

    function submitBuyNowForm(form, inputId) {
        let buyNowInput = document.getElementById(inputId);
        if (!buyNowInput) {
            buyNowInput = document.createElement('input');
            buyNowInput.type = 'hidden';
            buyNowInput.id = inputId;
            buyNowInput.name = 'buy_now';
            form.appendChild(buyNowInput);
        }
        buyNowInput.value = '1';
        showToast("Memproses", "Menambahkan ke keranjang belanja...");
        form.submit();
    }

    // Purchase Bottom Sheet logic
    function openPurchaseSheet() {
        const overlay = document.getElementById('purchase-sheet-overlay');
        const sheet = document.getElementById('purchase-sheet');
        
        overlay.classList.remove('hidden');
        // A small delay to allow display block to apply before animating opacity
        setTimeout(() => {
            overlay.classList.remove('opacity-0');
            overlay.classList.add('opacity-100');
            sheet.classList.remove('translate-y-full');
        }, 10);
    }

    function closePurchaseSheet() {
        const overlay = document.getElementById('purchase-sheet-overlay');
        const sheet = document.getElementById('purchase-sheet');
        
        overlay.classList.remove('opacity-100');
        overlay.classList.add('opacity-0');
        sheet.classList.add('translate-y-full');
        
        setTimeout(() => {
            overlay.classList.add('hidden');
        }, 300);
    }

    function startCountdown() {
        let fsSeconds = <?= (int)$fsSeconds ?>;
        if (fsSeconds <= 0) return;

        function formatTime(totalSeconds) {
            let h = Math.floor(totalSeconds / 3600);
            let m = Math.floor((totalSeconds % 3600) / 60);
            let s = totalSeconds % 60;
            return {
                hours: String(h).padStart(2, '0'),
                minutes: String(m).padStart(2, '0'),
                seconds: String(s).padStart(2, '0')
            };
        }

        const timerInterval = setInterval(() => {
            if (fsSeconds > 0) {
                fsSeconds--;
                const formatted = formatTime(fsSeconds);
                const timerEl = document.getElementById('detail-fs-timer');
                if (timerEl) {
                    timerEl.textContent = `${formatted.hours}:${formatted.minutes}:${formatted.seconds}`;
                }
            } else {
                clearInterval(timerInterval);
            }
        }, 1000);
    }

    window.addEventListener('DOMContentLoaded', () => {
        calculateShipping();
        startCountdown();
        updateMobileCtaPrice();
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
