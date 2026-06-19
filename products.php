<?php
/**
 * Products Page - All Products Listing
 * Paginated product grid with search, category filter, status filter, and sort options.
 */

include 'includes/header.php';

// Get filter parameters from URL
$search = trim($_GET['search'] ?? '');
$categoryId = (int)($_GET['category'] ?? 0);
$status = $_GET['status'] ?? '';
$sort = $_GET['sort'] ?? 'newest';
$page = max(1, (int)($_GET['page'] ?? 1));

// Build query
$perPage = 24;
$where = ['p.is_active = 1'];
$params = [];

// Search filter
if ($search !== '') {
    $where[] = '(p.name LIKE ? OR p.brand LIKE ? OR p.description LIKE ?)';
    $searchTerm = '%' . $search . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

// Category filter
if ($categoryId > 0) {
    $where[] = 'p.category_id = ?';
    $params[] = $categoryId;
}

// Status filter
if ($status !== '' && in_array($status, ['ready', 'po'])) {
    $where[] = 'p.status = ?';
    $params[] = $status;
}

$quickFilter = validateQuickFilter($_GET['filter'] ?? '');
$quickFilterResult = applyQuickFilterToWhereClause($quickFilter, $where, $params);
$where = $quickFilterResult['where'];
$params = $quickFilterResult['params'];

$whereClause = implode(' AND ', $where);

// Sort options
$sortOptions = [
    'newest' => 'p.created_at DESC',
    'cheapest' => 'p.selling_price ASC',
    'expensive' => 'p.selling_price DESC',
];
$orderBy = $sortOptions[$sort] ?? 'p.created_at DESC';

if ($quickFilter === 'new') {
    $orderBy = 'p.created_at DESC';
}

// Count total results
$countQuery = "SELECT COUNT(*) FROM products p WHERE $whereClause";
$stmtCount = $pdo->prepare($countQuery);
$stmtCount->execute($params);
$total = (int)$stmtCount->fetchColumn();

// Calculate pagination
$totalPages = (int)ceil($total / $perPage);
if ($totalPages > 0 && $page > $totalPages) {
    $page = $totalPages;
}
$page = max(1, min($page, max(1, $totalPages)));
$offset = ($page - 1) * $perPage;

// Fetch products
$query = "SELECT p.*, c.name AS category_name 
          FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id 
          WHERE $whereClause 
          ORDER BY $orderBy 
          LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Fetch categories for filter dropdown
$stmtCat = $pdo->query("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY sort_order ASC");
$categories = $stmtCat->fetchAll();

// CSRF Token for add to cart
$csrfToken = generateCSRFToken();
?>

<div class="max-w-max-width mx-auto px-4 md:px-margin-desktop py-3 md:py-lg animate-fade-in-up">
    <!-- Breadcrumbs -->
    <nav class="flex items-center gap-xs text-body-sm text-on-surface-variant mb-3 md:mb-lg">
        <a class="hover:text-secondary transition-colors" href="index">Beranda</a>
        <span class="material-symbols-outlined text-[16px] text-outline-variant">chevron_right</span>
        <span class="text-on-surface font-semibold">Semua Produk</span>
    </nav>

    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-2 md:gap-md mb-3 md:mb-lg">
        <div>
            <h1 class="text-headline-lg font-black text-on-background tracking-tight">Katalog Produk</h1>
            <p class="text-body-sm text-on-surface-variant">Menampilkan <?= count($products) ?> dari <?= $total ?> produk hardware berkualitas</p>
        </div>
    </div>

    <!-- Filter Form -->
    <form method="GET" action="products" class="bg-white p-3 md:p-5 rounded-xl border border-outline-variant/40 mb-4 md:mb-lg">
        <div class="flex flex-col md:grid md:grid-cols-12 gap-sm items-stretch md:items-end">
            <!-- Search field (Always visible) -->
            <div class="space-y-1 flex-grow md:col-span-4">
                <label for="filter-search" class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider hidden md:block">Cari Nama / Brand</label>
                <div class="flex gap-2">
                    <div class="relative flex-grow">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-[18px]">search</span>
                        <input type="text" id="filter-search" name="search" placeholder="Contoh: Asus, Keyboard..." value="<?= sanitizeOutput($search) ?>" class="w-full pl-9 pr-4 py-2.5 border border-outline-variant/85 rounded-lg text-body-sm bg-surface-container-lowest outline-none"/>
                    </div>
                    <!-- Toggle filters button for mobile only -->
                    <button type="button" onclick="toggleMobileFilters()" class="px-3.5 py-2 border border-outline-variant/85 rounded-lg text-body-sm font-bold text-on-surface flex items-center gap-1 bg-surface-container-lowest md:hidden">
                        <span class="material-symbols-outlined text-[18px]">filter_list</span> Filter
                    </button>
                </div>
            </div>

            <!-- Collapsible Filters Area (Mobile: hidden by default, Desktop: always visible) -->
            <div id="filters-form-fields" class="hidden md:contents">
                <div class="space-y-1 md:col-span-2">
                    <label for="filter-category" class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider block">Kategori</label>
                    <select id="filter-category" name="category" class="w-full px-4 py-2.5 border border-outline-variant/85 rounded-lg text-body-sm bg-surface-container-lowest outline-none">
                        <option value="">Semua Kategori</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int)$cat['id'] ?>" <?= $categoryId === (int)$cat['id'] ? 'selected' : '' ?>>
                                <?= sanitizeOutput($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="space-y-1 md:col-span-2">
                    <label for="filter-status" class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider block">Status Stok</label>
                    <select id="filter-status" name="status" class="w-full px-4 py-2.5 border border-outline-variant/85 rounded-lg text-body-sm bg-surface-container-lowest outline-none">
                        <option value="">Semua Status</option>
                        <option value="ready" <?= $status === 'ready' ? 'selected' : '' ?>>Ready</option>
                        <option value="po" <?= $status === 'po' ? 'selected' : '' ?>>Pre-Order</option>
                    </select>
                </div>

                <div class="space-y-1 md:col-span-2">
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

    <!-- Quick Filter Chips -->
    <?php
    $chipBaseParams = [];
    if ($search !== '') $chipBaseParams['search'] = $search;
    if ($categoryId > 0) $chipBaseParams['category'] = $categoryId;
    if ($status !== '') $chipBaseParams['status'] = $status;
    if ($sort !== 'newest') $chipBaseParams['sort'] = $sort;
    ?>
    <div class="flex gap-2 overflow-x-auto hide-scrollbar mb-4 md:mb-lg pb-1">
        <a href="<?= sanitizeOutput('products?' . http_build_query($chipBaseParams)) ?>" class="whitespace-nowrap px-4 py-1.5 rounded-full text-xs font-bold border <?= $quickFilter === '' ? 'bg-secondary text-white border-secondary' : 'bg-white text-on-surface-variant border-outline-variant/60 hover:bg-surface-container hover:border-outline-variant transition-colors' ?>">Semua</a>
        <a href="<?= sanitizeOutput('products?' . http_build_query(array_merge($chipBaseParams, ['filter' => 'ready']))) ?>" class="whitespace-nowrap px-4 py-1.5 rounded-full text-xs font-bold border <?= $quickFilter === 'ready' ? 'bg-secondary text-white border-secondary' : 'bg-white text-on-surface-variant border-outline-variant/60 hover:bg-surface-container hover:border-outline-variant transition-colors' ?>">Ready Stock</a>
        <?php if ($isGlobalFlashSaleActive): ?>
            <a href="<?= sanitizeOutput('products?' . http_build_query(array_merge($chipBaseParams, ['filter' => 'promo']))) ?>" class="whitespace-nowrap px-4 py-1.5 rounded-full text-xs font-bold border <?= $quickFilter === 'promo' ? 'bg-secondary text-white border-secondary' : 'bg-white text-on-surface-variant border-outline-variant/60 hover:bg-surface-container hover:border-outline-variant transition-colors' ?>">Promo</a>
        <?php endif; ?>
        <a href="<?= sanitizeOutput('products?' . http_build_query(array_merge($chipBaseParams, ['filter' => 'new']))) ?>" class="whitespace-nowrap px-4 py-1.5 rounded-full text-xs font-bold border <?= $quickFilter === 'new' ? 'bg-secondary text-white border-secondary' : 'bg-white text-on-surface-variant border-outline-variant/60 hover:bg-surface-container hover:border-outline-variant transition-colors' ?>">Terbaru</a>
        
        <?php 
        $catLimit = 6;
        $catCount = 0;
        foreach ($categories as $cat): 
            if ($catCount >= $catLimit) break;
            $catCount++;
            
            $catParams = $chipBaseParams;
            $catParams['category'] = $cat['id'];
            if ($quickFilter !== '') {
                $catParams['filter'] = $quickFilter;
            }
        ?>
            <a href="<?= sanitizeOutput('products?' . http_build_query($catParams)) ?>" class="whitespace-nowrap px-4 py-1.5 rounded-full text-xs font-bold border <?= $categoryId === (int)$cat['id'] ? 'bg-secondary text-white border-secondary' : 'bg-white text-on-surface-variant border-outline-variant/60 hover:bg-surface-container hover:border-outline-variant transition-colors' ?>">
                <?= sanitizeOutput($cat['name']) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($products)): ?>
        <!-- Empty catalog state -->
        <div class="bg-white border border-outline-variant/60 rounded-xl p-6 md:p-12 text-center max-w-md mx-auto space-y-md">
            <span class="material-symbols-outlined text-6xl text-on-surface-variant/40">shopping_bag</span>
            <div>
                <h2 class="text-headline-md font-extrabold text-on-background">Produk Tidak Ditemukan</h2>
                <p class="text-body-sm text-on-surface-variant mt-1">Kami tidak menemukan produk yang cocok dengan pencarian Anda.</p>
            </div>
            <a href="products" class="px-xl py-3 bg-secondary text-white font-bold text-label-md rounded-xl hover:bg-secondary-container transition-all inline-block shadow-md">
                Reset Pencarian
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
                    <img alt="<?= sanitizeOutput($product['name']) ?>" loading="lazy" class="w-full h-full object-contain group-hover:scale-105 transition-transform duration-500" src="<?= $imgSrc ?>"/>
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
                    <div class="flex items-center gap-1.5 mb-1 select-none">
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
                $queryParams = [];
                if ($search !== '') $queryParams['search'] = $search;
                if ($categoryId > 0) $queryParams['category'] = $categoryId;
                if ($status !== '') $queryParams['status'] = $status;
                if ($sort !== 'newest') $queryParams['sort'] = $sort;
                if ($quickFilter !== '') $queryParams['filter'] = $quickFilter;
                
                $paginationRange = generatePaginationRange($page, $totalPages, 1);
                ?>

                <!-- Prev button -->
                <?php if ($page > 1): ?>
                    <a href="<?= sanitizeOutput('products?' . http_build_query(array_merge($queryParams, ['page' => $page - 1]))) ?>" class="w-10 h-10 border border-outline-variant hover:border-secondary flex items-center justify-center rounded-lg bg-white hover:bg-secondary/5 font-bold transition-colors text-secondary">&laquo;</a>
                <?php endif; ?>

                <!-- Page numbers -->
                <?php foreach ($paginationRange as $i): ?>
                    <?php if ($i === '...'): ?>
                        <span class="w-10 h-10 flex items-center justify-center font-bold text-on-surface-variant">...</span>
                    <?php elseif ($i === $page): ?>
                        <span class="w-10 h-10 bg-secondary text-white flex items-center justify-center rounded-lg font-bold"><?= $i ?></span>
                    <?php else: ?>
                        <a href="<?= sanitizeOutput('products?' . http_build_query(array_merge($queryParams, ['page' => $i]))) ?>" class="w-10 h-10 border border-outline-variant hover:border-secondary flex items-center justify-center rounded-xl bg-white hover:bg-secondary/5 font-bold transition-all text-on-surface-variant hover:text-secondary"><?= $i ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>

                <!-- Next button -->
                <?php if ($page < $totalPages): ?>
                    <a href="<?= sanitizeOutput('products?' . http_build_query(array_merge($queryParams, ['page' => $page + 1]))) ?>" class="w-10 h-10 border border-outline-variant hover:border-secondary flex items-center justify-center rounded-lg bg-white hover:bg-secondary/5 font-bold transition-colors text-secondary">&raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>


<?php include 'includes/footer.php'; ?>
