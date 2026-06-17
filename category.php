<?php
/**
 * Category Page
 * Displays category info and filtered product grid for a specific category.
 * Supports search, sort, and pagination (12 per page).
 * Shows 404 if category slug not found or inactive.
 */

include 'includes/header.php';

// Get category slug from URL
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';

if (empty($slug)) {
    http_response_code(404);
    ?>
    <div class="max-w-max-width mx-auto px-4 md:px-margin-desktop py-xl text-center">
        <h1 class="text-headline-xl font-black text-on-background">404</h1>
        <p class="text-body-lg text-on-surface-variant mb-md">Kategori tidak ditemukan.</p>
        <a href="products" class="px-md py-3 bg-secondary text-white font-bold rounded-lg transition-colors inline-block">Lihat Semua Produk</a>
    </div>
    <?php
    include 'includes/footer.php';
    exit;
}

// Fetch category by slug (must be active)
$stmtCat = $pdo->prepare("SELECT * FROM categories WHERE slug = ? AND is_active = 1");
$stmtCat->execute([$slug]);
$category = $stmtCat->fetch();

if (!$category) {
    http_response_code(404);
    ?>
    <div class="max-w-max-width mx-auto px-4 md:px-margin-desktop py-xl text-center">
        <h1 class="text-headline-xl font-black text-on-background">404</h1>
        <p class="text-body-lg text-on-surface-variant mb-md">Kategori tidak ditemukan atau sudah tidak aktif.</p>
        <a href="products" class="px-md py-3 bg-secondary text-white font-bold rounded-lg transition-colors inline-block">Lihat Semua Produk</a>
    </div>
    <?php
    include 'includes/footer.php';
    exit;
}

// Get filter/sort parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'newest';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 12;

// Validate sort option
$allowedSorts = ['newest', 'cheapest', 'expensive'];
if (!in_array($sort, $allowedSorts, true)) {
    $sort = 'newest';
}

// Validate status filter
$allowedStatuses = ['', 'ready', 'po'];
if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
}

// Build query
$baseQuery = "SELECT p.*, c.name AS category_name FROM products p 
              LEFT JOIN categories c ON p.category_id = c.id 
              WHERE p.is_active = 1 AND p.category_id = ?";
$params = [$category['id']];

// Search filter
if (!empty($search)) {
    $baseQuery .= " AND (p.name LIKE ? OR p.brand LIKE ? OR p.description LIKE ?)";
    $searchWild = '%' . $search . '%';
    $params[] = $searchWild;
    $params[] = $searchWild;
    $params[] = $searchWild;
}

// Status filter
if (!empty($statusFilter)) {
    $baseQuery .= " AND p.status = ?";
    $params[] = $statusFilter;
}

// Sort order
switch ($sort) {
    case 'cheapest':
        $baseQuery .= " ORDER BY p.selling_price ASC";
        break;
    case 'expensive':
        $baseQuery .= " ORDER BY p.selling_price DESC";
        break;
    case 'newest':
    default:
        $baseQuery .= " ORDER BY p.created_at DESC";
        break;
}

// Paginate results
$result = paginate($pdo, $baseQuery, $params, $perPage, $page);
$products = $result['data'];
$totalPages = $result['pages'];
$currentPage = $result['current_page'];
$totalProducts = $result['total'];

// Generate CSRF Token for add-to-cart form
$csrfToken = generateCSRFToken();
?>

