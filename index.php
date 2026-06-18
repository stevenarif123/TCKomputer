<?php
/**
 * Homepage - TC Komputer
 * Menampilkan hero section, kategori pilihan, flash sale, produk unggulan, produk terbaru, dan keunggulan toko.
 */

require_once __DIR__ . '/includes/header.php';

// Generate CSRF token for add-to-cart forms
$csrfToken = generateCSRFToken();

// Fetch active banners ordered by sort_order
$stmtBanners = $pdo->query("SELECT * FROM banners WHERE is_active=1 ORDER BY sort_order ASC");
$banners = $stmtBanners->fetchAll();

// Fetch active categories ordered by sort_order (compact initial render for homepage top section)
$stmtCategories = $pdo->query("SELECT * FROM categories WHERE is_active=1 ORDER BY sort_order ASC LIMIT 8");
$categories = $stmtCategories->fetchAll();

// Fetch featured products (is_featured=1, is_active=1, limit 8, newest first)
$stmtFeatured = $pdo->query("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.is_featured=1 AND p.is_active=1 ORDER BY p.created_at DESC LIMIT 8");
$featuredProducts = $stmtFeatured->fetchAll();

// Fetch newest products (is_active=1, limit 8, newest first)
$stmtNewest = $pdo->query("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.is_active=1 ORDER BY p.created_at DESC LIMIT 8");
$newestProducts = $stmtNewest->fetchAll();

// Fetch products for Flash Sale (active products with real promo configurations, limit 6)
$stmtFlashSale = $pdo->query("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.is_active=1 AND p.promo_active=1 AND p.promo_price > 0 ORDER BY p.id ASC LIMIT 6");
$flashSaleProducts = $stmtFlashSale->fetchAll();



// Hero Banner Data Integration
$slides = [];
if (!empty($banners)) {
    foreach ($banners as $b) {
        // Automatically check if this banner is a promo/discount
        $isPromo = false;
        if (stripos($b['title'], 'promo') !== false || 
            stripos($b['title'], 'diskon') !== false || 
            stripos($b['title'], 'sale') !== false || 
            stripos($b['description'], 'promo') !== false || 
            stripos($b['description'], 'diskon') !== false || 
            stripos($b['description'], 'sale') !== false) {
            $isPromo = true;
        }
        $slides[] = [
            'title' => $b['title'],
            'description' => $b['description'],
            'image' => !empty($b['image']) ? "uploads/banners/" . $b['image'] : "uploads/banners/placeholder.png",
            'link_url' => $b['link_url'] ?? 'products',
            'is_promo' => $isPromo
        ];
    }
} else {
    $slides[] = [
        'title' => "Selamat Datang di TC Komputer",
        'description' => "Menyediakan perangkat IT, komputer, dan aksesoris berkualitas tinggi dengan garansi resmi untuk workspace produktif Anda.",
        'image' => "https://lh3.googleusercontent.com/aida-public/AB6AXuCxr_HNW9fY9-NnqnhEN1S3s0kGLbmCWuKATK2qhn5z76V2wuWrFl5_zUTGM9D3qwJRX0A7-l2EXm2s0z9aleio49_8JhhqYeubt1awTkgIwkOQ_3TEW8ukhUjaTYJcgpmjKeI7vUHAyPQhzKsrcLNIx9BQqj4JU8PrtTh9vJfeo1k9kkyjwWuVzDWpxWo79tBhjSRi5vgHSeYh6sKNKncwZ9I0IsfStI0nY-mRe5D8nkVpv9CtK83cX0QRs2CLFRnAPBKFuIT0Nbg",
        'link_url' => "products",
        'is_promo' => false
    ];
}


// Promo Grid Banners Data Integration
$promoBanners = [];
for ($i = 1; $i <= 3; $i++) {
    $title = $storeSettings["promo_banner_{$i}_title"] ?? '';
    if (!empty($title)) {
        $promoBanners[] = [
            'title' => $title,
            'desc' => $storeSettings["promo_banner_{$i}_desc"] ?? '',
            'link' => $storeSettings["promo_banner_{$i}_link"] ?? '#',
            'icon' => $storeSettings["promo_banner_{$i}_icon"] ?? 'campaign',
            'index' => $i
        ];
    }
}

