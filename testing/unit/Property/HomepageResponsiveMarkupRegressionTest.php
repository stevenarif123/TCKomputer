<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/helpers.php';

use PHPUnit\Framework\TestCase;

/**
 * Responsive markup regression tests for compact homepage layout contracts.
 *
 * **Validates: Requirements 2.1, 2.2, 2.3, 2.4, 4.1, 5.4**
 */
class HomepageResponsiveMarkupRegressionTest extends TestCase
{
    private string $homepageTemplate;

    protected function setUp(): void
    {
        $this->homepageTemplate = (string) file_get_contents(__DIR__ . '/../../../index.php');
    }

    /** @test */
    public function heroAndPromoShortcutMarkupKeepDesktopAndMobilePlacementContracts(): void
    {
        $this->assertMatchesRegularExpression(
            '/<div class="grid grid-cols-1 lg:grid-cols-\[minmax\(0,2fr\)_minmax\(280px,1fr\)\] gap-3 lg:gap-4 items-stretch">/',
            $this->homepageTemplate,
            'Hero cluster must stack on mobile and place carousel beside promo shortcuts on desktop.'
        );

        $this->assertMatchesRegularExpression(
            '/<div class="relative w-full max-h-\[(\d+)px\] md:max-h-\[(\d+)px\] overflow-hidden[^"]*" style="aspect-ratio:\s*1200\s*\/\s*380;">/',
            $this->homepageTemplate,
            'Hero carousel must declare mobile and desktop max-height bounds with the selected aspect-ratio wrapper.'
        );

        preg_match('/max-h-\[(\d+)px\] md:max-h-\[(\d+)px\]/', $this->homepageTemplate, $heightMatches);
        $this->assertLessThanOrEqual(220, (int) $heightMatches[1], 'Mobile hero max-height must not exceed 220px.');
        $this->assertLessThanOrEqual(360, (int) $heightMatches[2], 'Desktop hero max-height must not exceed 360px.');

        $this->assertStringContainsString(
            'grid grid-cols-2 md:grid-cols-3 lg:grid-cols-1 gap-2 lg:gap-3 lg:max-h-[360px]',
            $this->homepageTemplate,
            'Promo shortcuts must sit immediately below the carousel on mobile and stack beside it on desktop.'
        );
    }

    /** @test */
    public function discoveryRailProductDensityAndLazyLoadingContractsDoNotRegress(): void
    {
        $this->assertMatchesRegularExpression(
            '/<div class="flex items-center gap-3 overflow-x-auto hide-scrollbar px-3 py-3 md:px-4 md:py-3" aria-label="Jelajahi kategori">/',
            $this->homepageTemplate,
            'Discovery rail must remain a compact horizontal scroll container.'
        );

        $railHtml = renderHomepageProductRail([
            'title' => 'Produk Regression',
            'subtitle' => 'Density and lazy loading contract',
            'view_all_url' => 'products',
            'products' => $this->products(6),
            'limit' => 12,
        ], 'csrf-regression-token', false, []);

        $this->assertStringContainsString(
            'grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4',
            $railHtml,
            'Product rail must keep compact 2-column mobile, 3-column tablet, and 6-column desktop density.'
        );

        preg_match_all('/<img\b[^>]*>/i', $railHtml, $imageMatches);
        $this->assertCount(6, $imageMatches[0], 'Regression fixture should render one image per product card.');

        foreach ($imageMatches[0] as $index => $imageTag) {
            if ($index < 4) {
                $this->assertStringNotContainsString('loading="lazy"', $imageTag, "Product image {$index} should stay eagerly loaded within the first four cards.");
            } else {
                $this->assertStringContainsString('loading="lazy"', $imageTag, "Product image {$index} should be lazy-loaded below the first four cards.");
            }
        }
    }

    /** @return array<int,array<string,mixed>> */
    private function products(int $count): array
    {
        $products = [];

        for ($i = 1; $i <= $count; $i++) {
            $products[] = [
                'id' => $i,
                'name' => "Regression Product {$i}",
                'category_name' => 'Regression Category',
                'slug' => "regression-product-{$i}",
                'image' => '',
                'selling_price' => 100000 + $i,
                'promo_active' => 0,
                'promo_price' => 0,
                'stock' => 10 + $i,
                'status' => 'ready',
            ];
        }

        return $products;
    }
}
