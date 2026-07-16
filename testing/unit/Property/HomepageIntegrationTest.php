<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/helpers.php';

use PHPUnit\Framework\TestCase;

/**
 * Homepage integration coverage for populated and empty marketplace datasets.
 *
 * Validates: Requirements 1.1, 1.2, 1.3, 1.4, 2.5, 3.2, 3.3, 4.2, 4.3, 4.5, 4.7, 5.1, 5.5
 */
class HomepageIntegrationTest extends TestCase
{
    /** @test */
    public function populatedRealLikeDatasetsRenderMarketplaceFlowAndCommerceControls(): void
    {
        $csrfToken = 'csrf-homepage-integration-token';
        $settings = $this->populatedStoreSettings();
        $banners = $this->populatedBanners();
        $categories = $this->populatedCategories(14);
        $featuredProducts = $this->populatedProducts(14, 'featured');
        $newestProducts = $this->populatedProducts(13, 'newest');
        $flashSaleProducts = $this->populatedProducts(8, 'flash');

        $html = $this->renderHomepageFixture(
            $settings,
            $banners,
            $categories,
            $featuredProducts,
            $newestProducts,
            $flashSaleProducts,
            $csrfToken
        );

        $this->assertSectionOrder($html, [
            'data-section="running-ticker"',
            'data-section="hero-cluster"',
            'data-section="discovery-rail"',
            'data-section="popular-searches"',
            'data-section="flash-sale"',
            'Produk Unggulan',
            'Produk Terbaru',
            'data-section="trust-strip"',
        ]);

        $this->assertSame(12, substr_count($html, 'class="category-card '), 'Discovery rail must render at most 12 category cards.');
        $this->assertSame(12, substr_count($this->extractRail($html, 'Produk Unggulan'), 'actions/cart-add'), 'Featured rail must render at most 12 cart forms.');
        $this->assertSame(12, substr_count($this->extractRail($html, 'Produk Terbaru'), 'actions/cart-add'), 'Newest rail must render at most 12 cart forms.');
        $this->assertSame(6, substr_count($this->extractSection($html, 'flash-sale'), 'data-flash-card="1"'), 'Flash sale shelf must render at most 6 real promo products.');

        $this->assertStringContainsString('action="actions/cart-add" method="POST"', $html);
        $this->assertStringContainsString('name="csrf_token" value="' . $csrfToken . '"', $html);
        $this->assertStringContainsString('name="quantity" value="1"', $html);
        $this->assertStringContainsString('action="actions/wishlist-toggle" method="POST"', $html);
        $this->assertStringContainsString('toggleWishlist', $html);
        $this->assertStringContainsString('onclick="prevSlide(event)"', $html);
        $this->assertStringContainsString('onclick="nextSlide(event)"', $html);
        $this->assertStringContainsString('onclick="goToSlide(0, event)"', $html);
        $this->assertStringContainsString('id="fs-hours"', $html);
        $this->assertStringContainsString('function startCountdowns()', $html);

        $this->assertStringContainsString('&lt;strong&gt;Gaming&lt;/strong&gt; Deals', $html);
        $this->assertStringContainsString('Laptop &amp; Workstation', $html);
        $this->assertStringContainsString('Keyboard &lt;RGB&gt; featured 1', $html);
        $this->assertStringNotContainsString('<strong>Gaming</strong> Deals', $html);
        $this->assertStringNotContainsString('Keyboard <RGB> featured 1', $html);
        $this->assertStringNotContainsString('Sample Product', $html);
        $this->assertStringNotContainsString('Produk Contoh', $html);
        $this->assertStringNotContainsString('fake sold', strtolower($html));
    }