$bannerStyles = [
    1 => [
        'bg' => 'bg-blue-50 border-blue-100 hover:border-blue-300',
        'title' => 'text-blue-900 group-hover:text-secondary',
        'desc' => 'text-blue-800/80',
        'cta' => 'text-secondary',
        'icon' => 'text-secondary',
        'cta_text' => 'Cek Detail &raquo;'
    ],
    2 => [
        'bg' => 'bg-amber-50 border-amber-100 hover:border-amber-300',
        'title' => 'text-amber-900 group-hover:text-amber-700',
        'desc' => 'text-amber-800/80',
        'cta' => 'text-amber-600',
        'icon' => 'text-amber-500',
        'cta_text' => 'Lihat Promo &raquo;'
    ],
    3 => [
        'bg' => 'bg-emerald-50 border-emerald-100 hover:border-emerald-300',
        'title' => 'text-emerald-900 group-hover:text-emerald-700',
        'desc' => 'text-emerald-800/80',
        'cta' => 'text-emerald-600',
        'icon' => 'text-emerald-600',
        'cta_text' => 'Cari Produk &raquo;'
    ]
];
?>

<div class="py-2 animate-fade-in-up">
    <!-- Running Ticker / Info Promo Teks Berjalan -->
    <?php if (!empty($storeSettings['running_ticker'])): ?>
    <div class="max-w-max-width mx-auto px-4 md:px-margin-desktop">
        <div class="bg-secondary/5 border border-outline-variant/30 rounded-lg py-2.5 px-4 flex items-center gap-3 text-secondary text-xs select-none">
            <span class="material-symbols-outlined text-sm font-bold flex-shrink-0 animate-pulse">campaign</span>
            <div class="flex-grow overflow-hidden relative">
                <marquee scrollamount="4" class="font-semibold text-on-surface-variant">
                    <?= sanitizeOutput($storeSettings['running_ticker']) ?>
                </marquee>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Immersive Premium Hero Section with Slider -->
    <section class="max-w-max-width mx-auto px-4 md:px-margin-desktop hidden md:block my-2 md:my-3">
        <div class="relative w-full rounded-none overflow-hidden bg-transparent" style="aspect-ratio: 1200 / 380;">

            <!-- Slides Wrapper -->
            <div class="hero-slides absolute inset-0">
                <?php foreach ($slides as $index => $slide): ?>
                    <a href="<?= sanitizeOutput($slide['link_url']) ?>" class="hero-slide absolute inset-0 transition-opacity duration-700 ease-in-out <?= $index === 0 ? 'opacity-100 z-20' : 'opacity-0 z-0' ?>" data-slide-index="<?= $index ?>">
                        <!-- Slide Image -->
                        <div class="absolute inset-0 z-0">
                            <img alt="<?= sanitizeOutput($slide['title']) ?>" class="w-full h-full object-cover object-center" src="<?= $slide['image'] ?>"/>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if (count($slides) > 1): ?>
                <!-- Prev / Next Controls -->
                <button class="absolute left-2 md:left-4 top-1/2 -translate-y-1/2 w-8 h-8 md:w-10 md:h-10 rounded-full bg-white/70 hover:bg-white border border-slate-200/50 flex items-center justify-center text-slate-700 hover:text-slate-900 z-30 transition-all active:scale-90 shadow-sm" onclick="prevSlide(event)" aria-label="Previous Slide">
                    <span class="material-symbols-outlined text-sm md:text-base">chevron_left</span>
                </button>
                <button class="absolute right-2 md:right-4 top-1/2 -translate-y-1/2 w-8 h-8 md:w-10 md:h-10 rounded-full bg-white/70 hover:bg-white border border-slate-200/50 flex items-center justify-center text-slate-700 hover:text-slate-900 z-30 transition-all active:scale-90 shadow-sm" onclick="nextSlide(event)" aria-label="Next Slide">
                    <span class="material-symbols-outlined text-sm md:text-base">chevron_right</span>
                </button>

                <!-- Indicators (Dots) -->
                <div class="absolute bottom-2 md:bottom-4 left-1/2 -translate-x-1/2 flex gap-1.5 z-30">
                    <?php foreach ($slides as $index => $slide): ?>
                        <button class="w-2 h-2 md:w-2.5 md:h-2.5 rounded-full transition-all duration-300 <?= $index === 0 ? 'bg-secondary scale-125' : 'bg-slate-400/50 hover:bg-slate-400/80' ?>" onclick="goToSlide(<?= $index ?>, event)" data-indicator-index="<?= $index ?>" aria-label="Go to slide <?= $index + 1 ?>"></button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Promo Banners Grid -->
    <?php if (!empty($promoBanners)): ?>
    <section class="max-w-max-width mx-auto px-4 md:px-margin-desktop hidden md:block my-2 md:my-3">
        <div class="grid grid-cols-1 md:grid-cols-<?= count($promoBanners) ?> gap-4">
            <?php foreach ($promoBanners as $pb): ?>
            <?php 
                $style = $bannerStyles[$pb['index']] ?? $bannerStyles[1]; 
            ?>
            <a href="<?= sanitizeOutput($pb['link']) ?>" class="<?= $style['bg'] ?> p-4 rounded-xl flex items-center justify-between transition-colors group cursor-pointer">
                <div>
                    <h3 class="font-extrabold text-sm <?= $style['title'] ?>"><?= sanitizeOutput($pb['title']) ?></h3>
                    <?php if (!empty($pb['desc'])): ?>
                        <p class="text-[11px] <?= $style['desc'] ?> mt-1"><?= sanitizeOutput($pb['desc']) ?></p>
                    <?php endif; ?>
                    <span class="inline-block mt-2 text-[10px] font-black <?= $style['cta'] ?> uppercase tracking-wider"><?= $style['cta_text'] ?></span>
                </div>
                <div class="w-12 h-12 bg-white rounded-lg flex items-center justify-center border border-gray-100/50 flex-shrink-0 <?= $style['icon'] ?>">
                    <span class="material-symbols-outlined text-3xl"><?= sanitizeOutput($pb['icon']) ?></span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Categories Section (Fixed Icon Mapping & Centered Layout) -->
    <?php if (!empty($categories)): ?>
    <section id="categories" class="max-w-max-width mx-auto px-4 md:px-margin-desktop py-2 md:py-3 animate-fade-in-up">
        <div class="w-full flex flex-col md:items-center">
            <div class="flex flex-nowrap overflow-x-auto hide-scrollbar border border-secondary/20 rounded-xl bg-white divide-x divide-secondary/20 w-full shadow-sm">
                <?php foreach ($categories as $category): ?>
                <?php
                    // Dynamic Material Symbol mapping based on category slug/name
                    $catSlug = $category['slug'] ?? '';
                    $catIcon = 'devices';
                    if (stripos($catSlug, 'laptop') !== false) {
                        $catIcon = 'laptop';
                    } elseif (stripos($catSlug, 'phone') !== false || stripos($catSlug, 'smartphone') !== false) {
                        $catIcon = 'smartphone';
                    } elseif (stripos($catSlug, 'cable') !== false || stripos($catSlug, 'converter') !== false) {
                        $catIcon = 'cable';
                    } elseif (stripos($catSlug, 'peripheral') !== false || stripos($catSlug, 'keyboard') !== false || stripos($catSlug, 'mouse') !== false) {
                        $catIcon = 'keyboard';
                    } elseif (stripos($catSlug, 'storage') !== false || stripos($catSlug, 'memory') !== false) {
                        $catIcon = 'sd_card';
                    } elseif (stripos($catSlug, 'printer') !== false || stripos($catSlug, 'ink') !== false) {
                        $catIcon = 'print';
                    } elseif (stripos($catSlug, 'tool') !== false || stripos($catSlug, 'service') !== false) {
                        $catIcon = 'handyman';
                    }
                ?>
                <a class="flex-shrink-0 min-w-[80px] flex-1 py-3 md:py-4 flex flex-col items-center justify-center gap-1.5 group transition-all duration-200 hover:bg-secondary/[0.04]" href="category?slug=<?= sanitizeOutput($category['slug']) ?>">
                    <?php
                    $imageVal = $category['image'] ?? '';
                    $isUrl = (stripos($imageVal, 'http://') === 0 || stripos($imageVal, 'https://') === 0 || stripos($imageVal, '/') === 0);
                    $isFile = (!empty($imageVal) && !$isUrl && preg_match('/\.(jpg|jpeg|png|webp|gif|svg)$/i', $imageVal) && file_exists('uploads/categories/' . $imageVal));
                    
                    if ($isFile): ?>
                        <img src="uploads/categories/<?= sanitizeOutput($imageVal) ?>" alt="<?= sanitizeOutput($category['name']) ?>" class="w-6 h-6 md:w-7 md:h-7 object-contain group-hover:scale-110 transition-transform duration-200">
                    <?php elseif ($isUrl): ?>
                        <img src="<?= sanitizeOutput($imageVal) ?>" alt="<?= sanitizeOutput($category['name']) ?>" class="w-6 h-6 md:w-7 md:h-7 object-contain group-hover:scale-110 transition-transform duration-200">
                    <?php elseif (!empty($imageVal) && !$isUrl && !$isFile): // Material Symbol ?>
                        <span class="material-symbols-outlined text-secondary text-xl md:text-2xl group-hover:scale-110 transition-transform duration-200"><?= sanitizeOutput($imageVal) ?></span>
                    <?php else: // Slug-based dynamic fallback icon ?>
                        <span class="material-symbols-outlined text-secondary text-xl md:text-2xl group-hover:scale-110 transition-transform duration-200"><?= $catIcon ?></span>
                    <?php endif; ?>
                    
                    <span class="text-[9px] md:text-[10px] font-bold text-center text-on-surface-variant group-hover:text-secondary transition-colors duration-200 leading-tight w-full break-words line-clamp-2 px-1">
                        <?= sanitizeOutput($category['name']) ?>
                    </span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Popular Search Pills Section -->
    <?php 
    $popularSearchesStr = $storeSettings['popular_searches'] ?? '';
    $popularSearches = [];
    if (!empty($popularSearchesStr)) {
        $popularSearches = array_filter(array_map('trim', explode(',', $popularSearchesStr)));
    }
    ?>
    <?php if (!empty($popularSearches)): ?>
    <section class="max-w-max-width mx-auto px-4 md:px-margin-desktop py-2 hidden md:block">
        <div class="bg-white rounded-xl p-4 border border-gray-200 flex flex-wrap items-center gap-3">
            <span class="text-xs font-bold text-on-surface-variant flex items-center gap-1.5">
                <span class="material-symbols-outlined text-[16px] text-secondary">trending_up</span>
                Pencarian Populer:
            </span>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($popularSearches as $keyword): ?>
                <a href="products?search=<?= urlencode($keyword) ?>" class="bg-gray-50 border border-gray-200 hover:border-secondary hover:bg-secondary/5 px-3 py-1.5 rounded-full text-[11px] font-semibold text-on-surface-variant hover:text-secondary transition-colors">
                    <?= sanitizeOutput($keyword) ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($flashSaleProducts) && $fsSeconds > 0 && !empty($storeSettings['flash_sale_active'])): ?>
    <!-- Flash Sale Section (Dynamic DB items with Urgency Progress bar) -->
    <section class="max-w-max-width mx-auto px-4 md:px-margin-desktop">
        <div class="bg-red-600 rounded-xl p-6 md:p-8 flex flex-col lg:flex-row gap-6 items-center relative overflow-hidden">
            
            <div class="text-white min-w-[240px] text-center lg:text-left relative z-10">
                <div class="flex items-center justify-center lg:justify-start gap-2 mb-2">
                    <span class="w-2.5 h-2.5 bg-white rounded-full animate-ping"></span>
                    <h3 class="text-2xl font-black tracking-tight uppercase"><?= sanitizeOutput($storeSettings['flash_sale_title'] ?? 'Flash Sale') ?></h3>
                </div>
                <p class="text-sm text-white/80 mb-4"><?= sanitizeOutput($storeSettings['flash_sale_subtitle'] ?? 'Berakhir dalam:') ?></p>
                <!-- Live Ticking Countdown Box -->
                <div class="flex justify-center lg:justify-start gap-2.5 text-[#0b1c30]">
                    <div class="w-12 h-12 bg-white rounded-lg flex flex-col items-center justify-center font-black text-xl" id="fs-hours">01</div>
                    <div class="w-12 h-12 bg-white rounded-lg flex flex-col items-center justify-center font-black text-xl" id="fs-minutes">32</div>
                    <div class="w-12 h-12 bg-white rounded-lg flex flex-col items-center justify-center font-black text-xl animate-pulse" id="fs-seconds">45</div>
                </div>
            </div>
            
            <div class="flex-grow flex gap-4 overflow-x-auto hide-scrollbar w-full relative z-10 py-2">
                <?php foreach ($flashSaleProducts as $fsProduct): ?>
                <?php 
                $fsImg = !empty($fsProduct['image']) ? 'uploads/products/' . $fsProduct['image'] : 'uploads/products/placeholder.png';
                $originalPrice = (int)$fsProduct['selling_price'];
                $promoPrice = (int)$fsProduct['promo_price'];
                $discountPercent = $originalPrice > 0 ? round((1 - $promoPrice / $originalPrice) * 100) : 0;
                // Dynamically simulate stock level percentages for interactive progress bars
                $soldPercentage = min(92, max(28, ($fsProduct['id'] * 13) % 95));
                ?>
                <div class="w-44 md:w-48 shrink-0 grow-0 bg-white rounded-lg p-3 border border-gray-200 hover:border-gray-400 transition-all flex flex-col relative group cursor-pointer" onclick="window.location.href='product-detail.php?slug=<?= sanitizeOutput($fsProduct['slug']) ?>'">
                    <?php if ($discountPercent > 0): ?>
                        <div class="absolute top-2 left-2 bg-error text-white text-[9px] font-black px-2 py-0.5 rounded-full">HEMAT <?= $discountPercent ?>%</div>
                    <?php endif; ?>
                    
                    <div class="w-full aspect-square bg-surface-container-low rounded-lg overflow-hidden mb-3 p-1 flex items-center justify-center">
                        <img alt="<?= sanitizeOutput($fsProduct['name']) ?>" class="max-w-full max-h-full object-contain transition-transform duration-300" src="<?= $fsImg ?>"/>
                    </div>
                    
                    <h4 class="text-xs font-bold text-on-surface line-clamp-2 min-h-[32px] mb-1.5 leading-tight">
                        <?= sanitizeOutput($fsProduct['name']) ?>
                    </h4>
                    
                    <div class="mt-auto">
                        <p class="text-[11px] text-on-surface-variant/75 line-through leading-none mb-1">
                            <?= formatRupiah($originalPrice) ?>
                        </p>
                        <p class="text-error font-black text-sm leading-none mb-2">
                            <?= formatRupiah($promoPrice) ?>
                        </p>
                        
                        <!-- Progress bar based on real promo stock -->
                        <?php 
                        $pStock = (int)$fsProduct['promo_stock'];
                        $pInitial = (int)$fsProduct['promo_stock_initial'];
                        if ($pInitial <= 0) {
                            $pInitial = max(1, $pStock);
                        }
                        $stockFraction = min(100, max(0, round(($pStock / $pInitial) * 100)));
                        ?>
                        <div class="w-full bg-surface-container rounded-full h-1.5 overflow-hidden">
                            <?php if ($pStock > 0): ?>
                                <div class="bg-gradient-to-r from-error to-orange-500 h-full rounded-full animate-pulse" style="width: <?= $stockFraction ?>%;"></div>
                            <?php else: ?>
                                <div class="bg-outline-variant h-full rounded-full" style="width: 100%;"></div>
                            <?php endif; ?>
                        </div>
                        <p class="text-[9px] font-bold text-on-surface-variant mt-1.5 flex justify-between leading-none">
                            <?php if ($pStock > 0): ?>
                                <span class="opacity-80">Sisa <?= $pStock ?> Pcs</span>
                                <span class="text-error font-black"><?= $pStock <= 5 ? 'Hampir Habis!' : 'Tersedia' ?></span>
                            <?php else: ?>
                                <span class="opacity-80">Terjual 100%</span>
                                <span class="text-on-surface-variant/60 font-black">Habis Terjual</span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Featured Products Section -->
    <?php if (!empty($featuredProducts)): ?>
    <section class="max-w-max-width mx-auto px-4 md:px-margin-desktop py-2">
        <div class="flex items-end justify-between mb-3 md:mb-4">
            <div>
                <h2 class="text-xl md:text-2xl font-extrabold text-on-background leading-tight">Produk Unggulan</h2>
                <p class="text-xs md:text-sm text-on-surface-variant mt-1">Koleksi hardware & aksesoris pilihan terbaik</p>
            </div>
            <a href="products?sort=newest" class="text-secondary font-bold text-xs md:text-sm hover:text-secondary-container flex items-center gap-1 group transition-colors">
                Lihat Semua 
                <span class="material-symbols-outlined text-sm md:text-base group-hover:translate-x-1 transition-transform">arrow_forward</span>
            </a>
        </div>
        
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
            <?php foreach ($featuredProducts as $product): ?>
            <?php 
            $imgSrc = !empty($product['image']) ? 'uploads/products/' . $product['image'] : 'uploads/products/placeholder.png';
            $inWishlist = in_array($product['id'], $_SESSION['wishlist'] ?? [], true);
            ?>
            <div class="group bg-white tech-card flex flex-col overflow-hidden">
                <div class="relative aspect-square overflow-hidden bg-surface-container-low p-2">
                    <img alt="<?= sanitizeOutput($product['name']) ?>" class="w-full h-full object-contain transition-transform duration-300" src="<?= $imgSrc ?>"/>
                    
                    <!-- Wishlist button (Interactive heart) -->
                    <button class="absolute top-2 right-2 w-8 h-8 rounded-full bg-white border border-gray-200 flex items-center justify-center wishlist-btn transition-all hover:scale-105 <?= $inWishlist ? 'active' : '' ?>" onclick="event.stopPropagation(); toggleWishlist(this, <?= (int)$product['id'] ?>);">
                        <span class="material-symbols-outlined text-sm text-on-surface-variant" style="<?= $inWishlist ? "font-variation-settings: 'FILL' 1, 'wght' 400; color: #ba1a1a;" : "" ?>">favorite</span>
                    </button>
                </div>
                
                <div class="p-3 flex flex-col flex-grow">
                    <span class="text-[9px] font-bold text-secondary uppercase tracking-wider mb-1 block">
                        <?= sanitizeOutput($product['category_name']) ?>
                    </span>
                    <h3 class="text-xs font-bold text-on-background line-clamp-2 min-h-[36px] mb-1.5 leading-snug group-hover:text-secondary transition-colors cursor-pointer" onclick="window.location.href='product-detail?slug=<?= sanitizeOutput($product['slug']) ?>'">
                        <?= sanitizeOutput($product['name']) ?>
                    </h3>
                    
                    <?php
                    $isGlobalFlashSaleActive = !empty($storeSettings['flash_sale_active']) && $fsSeconds > 0;
                    $isPromo = $isGlobalFlashSaleActive && !empty($product['promo_active']) && !empty($product['promo_price']) && $product['promo_price'] > 0;
                    $activePrice = $isPromo ? (int)$product['promo_price'] : (int)$product['selling_price'];
                    ?>
                    <div class="mb-2 flex flex-wrap items-baseline gap-1">
                        <p class="text-sm font-black text-on-background"><?= formatRupiah($activePrice) ?></p>
                        <?php if ($isPromo): ?>
                            <p class="text-[10px] font-semibold text-on-surface-variant/70 line-through"><?= formatRupiah((int)$product['selling_price']) ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="flex items-center gap-1.5 mb-2.5 select-none">
                        <span class="text-[10px] font-bold text-on-surface-variant/80">Stok: <?= (int)$product['stock'] ?></span>
                        <span class="text-outline-variant/50 text-[10px]">|</span>
                        <?php if ($product['status'] === 'ready'): ?>
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[8px] font-bold bg-emerald-500/10 text-emerald-700">Ready</span>
                        <?php elseif ($product['status'] === 'po'): ?>
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[8px] font-bold bg-amber-500/10 text-amber-700">Pre-Order</span>
                        <?php else: ?>
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[8px] font-bold bg-red-500/10 text-red-700">Habis</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mt-auto flex items-center justify-between border-t border-outline-variant/30 pt-2.5">
                        <div class="flex items-center gap-0.5 text-on-surface-variant/80">
                            <span class="material-symbols-outlined text-[12px]">location_on</span>
                            <span class="text-[9px] font-semibold">Toko Pusat</span>
                        </div>
                        <?php if ($product['status'] === 'ready' || $product['status'] === 'po'): ?>
                            <form action="actions/cart-add" method="POST" class="inline">
                                <input type="hidden" name="csrf_token" value="<?= sanitizeOutput($csrfToken) ?>">
                                <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
                                <input type="hidden" name="quantity" value="1">
                                <button type="submit" class="w-8 h-8 flex items-center justify-center bg-secondary/5 text-secondary rounded-lg hover:bg-secondary hover:text-white transition-colors" title="Tambah ke keranjang">
                                    <span class="material-symbols-outlined text-sm">add_shopping_cart</span>
                                </button>
                            </form>
                        <?php else: ?>
                            <span class="text-[9px] bg-outline-variant/30 text-on-surface-variant px-2 py-0.5 rounded font-bold">Habis</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Newest Products Section -->
    <?php if (!empty($newestProducts)): ?>
    <section class="max-w-max-width mx-auto px-4 md:px-margin-desktop py-2">
        <div class="flex items-end justify-between mb-3 md:mb-4">
            <div>
                <h2 class="text-xl md:text-2xl font-extrabold text-on-background leading-tight">Produk Terbaru</h2>
                <p class="text-xs md:text-sm text-on-surface-variant mt-1">Temukan hardware & peripheral rilis paling anyar</p>
            </div>
            <a href="products" class="text-secondary font-bold text-xs md:text-sm hover:text-secondary-container flex items-center gap-1 group transition-colors">
                Lihat Semua 
                <span class="material-symbols-outlined text-sm md:text-base group-hover:translate-x-1 transition-transform">arrow_forward</span>
            </a>
        </div>
        
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
            <?php foreach ($newestProducts as $product): ?>
            <?php 
            $imgSrc = !empty($product['image']) ? 'uploads/products/' . $product['image'] : 'uploads/products/placeholder.png';
            $inWishlist = in_array($product['id'], $_SESSION['wishlist'] ?? [], true);
            ?>
            <div class="group bg-white tech-card flex flex-col overflow-hidden">
                <div class="relative aspect-square overflow-hidden bg-surface-container-low p-2">
                    <img alt="<?= sanitizeOutput($product['name']) ?>" class="w-full h-full object-contain transition-transform duration-300" src="<?= $imgSrc ?>"/>
                    
                    <!-- Wishlist button (Interactive heart - fixed product ID parameter) -->
                    <button class="absolute top-2 right-2 w-8 h-8 rounded-full bg-white border border-gray-200 flex items-center justify-center wishlist-btn transition-all hover:scale-105 <?= $inWishlist ? 'active' : '' ?>" onclick="event.stopPropagation(); toggleWishlist(this, <?= (int)$product['id'] ?>);">
                        <span class="material-symbols-outlined text-sm text-on-surface-variant" style="<?= $inWishlist ? "font-variation-settings: 'FILL' 1, 'wght' 400; color: #ba1a1a;" : "" ?>">favorite</span>
                    </button>
                </div>
                
                <div class="p-3 flex flex-col flex-grow">
                    <span class="text-[9px] font-bold text-secondary uppercase tracking-wider mb-1 block">
                        <?= sanitizeOutput($product['category_name']) ?>
                    </span>
                    <h3 class="text-xs font-bold text-on-background line-clamp-2 min-h-[36px] mb-1.5 leading-snug group-hover:text-secondary transition-colors cursor-pointer" onclick="window.location.href='product-detail?slug=<?= sanitizeOutput($product['slug']) ?>'">
                        <?= sanitizeOutput($product['name']) ?>
                    </h3>
                    
                    <?php
                    $isGlobalFlashSaleActive = !empty($storeSettings['flash_sale_active']) && $fsSeconds > 0;
                    $isPromo = $isGlobalFlashSaleActive && !empty($product['promo_active']) && !empty($product['promo_price']) && $product['promo_price'] > 0;
                    $activePrice = $isPromo ? (int)$product['promo_price'] : (int)$product['selling_price'];
                    ?>
                    <div class="mb-2 flex flex-wrap items-baseline gap-1">
                        <p class="text-sm font-black text-on-background"><?= formatRupiah($activePrice) ?></p>
                        <?php if ($isPromo): ?>
                            <p class="text-[10px] font-semibold text-on-surface-variant/70 line-through"><?= formatRupiah((int)$product['selling_price']) ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="flex items-center gap-1.5 mb-2.5 select-none">
                        <span class="text-[10px] font-bold text-on-surface-variant/80">Stok: <?= (int)$product['stock'] ?></span>
                        <span class="text-outline-variant/50 text-[10px]">|</span>
                        <?php if ($product['status'] === 'ready'): ?>
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[8px] font-bold bg-emerald-500/10 text-emerald-700">Ready</span>
                        <?php elseif ($product['status'] === 'po'): ?>
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[8px] font-bold bg-amber-500/10 text-amber-700">Pre-Order</span>
                        <?php else: ?>
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[8px] font-bold bg-red-500/10 text-red-700">Habis</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mt-auto flex items-center justify-between border-t border-outline-variant/30 pt-2.5">
                        <div class="flex items-center gap-0.5 text-on-surface-variant/80">
                            <span class="material-symbols-outlined text-[12px]">location_on</span>
                            <span class="text-[9px] font-semibold">Toko Pusat</span>
                        </div>
                        <?php if ($product['status'] === 'ready' || $product['status'] === 'po'): ?>
                            <form action="actions/cart-add" method="POST" class="inline">
                                <input type="hidden" name="csrf_token" value="<?= sanitizeOutput($csrfToken) ?>">
                                <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
                                <input type="hidden" name="quantity" value="1">
                                <button type="submit" class="w-8 h-8 flex items-center justify-center bg-secondary/5 text-secondary rounded-lg hover:bg-secondary hover:text-white transition-colors" title="Tambah ke keranjang">
                                    <span class="material-symbols-outlined text-sm">add_shopping_cart</span>
                                </button>
                            </form>
                        <?php else: ?>
                            <span class="text-[9px] bg-outline-variant/30 text-on-surface-variant px-2 py-0.5 rounded font-bold">Habis</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Store Advantages Section (Modern Trust Features) -->
    <section class="bg-white py-8 md:py-10 border-y border-gray-200 relative overflow-hidden mt-6">
        <div class="max-w-max-width mx-auto px-4 md:px-margin-desktop grid grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="flex items-center gap-4 group">
                <div class="w-12 h-12 bg-surface-container-low rounded-2xl flex items-center justify-center text-secondary group-hover:bg-secondary group-hover:text-white transition-all duration-300">
                    <span class="material-symbols-outlined text-[26px]">local_shipping</span>
                </div>
                <div>
                    <h4 class="text-sm font-extrabold text-on-background">Pengiriman Aman</h4>
                    <p class="text-xs text-on-surface-variant font-medium mt-0.5">Packing rapi &amp; aman sampai tujuan</p>
                </div>
            </div>
            <div class="flex items-center gap-4 group">
                <div class="w-12 h-12 bg-surface-container-low rounded-2xl flex items-center justify-center text-secondary group-hover:bg-secondary group-hover:text-white transition-all duration-300">
                    <span class="material-symbols-outlined text-[26px]">security</span>
                </div>
                <div>
                    <h4 class="text-sm font-extrabold text-on-background">Garansi Resmi</h4>
                    <p class="text-xs text-on-surface-variant font-medium mt-0.5">100% Produk Original</p>
                </div>
            </div>
            <div class="flex items-center gap-4 group">
                <div class="w-12 h-12 bg-surface-container-low rounded-2xl flex items-center justify-center text-secondary group-hover:bg-secondary group-hover:text-white transition-all duration-300">
                    <span class="material-symbols-outlined text-[26px]">sell</span>
                </div>
                <div>
                    <h4 class="text-sm font-extrabold text-on-background">Harga Bersaing</h4>
                    <p class="text-xs text-on-surface-variant font-medium mt-0.5">Harga terbaik untuk kebutuhan Anda</p>
                </div>
            </div>
            <div class="flex items-center gap-4 group">
                <div class="w-12 h-12 bg-surface-container-low rounded-2xl flex items-center justify-center text-secondary group-hover:bg-secondary group-hover:text-white transition-all duration-300">
                    <span class="material-symbols-outlined text-[26px]">headset_mic</span>
                </div>
                <div>
                    <h4 class="text-sm font-extrabold text-on-background">Layanan Ramah</h4>
                    <p class="text-xs text-on-surface-variant font-medium mt-0.5">Siap membantu kebutuhan Anda</p>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
