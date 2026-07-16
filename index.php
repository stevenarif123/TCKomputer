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

// Fetch active categories ordered by sort_order (compact discovery rail, max 12)
$stmtCategories = $pdo->query("SELECT * FROM categories WHERE is_active=1 ORDER BY sort_order ASC LIMIT 12");
$categories = $stmtCategories->fetchAll();
$popularSearches = parsePopularSearches($storeSettings['popular_searches'] ?? null);

// Fetch product rail data (renderers enforce the homepage card limit).
$productRailLimit = 12;
$stmtFeatured = $pdo->query("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.is_featured=1 AND p.is_active=1 ORDER BY p.created_at DESC LIMIT " . $productRailLimit);
$featuredProducts = $stmtFeatured->fetchAll();

$stmtNewest = $pdo->query("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.is_active=1 ORDER BY p.created_at DESC LIMIT " . $productRailLimit);
$newestProducts = $stmtNewest->fetchAll();

// Fetch products for Flash Sale (active products with real promo configurations, limit 6)
$stmtFlashSale = $pdo->query("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.is_active=1 AND p.promo_active=1 AND p.promo_price > 0 AND p.promo_stock > 0 ORDER BY p.id ASC LIMIT 6");
$flashSaleProducts = $stmtFlashSale->fetchAll();



// Hero Banner Data Integration
$slides = [];
if (!empty($banners)) {
    foreach ($banners as $b) {
        $bannerImage = trim((string)($b['image'] ?? ''));
        $slides[] = [
            'title' => (string)($b['title'] ?? ''),
            'description' => (string)($b['description'] ?? ''),
            'image' => $bannerImage !== '' ? 'uploads/banners/' . $bannerImage : 'assets/images/placeholder.svg',
            'link_url' => trim((string)($b['link_url'] ?? '')) !== '' ? (string)$b['link_url'] : 'products',
            'is_promo' => isHomepagePromoShortcutBanner($b),
            'is_fallback' => false,
        ];
    }
} else {
    // Approved static store introduction fallback; no campaign/promo banner is fabricated.
    $slides[] = [
        'title' => 'Selamat Datang di TC Komputer',
        'description' => 'Menyediakan perangkat IT, komputer, dan aksesoris berkualitas tinggi dan asli untuk workspace produktif Anda.',
        'image' => 'assets/images/placeholder.svg',
        'link_url' => 'products',
        'is_promo' => false,
        'is_fallback' => true,
    ];
}

