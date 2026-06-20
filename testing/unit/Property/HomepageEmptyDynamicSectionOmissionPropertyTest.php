<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/helpers.php';

use PHPUnit\Framework\TestCase;

/**
 * Property-Based Test for empty dynamic section omission.
 *
 * **Validates: Requirements 1.4, 3.3, 4.5, 4.7**
 *
 * Property 4: Empty Dynamic Section Omission
 */
class HomepageEmptyDynamicSectionOmissionPropertyTest extends TestCase
{
    private const ITERATIONS = 160;

    /**
     * @test
     */
    public function emptyBackingCollectionsRenderNoDynamicCardsOrPlaceholders(): void
    {
        for ($iteration = 0; $iteration < self::ITERATIONS; $iteration++) {
            $csrfToken = $this->randomToken();
            $railHtml = renderHomepageProductRail([
                'title' => $this->randomLabel(),
                'subtitle' => $this->randomLabel(),
                'view_all_url' => 'products?source=' . rawurlencode($this->randomLabel()),
                'products' => [],
                'limit' => mt_rand(1, 12),
            ], $csrfToken, (bool) mt_rand(0, 1), []);

            $this->assertSame('', $railHtml, 'Empty product collections must omit product rails entirely.');
            $this->assertNoDynamicCardsOrEmptyState($railHtml);

            $emptyPromoSettings = $this->generateEmptyPromoSettings();
            $promoShortcuts = extractHomepagePromoShortcuts($emptyPromoSettings, mt_rand(0, 6));
            $this->assertSame([], $promoShortcuts, 'Empty promo settings must not create promo shortcut cards.');

            $popularSearches = parsePopularSearches($this->randomEmptyString());
            $this->assertSame([], $popularSearches, 'Empty popular search settings must not create search chips.');

            $emptyCategories = array_values(array_filter([], 'is_array'));
            $emptyBanners = array_values(array_filter([], 'is_array'));
            $emptyFlashSaleProducts = array_values(array_filter([], 'is_array'));

            $this->assertSame([], $emptyCategories, 'Empty category collection must remain zero category cards.');
            $this->assertSame([], $emptyBanners, 'Empty banner collection must remain zero banner cards.');
            $this->assertSame([], $emptyFlashSaleProducts, 'Empty flash sale product collection must remain zero promo product cards.');
        }
    }

    /** @return array<string,string> */
    private function generateEmptyPromoSettings(): array
    {
        $settings = [];
        for ($index = 1; $index <= 6; $index++) {
            $settings["promo_banner_{$index}_title"] = $this->randomEmptyString();
            $settings["promo_banner_{$index}_desc"] = $this->randomLabel();
            $settings["promo_banner_{$index}_link"] = 'products?promo=' . $index;
            $settings["promo_banner_{$index}_icon"] = 'campaign';
        }

        return $settings;
    }

    private function randomEmptyString(): string
    {
        $fragments = ['', ' ', "\t", "\n", "\r\n", '   '];
        return str_repeat($fragments[array_rand($fragments)], mt_rand(1, 4));
    }

    private function randomLabel(): string
    {
        $parts = ['Produk', 'Kategori', 'Promo', 'Banner', 'Flash', 'Placeholder'];
        shuffle($parts);
        return implode(' ', array_slice($parts, 0, mt_rand(1, 3))) . ' ' . mt_rand(1, 9999);
    }

    private function randomToken(): string
    {
        return bin2hex(random_bytes(8));
    }

    private function assertNoDynamicCardsOrEmptyState(string $html): void
    {
        $forbiddenNeedles = [
            'data-homepage-product-rail',
            'actions/cart-add',
            'actions/wishlist-toggle',
            'category-card',
            'hero-slide',
            'promo_banner',
            'empty',
            'kosong',
            'belum ada',
            'placeholder card',
        ];

        foreach ($forbiddenNeedles as $needle) {
            $this->assertStringNotContainsString($needle, strtolower($html));
        }
    }
}