// Countdown Timer logic for Hero & Flash Sale
function startCountdowns() {
    // Set target time values: 5 hours 12 mins 44 secs for Hero, dynamic time from DB settings for Flash Sale
    let heroSeconds = 5 * 3600 + 12 * 60 + 44;
    let fsSeconds = <?= (int)$fsSeconds ?>;

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
        if (heroSeconds > 0) {
            heroSeconds--;
            const formatted = formatTime(heroSeconds);
            const timerElements = document.querySelectorAll('.hero-timer');
            timerElements.forEach(timerEl => {
                timerEl.textContent = `${formatted.hours}:${formatted.minutes}:${formatted.seconds}`;
            });
        }
        
        if (fsSeconds > 0) {
            fsSeconds--;
            const formatted = formatTime(fsSeconds);
            const elHours = document.getElementById('fs-hours');
            const elMinutes = document.getElementById('fs-minutes');
            const elSeconds = document.getElementById('fs-seconds');
            if (elHours && elMinutes && elSeconds) {
                elHours.textContent = formatted.hours;
                elMinutes.textContent = formatted.minutes;
                elSeconds.textContent = formatted.seconds;
            }
        }

        if (heroSeconds <= 0 && fsSeconds <= 0) {
            clearInterval(timerInterval);
        }
    }, 1000);
}