// Promo Grid Banners Data Integration
$promoBanners = extractHomepagePromoShortcuts($storeSettings, 3);
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
    <div class="max-w-max-width mx-auto px-4 md:px-margin-desktop mt-3 md:mt-0">
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

    <!-- Modern Mobile Welcome Banner (Only on Mobile/Tablet) -->
    <section class="max-w-max-width mx-auto px-4 lg:hidden mt-3 mb-3">
        <div class="bg-gradient-to-r from-secondary to-blue-700 rounded-xl p-5 text-white shadow-sm flex flex-col justify-between relative overflow-hidden select-none">
            <!-- Decorative background elements -->
            <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-white/10 rounded-full blur-xl"></div>
            <div class="absolute -left-6 -top-6 w-20 h-20 bg-white/5 rounded-full blur-lg"></div>
            
            <div class="relative z-10">
                <span class="inline-block bg-white/20 text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-full tracking-wider mb-2">Selamat Datang</span>
                <h2 class="text-lg font-black tracking-tight leading-tight">TC Komputer Toraja</h2>
                <p class="text-xs text-white/80 mt-1 max-w-[90%] font-medium">Solusi Kebutuhan IT & Aksesoris Terpercaya dengan Jaminan Asli.</p>
            </div>
            
            <div class="mt-4 flex items-center justify-between relative z-10">
                <a href="products" class="px-4 py-2 bg-white text-secondary hover:bg-secondary-container hover:text-white rounded-lg text-xs font-bold shadow-sm transition-all duration-300 transform active:scale-95 flex items-center gap-1">
                    <span>Lihat Semua Produk</span>
                    <span class="material-symbols-outlined text-xs">arrow_forward</span>
                </a>
                <div class="w-8 h-8 rounded-full bg-white/15 flex items-center justify-center text-white">
                    <span class="material-symbols-outlined text-sm">devices</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Mobile Search Bar (Only on Mobile/Tablet) -->
    <div class="block lg:hidden px-4 mb-1 animate-fade-in-up">
        <form action="products" method="GET" class="relative">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-xl">search</span>
            <input name="search" class="w-full bg-white border border-outline-variant/80 rounded-xl pl-10 pr-4 py-3 text-body-sm focus:border-secondary transition-colors outline-none shadow-sm" placeholder="Cari hardware, printer, aksesoris..." type="search" value="<?= sanitizeOutput($_GET['search'] ?? '') ?>"/>
        </form>
    </div>

    <!-- Compact Hero Marketplace Cluster -->
    <section class="max-w-max-width mx-auto px-4 md:px-margin-desktop my-2 md:my-3 hidden lg:block" aria-label="Promo utama TC Komputer">
        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,2fr)_minmax(280px,1fr)] gap-3 lg:gap-4 items-stretch">
            <div class="relative w-full max-h-[220px] md:max-h-[360px] overflow-hidden" style="aspect-ratio: 1200 / 380;">
                <div class="hero-slides absolute inset-0">
                    <?php foreach ($slides as $index => $slide): ?>
                    <a href="<?= sanitizeOutput($slide['link_url']) ?>" class="hero-slide absolute inset-0 transition-opacity duration-700 ease-in-out <?= $index === 0 ? 'opacity-100 z-20' : 'opacity-0 z-0' ?>" data-slide-index="<?= (int)$index ?>">
                        <div class="absolute inset-0 flex items-center justify-center">
                            <img alt="<?= sanitizeOutput($slide['title']) ?>" class="w-full h-full object-cover object-center" src="<?= sanitizeOutput($slide['image']) ?>"/>
                        </div>
                        <?php if (!empty($slide['is_fallback'])): ?>
                        <div class="absolute inset-0 flex items-center justify-center bg-gradient-to-br from-white/95 via-white/90 to-secondary/10 p-6 text-center">
                            <div class="max-w-xl">
                                <p class="text-[10px] md:text-xs font-black uppercase tracking-[0.24em] text-secondary mb-2">TC Komputer</p>
                                <h1 class="text-xl md:text-3xl font-black text-on-background leading-tight"><?= sanitizeOutput($slide['title']) ?></h1>
                                <p class="mt-2 text-xs md:text-sm font-medium text-on-surface-variant leading-relaxed"><?= sanitizeOutput($slide['description']) ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                </div>

                <?php if (count($slides) > 1): ?>
                <button class="absolute left-2 md:left-4 top-1/2 -translate-y-1/2 w-8 h-8 md:w-10 md:h-10 rounded-full bg-white/80 hover:bg-white border border-slate-200/70 flex items-center justify-center text-slate-700 hover:text-slate-900 z-30 transition-all active:scale-90 shadow-sm" onclick="prevSlide(event)" aria-label="Previous Slide">
                    <span class="material-symbols-outlined text-sm md:text-base">chevron_left</span>
                </button>
                <button class="absolute right-2 md:right-4 top-1/2 -translate-y-1/2 w-8 h-8 md:w-10 md:h-10 rounded-full bg-white/80 hover:bg-white border border-slate-200/70 flex items-center justify-center text-slate-700 hover:text-slate-900 z-30 transition-all active:scale-90 shadow-sm" onclick="nextSlide(event)" aria-label="Next Slide">
                    <span class="material-symbols-outlined text-sm md:text-base">chevron_right</span>
                </button>
                <?php endif; ?>
            </div>

            <?php if (!empty($promoBanners)): ?>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-1 gap-2 lg:gap-3 lg:max-h-[360px]">
                <?php foreach ($promoBanners as $pb): ?>
                <?php
                    $style = $bannerStyles[$pb['index']] ?? $bannerStyles[1];
                    $promoIcon = $pb['icon'] !== '' ? $pb['icon'] : 'campaign';
                    $promoLink = $pb['link'] !== '' ? $pb['link'] : '#';
                ?>
                <a href="<?= sanitizeOutput($promoLink) ?>" class="<?= $style['bg'] ?> min-h-[88px] lg:min-h-0 lg:h-full px-2 md:px-3 border flex items-center justify-between gap-2 transition-colors group cursor-pointer overflow-hidden">
                    <div class="min-w-0">
                        <h3 class="font-extrabold text-xs md:text-sm <?= $style['title'] ?> line-clamp-2"><?= sanitizeOutput($pb['title']) ?></h3>
                        <?php if (!empty($pb['desc'])): ?>
                        <p class="text-[10px] md:text-[11px] <?= $style['desc'] ?> mt-1 line-clamp-2"><?= sanitizeOutput($pb['desc']) ?></p>
                        <?php endif; ?>
                        <span class="inline-block mt-2 text-[9px] md:text-[10px] font-black <?= $style['cta'] ?> uppercase tracking-wider"><?= $style['cta_text'] ?></span>
                    </div>
                    <div class="w-10 h-10 md:w-12 md:h-12 bg-white rounded-lg flex items-center justify-center border border-gray-100/50 flex-shrink-0 <?= $style['icon'] ?>">
                        <span class="material-symbols-outlined text-2xl md:text-3xl"><?= sanitizeOutput($promoIcon) ?></span>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Discovery Rail: active categories only -->
    <section id="categories" class="max-w-max-width mx-auto px-4 md:px-margin-desktop pt-1 pb-1 md:py-3 animate-fade-in-up">
        <div class="bg-white overflow-hidden">
            <div class="flex items-center gap-3 overflow-x-auto hide-scrollbar px-3 py-3 md:px-4 md:py-3" aria-label="Jelajahi kategori">
                <!-- Semua Produk category shortcut for mobile-first layout -->
                <a class="category-card flex-shrink-0 w-[86px] md:w-[96px] py-1 px-2 flex flex-col items-center justify-center gap-1 group transition-all duration-200 hover:bg-secondary/[0.04] rounded-none" href="products">
                    <div class="w-10 h-10 md:w-12 md:h-12 flex items-center justify-center group-hover:scale-110 transition-transform duration-200">
                        <span class="material-symbols-outlined text-secondary text-xl md:text-2xl group-hover:scale-110 transition-transform duration-200">grid_view</span>
                    </div>
                    <span class="text-[9px] md:text-[10px] font-bold text-center text-on-surface-variant group-hover:text-secondary transition-colors duration-200 leading-tight w-full break-words line-clamp-2">
                        Semua Produk
                    </span>
                </a>

                <?php foreach ($categories as $category): ?>
                <?php
                    $categoryId = (int)($category['id'] ?? 0);
                    $catSlug = (string)($category['slug'] ?? '');
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
                <a class="category-card flex-shrink-0 w-[86px] md:w-[96px] py-1 px-2 flex flex-col items-center justify-center gap-1 group transition-all duration-200 hover:bg-secondary/[0.04] rounded-none" href="category?slug=<?= sanitizeOutput($catSlug) ?>" data-category-id="<?= $categoryId ?>">
                    <?php
                    $imageVal = $category['image'] ?? '';
                    $isUrl = (stripos($imageVal, 'http://') === 0 || stripos($imageVal, 'https://') === 0 || stripos($imageVal, '/') === 0);
                    $isFile = (!empty($imageVal) && !$isUrl && preg_match('/\.(jpg|jpeg|png|webp|gif|svg)$/i', $imageVal) && file_exists('uploads/categories/' . $imageVal));
                    ?>
                    <?php if ($isFile): ?>
                        <img src="uploads/categories/<?= sanitizeOutput($imageVal) ?>" alt="<?= sanitizeOutput($category['name'] ?? '') ?>" class="w-6 h-6 md:w-7 md:h-7 object-contain group-hover:scale-110 transition-transform duration-200">
                    <?php elseif ($isUrl): ?>
                        <img src="<?= sanitizeOutput($imageVal) ?>" alt="<?= sanitizeOutput($category['name'] ?? '') ?>" class="w-6 h-6 md:w-7 md:h-7 object-contain group-hover:scale-110 transition-transform duration-200">
                    <?php elseif (!empty($imageVal) && !$isUrl && !$isFile): ?>
                        <span class="material-symbols-outlined text-secondary text-xl md:text-2xl group-hover:scale-110 transition-transform duration-200"><?= sanitizeOutput($imageVal) ?></span>
                    <?php else: ?>
                        <span class="material-symbols-outlined text-secondary text-xl md:text-2xl group-hover:scale-110 transition-transform duration-200"><?= $catIcon ?></span>
                    <?php endif; ?>
                    <span class="text-[9px] md:text-[10px] font-bold text-center text-on-surface-variant group-hover:text-secondary transition-colors duration-200 leading-tight w-full break-words line-clamp-2">
                        <?= sanitizeOutput($category['name'] ?? '') ?>
                    </span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Popular Searches: source-backed chips from store settings -->
    <?php if (!empty($popularSearches)): ?>
    <section class="max-w-max-width mx-auto px-4 md:px-margin-desktop pt-0 pb-1 md:py-2 animate-fade-in-up" aria-label="Pencarian populer">
        <div class="flex items-center gap-2 overflow-x-auto hide-scrollbar">
            <span class="flex-shrink-0 text-[10px] md:text-xs font-black text-on-surface-variant flex items-center gap-1">
                <span class="material-symbols-outlined text-[15px] text-secondary">trending_up</span>
                Populer
            </span>
            <?php foreach ($popularSearches as $keyword): ?>
                <a href="products?search=<?= urlencode($keyword) ?>" class="flex-shrink-0 bg-gray-50 border border-gray-200 hover:border-secondary hover:bg-secondary/5 px-3 py-1.5 rounded-full text-[11px] font-semibold text-on-surface-variant hover:text-secondary transition-colors">
                    <?= sanitizeOutput($keyword) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($flashSaleProducts) && $fsSeconds > 0 && !empty($storeSettings['flash_sale_active'])): ?>
    <!-- Flash Sale Section: real active promo products only -->
    <section class="max-w-max-width mx-auto px-4 md:px-margin-desktop">
        <div class="bg-red-600 rounded-xl p-4 md:p-6 flex flex-col lg:flex-row gap-5 items-center relative overflow-hidden">
            <div class="text-white min-w-[220px] text-center lg:text-left relative z-10">
                <div class="flex items-center justify-center lg:justify-start gap-2 mb-2">
                    <span class="w-2.5 h-2.5 bg-white rounded-full"></span>
                    <h3 class="text-xl md:text-2xl font-black tracking-tight uppercase"><?= sanitizeOutput($storeSettings['flash_sale_title'] ?? 'Flash Sale') ?></h3>
                </div>
                <p class="text-sm text-white/80 mb-4"><?= sanitizeOutput($storeSettings['flash_sale_subtitle'] ?? 'Berakhir dalam:') ?></p>
                <div class="flex justify-center lg:justify-start gap-2.5 text-[#0b1c30]" aria-label="Hitung mundur flash sale">
                    <div class="w-12 h-12 bg-white rounded-lg flex items-center justify-center font-black text-xl" id="fs-hours">00</div>
                    <div class="w-12 h-12 bg-white rounded-lg flex items-center justify-center font-black text-xl" id="fs-minutes">00</div>
                    <div class="w-12 h-12 bg-white rounded-lg flex items-center justify-center font-black text-xl" id="fs-seconds">00</div>
                </div>
            </div>

            <div class="flex-grow flex gap-4 overflow-x-auto hide-scrollbar w-full relative z-10 py-2">
                <?php foreach ($flashSaleProducts as $fsProduct): ?>
                <?php
                $fsImg = resolveHomepageProductImage($fsProduct);
                $slug = (string)($fsProduct['slug'] ?? '');
                $price = determineActivePrice($fsProduct, true);
                $stockPercent = calculatePromoStockPercent($fsProduct);
                $promoStock = (int)($fsProduct['promo_stock'] ?? 0);
                ?>
                <a class="w-44 md:w-48 shrink-0 grow-0 bg-white rounded-lg p-3 border border-gray-200 hover:border-gray-400 transition-all flex flex-col relative group" href="product-detail?slug=<?= rawurlencode($slug) ?>">
                    <div class="w-full aspect-square bg-surface-container-low rounded-lg overflow-hidden mb-3 p-1 flex items-center justify-center">
                        <img alt="<?= sanitizeOutput($fsProduct['name'] ?? '') ?>" class="max-w-full max-h-full object-contain transition-transform duration-300 group-hover:scale-105" src="<?= sanitizeOutput($fsImg) ?>" loading="lazy"/>
                    </div>
                    <h4 class="text-xs font-bold text-on-surface line-clamp-2 min-h-[32px] mb-1.5 leading-tight"><?= sanitizeOutput($fsProduct['name'] ?? '') ?></h4>
                    <div class="mt-auto">
                        <?php if ($price['is_promo'] && $price['original_price'] > $price['price']): ?>
                            <p class="text-[11px] text-on-surface-variant/75 line-through leading-none mb-1"><?= formatRupiah($price['original_price']) ?></p>
                        <?php endif; ?>
                        <p class="text-error font-black text-sm leading-none mb-2"><?= formatRupiah($price['price']) ?></p>
                        <?php if ($stockPercent !== null): ?>
                            <div class="w-full bg-surface-container rounded-full h-1.5 overflow-hidden" aria-label="Sisa stok promo <?= $stockPercent ?> persen">
                                <div class="bg-gradient-to-r from-error to-orange-500 h-full rounded-full" style="width: <?= $stockPercent ?>%;"></div>
                            </div>
                            <p class="text-[9px] font-bold text-on-surface-variant mt-1.5 leading-none">Sisa <?= $promoStock ?> Pcs</p>
                        <?php endif; ?>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Product Rails -->
    <?= renderHomepageProductRail([
        'title' => 'Produk Unggulan',
        'subtitle' => 'Koleksi hardware & aksesoris pilihan terbaik',
        'view_all_url' => 'products?sort=newest',
        'products' => $featuredProducts,
        'limit' => $productRailLimit,
    ], $csrfToken, !empty($storeSettings['flash_sale_active']) && $fsSeconds > 0, $_SESSION['wishlist'] ?? []) ?>

    <?= renderHomepageProductRail([
        'title' => 'Produk Terbaru',
        'subtitle' => 'Temukan hardware & peripheral rilis paling anyar',
        'view_all_url' => 'products',
        'products' => $newestProducts,
        'limit' => $productRailLimit,
    ], $csrfToken, !empty($storeSettings['flash_sale_active']) && $fsSeconds > 0, $_SESSION['wishlist'] ?? []) ?>

    <!-- Compact Trust Strip -->
    <section class="max-w-max-width mx-auto px-4 md:px-margin-desktop mt-4 mb-2" aria-label="Kepercayaan belanja TC Komputer">
        <div class="bg-white border border-outline-variant/40 rounded-xl shadow-sm px-3 py-3 md:px-4 md:py-3 grid grid-cols-2 lg:grid-cols-4 gap-2 md:gap-3">
            <div class="flex items-center gap-2.5 min-w-0">
                <span class="material-symbols-outlined text-secondary text-[22px] flex-shrink-0">local_shipping</span>
                <p class="text-xs md:text-sm font-extrabold text-on-background truncate">Pengiriman Aman</p>
            </div>
            <div class="flex items-center gap-2.5 min-w-0">
                <span class="material-symbols-outlined text-secondary text-[22px] flex-shrink-0">verified_user</span>
                <p class="text-xs md:text-sm font-extrabold text-on-background truncate">100% Asli</p>
            </div>
            <div class="flex items-center gap-2.5 min-w-0">
                <span class="material-symbols-outlined text-secondary text-[22px] flex-shrink-0">sell</span>
                <p class="text-xs md:text-sm font-extrabold text-on-background truncate">Harga Bersaing</p>
            </div>
            <div class="flex items-center gap-2.5 min-w-0">
                <span class="material-symbols-outlined text-secondary text-[22px] flex-shrink-0">support_agent</span>
                <p class="text-xs md:text-sm font-extrabold text-on-background truncate">Layanan Ramah</p>
            </div>
        </div>
    </section>
</div>

<script>
// Countdown Timer logic for Flash Sale
function startCountdowns() {
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

    function renderFlashSaleCountdown() {
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

    if (fsSeconds <= 0) return;
    renderFlashSaleCountdown();

    const timerInterval = setInterval(() => {
        fsSeconds--;
        renderFlashSaleCountdown();

        if (fsSeconds <= 0) {
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