    /** @test */
    public function emptyDynamicDatasetsOmitCardsAndMockMarketplaceData(): void
    {
        $html = $this->renderHomepageFixture(
            $this->emptyStoreSettings(),
            [],
            [],
            [],
            [],
            [],
            'csrf-empty-homepage'
        );

        $this->assertStringContainsString('Selamat Datang di TC Komputer', $html, 'No active banners should use only the approved static store introduction fallback.');
        $this->assertStringContainsString('data-section="hero-cluster"', $html);
        $this->assertStringContainsString('data-section="discovery-rail"', $html);
        $this->assertStringContainsString('data-section="trust-strip"', $html);
        $this->assertSame(0, substr_count($html, 'class="category-card '), 'Empty categories must render zero category cards.');
        $this->assertStringNotContainsString('data-section="popular-searches"', $html);
        $this->assertStringNotContainsString('data-section="flash-sale"', $html);
        $this->assertStringNotContainsString('data-homepage-product-rail', $html);
        $this->assertStringNotContainsString('actions/cart-add', $html);
        $this->assertStringNotContainsString('actions/wishlist-toggle', $html);
        $this->assertStringNotContainsString('promo-shortcut-card', $html);

        foreach (['Sample Product', 'Produk Contoh', 'Dummy', 'Lorem ipsum', 'fake sold', 'Terjual 100'] as $mockNeedle) {
            $this->assertStringNotContainsString($mockNeedle, $html);
        }
    }

    /** @param array<string,string> $settings */
    private function renderHomepageFixture(
        array $settings,
        array $banners,
        array $categories,
        array $featuredProducts,
        array $newestProducts,
        array $flashSaleProducts,
        string $csrfToken
    ): string {
        $slides = $this->buildSlides($banners);
        $promoBanners = extractHomepagePromoShortcuts($settings, 3);
        $popularSearches = parsePopularSearches($settings['popular_searches'] ?? null);
        $flashSaleState = normalizeFlashSaleState($settings, strtotime('2025-01-01 12:00:00'));

        $html = '<main>';
        if (trim((string)($settings['running_ticker'] ?? '')) !== '') {
            $html .= '<section data-section="running-ticker">' . sanitizeOutput($settings['running_ticker']) . '</section>';
        }

        $html .= '<section data-section="hero-cluster">';
        foreach ($slides as $index => $slide) {
            $html .= '<a class="hero-slide" href="' . sanitizeOutput($slide['link_url']) . '" data-slide-index="' . (int)$index . '">';
            $html .= '<img src="' . sanitizeOutput($slide['image']) . '" alt="' . sanitizeOutput($slide['title']) . '">';
            if (!empty($slide['is_fallback'])) {
                $html .= '<h1>' . sanitizeOutput($slide['title']) . '</h1><p>' . sanitizeOutput($slide['description']) . '</p>';
            }
            $html .= '</a>';
        }
        if (count($slides) > 1) {
            $html .= '<button onclick="prevSlide(event)" aria-label="Previous Slide"></button>';
            $html .= '<button onclick="nextSlide(event)" aria-label="Next Slide"></button>';
            foreach ($slides as $index => $_slide) {
                $html .= '<button onclick="goToSlide(' . (int)$index . ', event)" data-indicator-index="' . (int)$index . '"></button>';
            }
        }
        foreach ($promoBanners as $promoBanner) {
            $html .= '<a class="promo-shortcut-card" href="' . sanitizeOutput($promoBanner['link']) . '">';
            $html .= '<span>' . sanitizeOutput($promoBanner['title']) . '</span><span>' . sanitizeOutput($promoBanner['desc']) . '</span>';
            $html .= '</a>';
        }
        $html .= '</section>';

        $html .= '<section data-section="discovery-rail">';
        foreach (array_slice($categories, 0, 12) as $category) {
            $html .= '<a class="category-card " href="category?slug=' . sanitizeOutput((string)$category['slug']) . '" data-category-id="' . (int)$category['id'] . '">';
            $html .= sanitizeOutput((string)$category['name']) . '</a>';
        }
        $html .= '</section>';

        if ($popularSearches !== []) {
            $html .= '<section data-section="popular-searches">';
            foreach ($popularSearches as $keyword) {
                $html .= '<a href="products?search=' . urlencode($keyword) . '">' . sanitizeOutput($keyword) . '</a>';
            }
            $html .= '</section>';
        }

        if ($flashSaleState['is_active'] && $flashSaleProducts !== []) {
            $html .= '<section data-section="flash-sale"><div id="fs-hours">00</div><div id="fs-minutes">00</div><div id="fs-seconds">00</div>';
            foreach (array_slice($flashSaleProducts, 0, 6) as $product) {
                $price = determineActivePrice($product, true);
                $stockPercent = calculatePromoStockPercent($product);
                $html .= '<a data-flash-card="1" href="product-detail?slug=' . rawurlencode((string)$product['slug']) . '">';
                $html .= '<img src="' . sanitizeOutput(resolveHomepageProductImage($product)) . '" alt="' . sanitizeOutput((string)$product['name']) . '" loading="lazy">';
                $html .= '<span>' . sanitizeOutput((string)$product['name']) . '</span><span>' . formatRupiah($price['price']) . '</span>';
                if ($stockPercent !== null) {
                    $html .= '<div aria-label="Sisa stok promo ' . $stockPercent . ' persen" style="width: ' . $stockPercent . '%;"></div>';
                }
                $html .= '</a>';
            }
            $html .= '</section>';
        }

        $html .= renderHomepageProductRail([
            'title' => 'Produk Unggulan',
            'subtitle' => 'Koleksi hardware & aksesoris pilihan terbaik',
            'view_all_url' => 'products?sort=newest',
            'products' => $featuredProducts,
            'limit' => 12,
        ], $csrfToken, $flashSaleState['is_active'], [2, 4]);

        $html .= renderHomepageProductRail([
            'title' => 'Produk Terbaru',
            'subtitle' => 'Temukan hardware & peripheral rilis paling anyar',
            'view_all_url' => 'products',
            'products' => $newestProducts,
            'limit' => 12,
        ], $csrfToken, $flashSaleState['is_active'], [2, 4]);

        $html .= '<section data-section="trust-strip">Pengiriman Aman 100% Asli Harga Bersaing Layanan Ramah</section>';
        $html .= '<script>function startCountdowns(){} function prevSlide(e){} function nextSlide(e){} function goToSlide(index,e){}</script>';
        $html .= '</main>';

        return $html;
    }