<div class="max-w-max-width mx-auto px-4 md:px-margin-desktop py-3 md:py-lg animate-fade-in-up">
    <!-- Breadcrumbs -->
    <nav class="flex items-center gap-xs text-body-sm text-on-surface-variant mb-3 md:mb-lg">
        <a class="hover:text-secondary transition-colors" href="index">Beranda</a>
        <span class="material-symbols-outlined text-[16px] text-outline-variant">chevron_right</span>
        <a class="hover:text-secondary transition-colors" href="categories">Kategori</a>
        <span class="material-symbols-outlined text-[16px] text-outline-variant">chevron_right</span>
        <span class="text-on-surface font-semibold"><?= sanitizeOutput($category['name']) ?></span>
    </nav>

    <!-- Category Header Banner (Desktop Only) -->
    <div class="relative bg-slate-900 rounded-xl p-6 md:p-10 text-white overflow-hidden mb-lg hidden md:block">
        <div class="relative z-10 flex flex-col md:flex-row items-center gap-md">
            <?php
            $imageVal = $category['image'] ?? '';
            $isUrl = (stripos($imageVal, 'http://') === 0 || stripos($imageVal, 'https://') === 0 || stripos($imageVal, '/') === 0);
            $isFile = (!empty($imageVal) && !$isUrl && preg_match('/\.(jpg|jpeg|png|webp|gif|svg)$/i', $imageVal) && file_exists('uploads/categories/' . $imageVal));
            
            if (!empty($imageVal)): ?>
                <div class="w-24 h-24 rounded-lg bg-white/10 p-3 flex items-center justify-center border border-white/10 flex-shrink-0">
                    <?php if ($isFile): ?>
                        <img src="uploads/categories/<?= sanitizeOutput($imageVal) ?>" alt="<?= sanitizeOutput($category['name']) ?>" class="max-w-full max-h-full object-contain brightness-0 invert">
                    <?php elseif ($isUrl): ?>
                        <img src="<?= sanitizeOutput($imageVal) ?>" alt="<?= sanitizeOutput($category['name']) ?>" class="max-w-full max-h-full object-contain brightness-0 invert">
                    <?php else: // Material Symbol ?>
                        <span class="material-symbols-outlined text-white text-5xl"><?= sanitizeOutput($imageVal) ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <div class="text-center md:text-left">
                <h1 class="text-headline-md md:text-[32px] font-black tracking-tight leading-tight mb-1"><?= sanitizeOutput($category['name']) ?></h1>
                <?php if (!empty($category['description'])): ?>
                    <p class="text-body-sm text-white/70 max-w-xl leading-relaxed"><?= sanitizeOutput($category['description']) ?></p>
                <?php else: ?>
                    <p class="text-body-sm text-white/70 max-w-xl leading-relaxed">Kumpulan pilihan hardware <?= sanitizeOutput($category['name']) ?> dengan kualitas terbaik.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Category Mobile Title (Simple & Clean, Mobile Only) -->
    <div class="md:hidden mb-4 px-1">
        <h1 class="text-xl font-extrabold text-on-background"><?= sanitizeOutput($category['name']) ?></h1>
        <?php if (!empty($category['description'])): ?>
            <p class="text-xs text-on-surface-variant mt-1"><?= sanitizeOutput($category['description']) ?></p>
        <?php endif; ?>
    </div>

    <!-- Filter Form -->
    <form method="GET" action="category" class="bg-white p-4 md:p-5 rounded-xl border border-outline-variant/40 mb-lg">
        <input type="hidden" name="slug" value="<?= sanitizeOutput($slug) ?>">
        <div class="flex flex-col md:grid md:grid-cols-12 gap-sm items-stretch md:items-end">
            <!-- Search field (Always visible) -->
            <div class="space-y-1 flex-grow md:col-span-4">
                <label for="filter-search" class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider hidden md:block">Cari Produk</label>
                <div class="flex gap-2">
                    <div class="relative flex-grow">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-[18px]">search</span>
                        <input type="text" id="filter-search" name="search" placeholder="Cari nama, brand..." value="<?= sanitizeOutput($search) ?>" class="w-full pl-9 pr-4 py-2.5 border border-outline-variant/85 rounded-lg text-body-sm bg-surface-container-lowest outline-none"/>
                    </div>
                    <!-- Toggle filters button for mobile only -->
                    <button type="button" onclick="toggleMobileFilters()" class="px-3.5 py-2 border border-outline-variant/85 rounded-lg text-body-sm font-bold text-on-surface flex items-center gap-1 bg-surface-container-lowest md:hidden">
                        <span class="material-symbols-outlined text-[18px]">filter_list</span> Filter
                    </button>
                </div>
            </div>

            <!-- Collapsible Filters Area (Mobile: hidden by default, Desktop: always visible) -->
            <div id="filters-form-fields" class="hidden md:contents">
                <div class="space-y-1 md:col-span-3">
                    <label for="filter-status" class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider block">Status Stok</label>
                    <select id="filter-status" name="status" class="w-full px-4 py-2.5 border border-outline-variant/85 rounded-lg text-body-sm bg-surface-container-lowest outline-none">
                        <option value="">Semua Status</option>
                        <option value="ready" <?= $statusFilter === 'ready' ? 'selected' : '' ?>>Ready</option>
                        <option value="po" <?= $statusFilter === 'po' ? 'selected' : '' ?>>Pre-Order</option>
                    </select>
                </div>

                <div class="space-y-1 md:col-span-3">
                    <label for="filter-sort" class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider block">Urutkan</label>
                    <select id="filter-sort" name="sort" class="w-full px-4 py-2.5 border border-outline-variant/85 rounded-lg text-body-sm bg-surface-container-lowest outline-none">
                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Terbaru</option>
                        <option value="cheapest" <?= $sort === 'cheapest' ? 'selected' : '' ?>>Termurah</option>
                        <option value="expensive" <?= $sort === 'expensive' ? 'selected' : '' ?>>Termahal</option>
                    </select>
                </div>

                <div class="md:col-span-2 pt-2 md:pt-0">
                    <button type="submit" class="w-full bg-secondary hover:bg-secondary-container text-white py-2 rounded-lg font-bold text-label-md transition-colors flex items-center justify-center gap-1">
                        <span class="material-symbols-outlined text-md">filter_alt</span>
                        Terapkan
                    </button>
                </div>
            </div>
        </div>
    </form>

    <script>
    function toggleMobileFilters() {
        const fields = document.getElementById('filters-form-fields');
        if (fields) {
            if (fields.classList.contains('hidden')) {
                fields.classList.remove('hidden');
                fields.classList.add('flex', 'flex-col', 'gap-sm', 'mt-4');
            } else {
                fields.classList.add('hidden');
                fields.classList.remove('flex', 'flex-col', 'gap-sm', 'mt-4');
            }
        }
    }
    </script>

    <p class="text-body-sm text-on-surface-variant mb-md px-1">Menampilkan <?= count($products) ?> dari <?= $totalProducts ?> produk dalam kategori ini</p>

    <?php if (empty($products)): ?>
        <div class="bg-white border border-outline-variant/60 rounded-xl p-6 md:p-12 text-center max-w-md mx-auto space-y-md">
            <span class="material-symbols-outlined text-6xl text-on-surface-variant/40">shopping_bag</span>
            <div>
                <h2 class="text-headline-md font-extrabold text-on-background">Produk Kosong</h2>
                <p class="text-body-sm text-on-surface-variant mt-1">Tidak ada produk yang cocok dengan kriteria filter di kategori ini.</p>
            </div>
            <a href="category?slug=<?= sanitizeOutput($slug) ?>" class="px-xl py-3 bg-secondary text-white font-bold text-label-md rounded-lg hover:bg-secondary-container transition-colors inline-block">
                Reset Filter
            </a>
        </div>
    <?php else: ?>
        <!-- Product Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-sm mb-xl">
            <?php foreach ($products as $product): ?>
            <?php 
            $imgSrc = !empty($product['image']) ? 'uploads/products/' . $product['image'] : 'uploads/products/placeholder.png';
            $inWishlist = in_array($product['id'], $_SESSION['wishlist'] ?? [], true);
            ?>
            <div class="group bg-white border border-outline-variant/60 tech-card flex flex-col overflow-hidden">
                <div class="relative aspect-square overflow-hidden bg-surface-container-low p-2">
                    <img alt="<?= sanitizeOutput($product['name']) ?>" class="w-full h-full object-contain group-hover:scale-105 transition-transform duration-500" src="<?= $imgSrc ?>"/>
                    <button class="absolute top-2 right-2 w-8 h-8 rounded-full bg-white border border-gray-200 flex items-center justify-center wishlist-btn transition-colors hover:bg-white <?= $inWishlist ? 'active' : '' ?>" onclick="event.stopPropagation(); toggleWishlist(this, <?= (int)$product['id'] ?>);">
                        <span class="material-symbols-outlined text-sm text-on-surface-variant" style="<?= $inWishlist ? "font-variation-settings: 'FILL' 1, 'wght' 400; color: #ba1a1a;" : "" ?>">favorite</span>
                    </button>
                </div>
                <div class="p-3 flex flex-col flex-grow">
                    <h3 class="text-body-sm font-semibold text-on-background line-clamp-2 min-h-[40px] mb-1 group-hover:text-secondary transition-colors cursor-pointer" onclick="window.location.href='product-detail?slug=<?= sanitizeOutput($product['slug']) ?>'"><?= sanitizeOutput($product['name']) ?></h3>
                    <?php 
                    $isPromo = $isGlobalFlashSaleActive && !empty($product['promo_active']) && !empty($product['promo_price']) && $product['promo_price'] > 0 && isset($product['promo_stock']) && $product['promo_stock'] > 0;
                    $activePrice = $isPromo ? (int)$product['promo_price'] : (int)$product['selling_price'];
                    ?>
                    <div class="flex flex-wrap items-baseline gap-1.5 mb-1">
                        <p class="text-body-md font-bold text-on-background"><?= formatRupiah($activePrice) ?></p>
                        <?php if ($isPromo): ?>
                            <p class="text-[10px] font-semibold text-on-surface-variant/70 line-through"><?= formatRupiah((int)$product['selling_price']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="flex items-center gap-1.5 mb-2 select-none">
                        <span class="text-[11px] font-semibold text-on-surface-variant">Stok: <?= (int)$product['stock'] ?></span>
                        <span class="text-outline-variant/50 text-[10px]">|</span>
                        <?php if ($product['status'] === 'ready'): ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-bold bg-secondary-container/10 text-secondary-container">Ready</span>
                        <?php elseif ($product['status'] === 'po'): ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-bold bg-on-tertiary-container/10 text-on-tertiary-fixed-variant">Pre-Order</span>
                        <?php else: ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-bold bg-error/10 text-error">Habis</span>
                        <?php endif; ?>
                    </div>
                    <div class="mt-auto flex items-center justify-between border-t border-outline-variant/30 pt-2">
                        <div class="flex items-center gap-0.5">
                            <span class="material-symbols-outlined text-[13px] text-on-surface-variant">location_on</span>
                            <span class="text-[10px] text-on-surface-variant font-medium">Toko Pusat</span>
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
                            <span class="text-[10px] bg-outline-variant/30 text-on-surface-variant px-2 py-0.5 rounded font-bold">Habis</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="flex justify-center items-center gap-xs">
                <?php
                $queryParams = [
                    'slug' => $slug,
                ];
                if (!empty($search)) $queryParams['search'] = $search;
                if (!empty($statusFilter)) $queryParams['status'] = $statusFilter;
                if ($sort !== 'newest') $queryParams['sort'] = $sort;
                ?>

                <!-- Prev button -->
                <?php if ($currentPage > 1): ?>
                    <a href="category?<?= http_build_query(array_merge($queryParams, ['page' => $currentPage - 1])) ?>" class="w-10 h-10 border border-outline-variant hover:border-secondary flex items-center justify-center rounded-lg bg-white hover:bg-secondary/5 font-bold transition-colors text-secondary">&laquo;</a>
                <?php endif; ?>

                <!-- Page numbers -->
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <?php if ($i === $currentPage): ?>
                        <span class="w-10 h-10 bg-secondary text-white flex items-center justify-center rounded-lg font-bold"><?= $i ?></span>
                    <?php else: ?>
                        <a href="category?<?= http_build_query(array_merge($queryParams, ['page' => $i])) ?>" class="w-10 h-10 border border-outline-variant hover:border-secondary flex items-center justify-center rounded-xl bg-white hover:bg-secondary/5 font-bold transition-all text-on-surface-variant hover:text-secondary"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <!-- Next button -->
                <?php if ($currentPage < $totalPages): ?>
                    <a href="category?<?= http_build_query(array_merge($queryParams, ['page' => $currentPage + 1])) ?>" class="w-10 h-10 border border-outline-variant hover:border-secondary flex items-center justify-center rounded-lg bg-white hover:bg-secondary/5 font-bold transition-colors text-secondary">&raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>


<?php include 'includes/footer.php'; ?>
