<?php
/**
 * Categories Listing Page - Steven IT Shop
 * Displays a premium grid of all active categories in the shop.
 */

require_once __DIR__ . '/includes/header.php';

// Fetch all active categories
$stmt = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order ASC");
$categories = $stmt->fetchAll();
?>

<div class="max-w-max-width mx-auto px-4 md:px-margin-desktop py-3 md:py-lg animate-fade-in-up">
    <!-- Breadcrumbs -->
    <nav class="flex items-center gap-xs text-body-sm text-on-surface-variant mb-3 md:mb-lg">
        <a class="hover:text-secondary transition-colors" href="index">Beranda</a>
        <span class="material-symbols-outlined text-[16px] text-outline-variant">chevron_right</span>
        <span class="text-on-surface font-semibold">Kategori Pilihan</span>
    </nav>

    <h1 class="text-headline-lg font-black text-on-background tracking-tight mb-xs">Kategori Pilihan</h1>
    <p class="text-body-sm text-on-surface-variant mb-md">Temukan perangkat komputer dan aksesoris berkualitas berdasarkan kategori spesifik.</p>

    <?php if (empty($categories)): ?>
        <div class="bg-white border border-outline-variant/60 rounded-xl p-6 md:p-12 text-center max-w-md mx-auto space-y-md">
            <span class="material-symbols-outlined text-6xl text-on-surface-variant/40">folder_open</span>
            <div>
                <h2 class="text-headline-md font-extrabold text-on-background">Kategori Tidak Tersedia</h2>
                <p class="text-body-sm text-on-surface-variant mt-1">Saat ini belum ada kategori produk yang aktif.</p>
            </div>
            <a href="products" class="px-xl py-3 bg-secondary text-white font-bold text-label-md rounded-lg hover:bg-secondary-container transition-colors inline-block">
                Lihat Semua Produk
            </a>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-sm">
            <?php foreach ($categories as $cat): ?>
            <?php
            $imageVal = $cat['image'] ?? '';
            $isUrl = (stripos($imageVal, 'http://') === 0 || stripos($imageVal, 'https://') === 0 || stripos($imageVal, '/') === 0);
            $isFile = (!empty($imageVal) && !$isUrl && preg_match('/\.(jpg|jpeg|png|webp|gif|svg)$/i', $imageVal) && file_exists('uploads/categories/' . $imageVal));
            
            // Slug-based icon mapping
            $catSlug = $cat['slug'] ?? '';
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
            <a href="category?slug=<?= sanitizeOutput($cat['slug']) ?>" class="group bg-white rounded-lg border border-outline-variant/60 tech-card flex flex-col overflow-hidden">
                <div class="relative aspect-[4/3] overflow-hidden bg-surface-container-low p-3 md:p-6 flex items-center justify-center">
                    <?php if ($isFile): ?>
                        <img alt="<?= sanitizeOutput($cat['name']) ?>" class="max-w-[80%] max-h-[80%] object-contain " src="uploads/categories/<?= sanitizeOutput($imageVal) ?>"/>
                    <?php elseif ($isUrl): ?>
                        <img alt="<?= sanitizeOutput($cat['name']) ?>" class="max-w-[80%] max-h-[80%] object-contain " src="<?= sanitizeOutput($imageVal) ?>"/>
                    <?php elseif (!empty($imageVal) && !$isUrl && !$isFile): // Material Symbol ?>
                        <span class="material-symbols-outlined text-5xl text-secondary "><?= sanitizeOutput($imageVal) ?></span>
                    <?php else: // Slug fallback ?>
                        <span class="material-symbols-outlined text-5xl text-outline-variant/60 "><?= $catIcon ?></span>
                    <?php endif; ?>
                </div>
                <div class="p-4 flex flex-col flex-grow bg-white">
                    <h3 class="text-body-md font-extrabold text-on-background group-hover:text-secondary transition-colors"><?= sanitizeOutput($cat['name']) ?></h3>
                    <?php if (!empty($cat['description'])): ?>
                        <p class="text-body-sm text-on-surface-variant line-clamp-2 mt-1 leading-relaxed"><?= sanitizeOutput($cat['description']) ?></p>
                    <?php else: ?>
                        <p class="text-body-sm text-on-surface-variant italic mt-1">Lihat berbagai hardware <?= sanitizeOutput($cat['name']) ?>.</p>
                    <?php endif; ?>
                    <span class="text-secondary font-bold text-label-sm mt-md inline-flex items-center gap-1 group-hover:underline">
                        Lihat Produk
                        <span class="material-symbols-outlined text-sm group-hover:translate-x-1 transition-transform">arrow_forward</span>
                    </span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