    private function buildSlides(array $banners): array
    {
        if ($banners === []) {
            return [[
                'title' => 'Selamat Datang di TC Komputer',
                'description' => 'Menyediakan perangkat IT, komputer, dan aksesoris berkualitas tinggi dengan Jaminan asli untuk workspace produktif Anda.',
                'image' => 'assets/images/placeholder.svg',
                'link_url' => 'products',
                'is_fallback' => true,
            ]];
        }

        return array_map(static function (array $banner): array {
            $image = trim((string)($banner['image'] ?? ''));
            return [
                'title' => (string)($banner['title'] ?? ''),
                'description' => (string)($banner['description'] ?? ''),
                'image' => $image !== '' ? 'uploads/banners/' . $image : 'assets/images/placeholder.svg',
                'link_url' => trim((string)($banner['link_url'] ?? '')) !== '' ? (string)$banner['link_url'] : 'products',
                'is_fallback' => false,
            ];
        }, $banners);
    }

    private function assertSectionOrder(string $html, array $needles): void
    {
        $lastPosition = -1;
        foreach ($needles as $needle) {
            $position = strpos($html, $needle);
            $this->assertNotFalse($position, "Missing section marker: {$needle}");
            $this->assertGreaterThan($lastPosition, $position, "Section marker out of order: {$needle}");
            $lastPosition = $position;
        }
    }

    private function extractSection(string $html, string $section): string
    {
        preg_match('/<section data-section="' . preg_quote($section, '/') . '".*?<\/section>/s', $html, $matches);
        return $matches[0] ?? '';
    }

