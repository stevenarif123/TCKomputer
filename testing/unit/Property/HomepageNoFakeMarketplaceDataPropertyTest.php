<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/helpers.php';

use PHPUnit\Framework\TestCase;

/**
 * Property-Based Test for Homepage Marketplace Source Integrity.
 *
 * **Validates: Requirements 1.1, 1.2, 1.3, 1.5, 3.5, 3.7**
 *
 * Property 1: No Fake Marketplace Data
 */
class HomepageNoFakeMarketplaceDataPropertyTest extends TestCase
{
    private const ITERATIONS = 120;

    /** @var array<int,string> */
    private const STATIC_TRUST_COPY = [
        'Pengiriman aman',
        'Garansi resmi',
        'Harga kompetitif',
        'Layanan ramah',
    ];

    /** @return array<string,string> */
    private function generateStoreSettings(int $iteration): array
    {
        $settings = [
            'popular_searches' => $this->randomCsvTokens($iteration),
            'flash_sale_active' => (string) mt_rand(0, 1),
            'flash_sale_end' => date('Y-m-d H:i:s', time() + mt_rand(-7200, 7200)),
        ];

        for ($slot = 1; $slot <= 3; $slot++) {
            $configured = mt_rand(0, 1) === 1;
            $settings["promo_banner_{$slot}_title"] = $configured ? "Promo Source {$iteration}-{$slot}" : str_repeat(' ', mt_rand(0, 3));
            $settings["promo_banner_{$slot}_desc"] = $configured ? "Desc Source {$iteration}-{$slot}" : '';
            $settings["promo_banner_{$slot}_link"] = $configured ? "products?promo={$iteration}-{$slot}" : '';
            $settings["promo_banner_{$slot}_icon"] = $configured ? "icon_{$iteration}_{$slot}" : '';
        }

        return $settings;
    }

    /** @return array<int,string> */
    private function generateSourceTokens(int $iteration): array
    {
        $count = mt_rand(0, 8);
        $tokens = [];

        for ($i = 0; $i < $count; $i++) {
            $tokens[] = "Search Source {$iteration}-{$i}";
        }

        return $tokens;
    }

    private function randomCsvTokens(int $iteration): string
    {
        $tokens = $this->generateSourceTokens($iteration);
        $parts = [];

        foreach ($tokens as $token) {
            $parts[] = str_repeat(' ', mt_rand(0, 2)) . $token . str_repeat(' ', mt_rand(0, 2));
            if (mt_rand(0, 3) === 0) {
                $parts[] = str_repeat(' ', mt_rand(0, 3));
            }
        }

        return implode(',', $parts);
    }

    /** @return array<int,array<string,mixed>> */
    private function generateProducts(int $iteration): array
    {
        $count = mt_rand(0, 10);
        $products = [];

        for ($i = 0; $i < $count; $i++) {
            $products[] = [
                'id' => $iteration * 100 + $i,
                'name' => "Product Source {$iteration}-{$i}",
                'category_name' => "Category Source {$iteration}-{$i}",
                'image' => $i % 2 === 0 ? "uploads/product-{$iteration}-{$i}.jpg" : '',
                'selling_price' => mt_rand(0, 50000000),
                'promo_active' => mt_rand(0, 1),
                'promo_price' => mt_rand(-1000, 40000000),
                'promo_stock' => mt_rand(-5, 80),
                'promo_stock_initial' => mt_rand(-5, 100),
                'stock' => mt_rand(-5, 200),
            ];
        }

        return $products;
    }

    /** @return array<int,array<string,mixed>> */
    private function generateCategories(int $iteration): array
    {
        $count = mt_rand(0, 14);
        $categories = [];

        for ($i = 0; $i < $count; $i++) {
            $categories[] = [
                'id' => $iteration * 50 + $i,
                'name' => "Category Source {$iteration}-{$i}",
                'slug' => "category-source-{$iteration}-{$i}",
                'image' => $i % 3 === 0 ? "uploads/category-{$iteration}-{$i}.jpg" : '',
            ];
        }

        return $categories;
    }