// Carousel/Slider logic
let currentSlide = 0;
const slides = document.querySelectorAll('.hero-slide');
const indicators = document.querySelectorAll('[data-indicator-index]');
let autoSlideInterval;

function showSlide(index) {
    if (slides.length <= 1) return;
    
    // Wrap index around
    if (index >= slides.length) currentSlide = 0;
    else if (index < 0) currentSlide = slides.length - 1;
    else currentSlide = index;

    // Update slides visibility & animations
    slides.forEach((slide, i) => {
        if (i === currentSlide) {
            slide.classList.remove('opacity-0', 'z-0');
            slide.classList.add('opacity-100', 'z-20');
        } else {
            slide.classList.remove('opacity-100', 'z-20');
            slide.classList.add('opacity-0', 'z-0');
        }
    });

    // Update dot indicators
    indicators.forEach((indicator, i) => {
        if (i === currentSlide) {
            indicator.classList.remove('bg-slate-400/50', 'hover:bg-slate-400/80');
            indicator.classList.add('bg-secondary', 'scale-125');
        } else {
            indicator.classList.remove('bg-secondary', 'scale-125');
            indicator.classList.add('bg-slate-400/50', 'hover:bg-slate-400/80');
        }
    });
}

function nextSlide(e) {
    if (e) {
        e.preventDefault();
        e.stopPropagation();
    }
    showSlide(currentSlide + 1);
    resetAutoSlide();
}

function prevSlide(e) {
    if (e) {
        e.preventDefault();
        e.stopPropagation();
    }
    showSlide(currentSlide - 1);
    resetAutoSlide();
}

function goToSlide(index, e) {
    if (e) {
        e.preventDefault();
        e.stopPropagation();
    }
    showSlide(index);
    resetAutoSlide();
}

function startAutoSlide() {
    if (slides.length <= 1) return;
    autoSlideInterval = setInterval(() => {
        showSlide(currentSlide + 1);
    }, 5000); // 5 seconds interval
}

function resetAutoSlide() {
    clearInterval(autoSlideInterval);
    startAutoSlide();
}

document.addEventListener('DOMContentLoaded', () => {
    startCountdowns();
    startAutoSlide();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