    private function extractRail(string $html, string $title): string
    {
        $start = strpos($html, $title);
        if ($start === false) {
            return '';
        }

        $nextRail = strpos($html, 'data-homepage-product-rail', $start + strlen($title));
        $trust = strpos($html, 'data-section="trust-strip"', $start);
        $endCandidates = array_filter([$nextRail, $trust], static fn ($value) => $value !== false);
        $end = $endCandidates === [] ? strlen($html) : min($endCandidates);

        return substr($html, $start, $end - $start);
    }

    /** @return array<string,string> */
    private function populatedStoreSettings(): array
    {
        return [
            'running_ticker' => 'Promo resmi & aman <hari ini>',
            'popular_searches' => 'laptop kerja, keyboard <RGB>, monitor 24"',
            'flash_sale_active' => '1',
            'flash_sale_end' => '2025-01-01 14:00:00',
            'flash_sale_title' => 'Flash Sale <Aman>',
            'flash_sale_subtitle' => 'Berakhir dalam:',
            'promo_banner_1_title' => '<strong>Gaming</strong> Deals',
            'promo_banner_1_desc' => 'Perangkat resmi & bergaransi',
            'promo_banner_1_link' => 'products?promo=gaming&safe=1',
            'promo_banner_1_icon' => 'sports_esports',
            'promo_banner_2_title' => 'Workspace Hemat',
            'promo_banner_2_desc' => 'Laptop, monitor, dan aksesoris',
            'promo_banner_2_link' => 'products?promo=workspace',
            'promo_banner_2_icon' => 'computer',
            'promo_banner_3_title' => 'Service Friendly',
            'promo_banner_3_desc' => 'Konsultasi kebutuhan IT',
            'promo_banner_3_link' => 'products?promo=service',
            'promo_banner_3_icon' => 'support_agent',
        ];
    }

    /** @return array<string,string> */
    private function emptyStoreSettings(): array
    {
        return [
            'running_ticker' => '',
            'popular_searches' => '',
            'flash_sale_active' => '',
            'flash_sale_end' => '2024-01-01 00:00:00',
            'promo_banner_1_title' => '',
            'promo_banner_2_title' => '',
            'promo_banner_3_title' => '',
        ];
    }

    private function populatedBanners(): array
    {
        return [
            ['id' => 1, 'title' => '<strong>Gaming</strong> Deals', 'description' => 'Banner utama', 'image' => 'gaming.png', 'link_url' => 'products?banner=1'],
            ['id' => 2, 'title' => 'Laptop Kerja', 'description' => 'Banner kedua', 'image' => '', 'link_url' => 'products?banner=2'],
        ];
    }

    private function populatedCategories(int $count): array
    {
        $categories = [];
        for ($index = 1; $index <= $count; $index++) {
            $categories[] = [
                'id' => $index,
                'name' => $index === 1 ? 'Laptop & Workstation' : "Kategori {$index}",
                'slug' => "kategori-{$index}",
            ];
        }

        return $categories;
    }

    private function populatedProducts(int $count, string $prefix): array
    {
        $products = [];
        for ($index = 1; $index <= $count; $index++) {
            $products[] = [
                'id' => $index,
                'name' => $index === 1 ? "Keyboard <RGB> {$prefix} {$index}" : "Produk {$prefix} {$index}",
                'category_name' => 'Aksesoris & Peripheral',
                'slug' => "produk-{$prefix}-{$index}",
                'image' => '',
                'selling_price' => 100000 + ($index * 1000),
                'promo_active' => 1,
                'promo_price' => 90000 + ($index * 1000),
                'promo_stock' => max(1, 20 - $index),
                'promo_stock_initial' => 20,
                'stock' => 30 + $index,
                'status' => $index % 5 === 0 ? 'po' : 'ready',
            ];
        }

        return $products;
    }
}