    /** @return array<int,array<string,mixed>> */
    private function generateBanners(int $iteration): array
    {
        $count = mt_rand(0, 6);
        $banners = [];

        for ($i = 0; $i < $count; $i++) {
            $keyword = ['Promo', 'Diskon', 'Sale', 'Info'][array_rand(['Promo', 'Diskon', 'Sale', 'Info'])];
            $banners[] = [
                'title' => "{$keyword} Banner Source {$iteration}-{$i}",
                'description' => "Banner Desc Source {$iteration}-{$i}",
                'image_url' => "uploads/banner-{$iteration}-{$i}.jpg",
                'link_url' => "products?banner={$iteration}-{$i}",
            ];
        }

        return $banners;
    }

    /** @return array<int,string> */
    private function collectSourceStrings(array $settings, array $products, array $categories, array $banners): array
    {
        $sources = self::STATIC_TRUST_COPY;

        foreach ($settings as $value) {
            $value = trim((string) $value);
            if ($value !== '' && !preg_match('/^\d+$/', $value)) {
                $sources[] = $value;
            }
        }

        foreach (array_merge($products, $categories, $banners) as $row) {
            foreach ($row as $value) {
                if (is_string($value) && trim($value) !== '') {
                    $sources[] = trim($value);
                }
            }
        }

        foreach (parsePopularSearches($settings['popular_searches'] ?? null) as $token) {
            $sources[] = $token;
        }

        return array_values(array_unique($sources));
    }

    private function assertSourceBackedString(string $value, array $sources, string $context): void
    {
        $this->assertContains(
            $value,
            $sources,
            "{$context}: helper output '{$value}' must come from generated source data or approved static trust copy."
        );
    }

    /**
     * @test
     */
    public function helperOutputsContainOnlySourceBackedMarketplaceValues(): void
    {
        for ($iteration = 0; $iteration < self::ITERATIONS; $iteration++) {
            $settings = $this->generateStoreSettings($iteration);
            $products = $this->generateProducts($iteration);
            $categories = $this->generateCategories($iteration);
            $banners = $this->generateBanners($iteration);
            $sources = $this->collectSourceStrings($settings, $products, $categories, $banners);

            foreach (extractHomepagePromoShortcuts($settings) as $shortcut) {
                $this->assertSourceBackedString($shortcut['title'], $sources, "Iteration {$iteration} promo title");
                if ($shortcut['desc'] !== '') {
                    $this->assertSourceBackedString($shortcut['desc'], $sources, "Iteration {$iteration} promo description");
                }
                if ($shortcut['link'] !== '') {
                    $this->assertSourceBackedString($shortcut['link'], $sources, "Iteration {$iteration} promo link");
                }
                if ($shortcut['icon'] !== '') {
                    $this->assertSourceBackedString($shortcut['icon'], $sources, "Iteration {$iteration} promo icon");
                }
                $this->assertContains($shortcut['index'], [1, 2, 3], "Iteration {$iteration}: promo index must identify a configured source slot.");
            }

            foreach (parsePopularSearches($settings['popular_searches'] ?? null) as $token) {
                $this->assertSourceBackedString($token, $sources, "Iteration {$iteration} popular search");
            }

            foreach ($banners as $banner) {
                $isPromo = isHomepagePromoShortcutBanner($banner);
                $sourceText = strtolower(trim((string) $banner['title'] . ' ' . (string) $banner['description']));
                $this->assertSame(
                    str_contains($sourceText, 'promo') || str_contains($sourceText, 'diskon') || str_contains($sourceText, 'sale'),
                    $isPromo,
                    "Iteration {$iteration}: promo detection must depend only on banner source wording."
                );
            }

            foreach ($products as $product) {
                $activePrice = determineActivePrice($product, mt_rand(0, 1) === 1);
                $this->assertContains($activePrice['price'], [(int) $product['selling_price'], (int) $product['promo_price']], "Iteration {$iteration}: active price must be source-backed.");
                $this->assertSame(max(0, (int) $product['selling_price']), $activePrice['original_price'], "Iteration {$iteration}: original price must be the source selling price normalized to non-negative.");

                $stockPercent = calculatePromoStockPercent($product);
                if ($stockPercent !== null) {
                    $this->assertGreaterThanOrEqual(0, $stockPercent, "Iteration {$iteration}: promo stock percent must not be fabricated below bounds.");
                    $this->assertLessThanOrEqual(100, $stockPercent, "Iteration {$iteration}: promo stock percent must not be fabricated above bounds.");
                }
            }
        }
    }
}
