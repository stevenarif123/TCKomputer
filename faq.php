<?php
/**
 * Public FAQ Page - Steven IT Shop
 * Displays categorized FAQ entries with interactive accordions and client-side search.
 */

require_once __DIR__ . '/includes/header.php';

// Load FAQ data grouped by category
$faqCategories = loadFaqData($pdo);
?>

<div class="max-w-max-width mx-auto px-4 md:px-margin-desktop py-3 md:py-lg animate-fade-in-up">
    <!-- Breadcrumbs -->
    <nav class="flex items-center gap-xs text-body-sm text-on-surface-variant mb-3 md:mb-lg">
        <a class="hover:text-secondary transition-colors" href="index">Beranda</a>
        <span class="material-symbols-outlined text-[16px] text-outline-variant">chevron_right</span>
        <span class="text-on-surface font-semibold">FAQ</span>
    </nav>

    <h1 class="text-xl md:text-2xl font-extrabold text-on-background">
        Pertanyaan yang Sering Diajukan (FAQ)
    </h1>

    <!-- Search Box -->
    <div class="relative mt-4 mb-6">
        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant">search</span>
        <input type="text" id="faq-search" placeholder="Cari pertanyaan..."
               class="w-full pl-10 pr-4 py-3 border border-outline-variant/60 rounded-xl text-body-sm bg-white outline-none focus:border-secondary" />
    </div>

    <!-- FAQ Categories & Accordion -->
    <?php foreach ($faqCategories as $cat): ?>
    <div class="faq-category-section mb-6" data-category="<?= sanitizeOutput($cat['name']) ?>">
        <div class="flex items-center gap-2 mb-3">
            <?php if (!empty($cat['icon'])): ?>
                <span class="material-symbols-outlined text-secondary"><?= sanitizeOutput($cat['icon']) ?></span>
            <?php endif; ?>
            <h2 class="text-lg font-extrabold text-on-background"><?= sanitizeOutput($cat['name']) ?></h2>
        </div>
        <div class="space-y-2">
            <?php foreach ($cat['faqs'] as $faq): ?>
            <div class="faq-item bg-white border border-outline-variant/40 rounded-xl overflow-hidden">
                <button onclick="toggleFaq(this)" class="w-full text-left px-5 py-4 flex items-center justify-between gap-3">
                    <span class="text-sm font-bold text-on-background"><?= sanitizeOutput($faq['question']) ?></span>
                    <span class="material-symbols-outlined text-on-surface-variant faq-chevron transition-transform">expand_more</span>
                </button>
                <div class="faq-answer hidden px-5 pb-4 text-sm text-on-surface-variant leading-relaxed">
                    <?= nl2br(sanitizeOutput($faq['answer'])) ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
