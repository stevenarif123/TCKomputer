<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/helpers.php';

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for homepage marketplace helper functions.
 *
 * Validates: Requirements 3.4, 3.6, 3.7, 5.6
 */
class HomepageHelperUnitTest extends TestCase
{
    /** @test */
    public function parsePopularSearchesReturnsEmptyListForBlankValues(): void
    {
        $this->assertSame([], parsePopularSearches(null));
        $this->assertSame([], parsePopularSearches(''));
        $this->assertSame([], parsePopularSearches(" \t\n "));
        $this->assertSame([], parsePopularSearches(' , ,   ,'));
    }

    /** @test */
    public function parsePopularSearchesPreservesTrimmedSourceOrder(): void
    {
        $this->assertSame(
            ['laptop gaming', 'ssd nvme', 'keyboard mechanical', 'monitor'],
            parsePopularSearches(' laptop gaming,ssd nvme ,  keyboard mechanical ,, monitor ')
        );
    }

    /** @test */
    public function extractHomepagePromoShortcutsReturnsOnlyConfiguredSlots(): void
    {
        $settings = [
            'promo_banner_1_title' => '  Diskon Monitor  ',
            'promo_banner_1_desc' => '  Hemat minggu ini  ',
            'promo_banner_1_link' => '  /products?promo=monitor  ',
            'promo_banner_1_icon' => '  desktop_windows  ',
            'promo_banner_2_title' => '   ',
            'promo_banner_2_desc' => 'Should be ignored',
            'promo_banner_2_link' => '/ignored',
            'promo_banner_2_icon' => 'hide_source',
            'promo_banner_3_title' => 'Paket Upgrade',
            'promo_banner_3_desc' => '',
            'promo_banner_3_link' => '/products?category=upgrade',
            'promo_banner_3_icon' => '',
        ];

        $this->assertSame(
            [
                [
                    'title' => 'Diskon Monitor',
                    'desc' => 'Hemat minggu ini',
                    'link' => '/products?promo=monitor',
                    'icon' => 'desktop_windows',
                    'index' => 1,
                ],
                [
                    'title' => 'Paket Upgrade',
                    'desc' => '',
                    'link' => '/products?category=upgrade',
                    'icon' => '',
                    'index' => 3,
                ],
            ],
            extractHomepagePromoShortcuts($settings)
        );
    }

    /** @test */
    public function extractHomepagePromoShortcutsReturnsEmptyListWhenNoTitlesAreConfigured(): void
    {
        $this->assertSame([], extractHomepagePromoShortcuts([]));
        $this->assertSame([], extractHomepagePromoShortcuts([
            'promo_banner_1_title' => '',
            'promo_banner_2_title' => '  ',
            'promo_banner_3_desc' => 'Description without title',
        ]));
        $this->assertSame([], extractHomepagePromoShortcuts([
            'promo_banner_1_title' => 'Configured but outside limit',
        ], 0));
    }

    /** @test */
    public function extractHomepagePromoShortcutsDeduplicatesConfiguredPromos(): void
    {
        $settings = [
            'promo_banner_1_title' => 'Diskon Monitor',
            'promo_banner_1_desc' => 'Hemat minggu ini',
            'promo_banner_1_link' => '/products?promo=monitor',
            'promo_banner_1_icon' => 'desktop_windows',
            'promo_banner_2_title' => '  Diskon Monitor  ',
            'promo_banner_2_desc' => ' Hemat minggu ini ',
            'promo_banner_2_link' => ' /products?promo=monitor ',
            'promo_banner_2_icon' => 'campaign',
            'promo_banner_3_title' => 'Paket Upgrade',
            'promo_banner_3_desc' => 'Komponen pilihan',
            'promo_banner_3_link' => '/products?category=upgrade',
            'promo_banner_3_icon' => 'memory',
        ];

        $shortcuts = extractHomepagePromoShortcuts($settings);

        $this->assertCount(2, $shortcuts);
        $this->assertSame('Diskon Monitor', $shortcuts[0]['title']);
        $this->assertSame('Paket Upgrade', $shortcuts[1]['title']);
    }

    /** @test */
    public function determineActivePriceUsesPromoOnlyWhenFlashSaleAndProductPromoAreValid(): void
    {
        $product = [
            'selling_price' => 1500000,
            'promo_active' => 1,
            'promo_price' => 1250000,
            'promo_stock' => 4,
        ];

        $this->assertSame(
            ['price' => 1250000, 'original_price' => 1500000, 'is_promo' => true],
            determineActivePrice($product, true)
        );
    }

    /** @test */
    public function determineActivePriceFallsBackForInactiveOrInvalidPromoValues(): void
    {
        $cases = [
            'flash sale inactive' => [[
                'selling_price' => 1500000,
                'promo_active' => 1,
                'promo_price' => 1250000,
                'promo_stock' => 4,
            ], false, 1500000],
            'product promo inactive' => [[
                'selling_price' => 1500000,
                'promo_active' => 0,
                'promo_price' => 1250000,
                'promo_stock' => 4,
            ], true, 1500000],
            'zero promo price' => [[
                'selling_price' => 1500000,
                'promo_active' => 1,
                'promo_price' => 0,
                'promo_stock' => 4,
            ], true, 1500000],
            'negative promo price' => [[
                'selling_price' => 1500000,
                'promo_active' => 1,
                'promo_price' => -100,
                'promo_stock' => 4,
            ], true, 1500000],
            'zero promo stock' => [[
                'selling_price' => 1500000,
                'promo_active' => 1,
                'promo_price' => 1250000,
                'promo_stock' => 0,
            ], true, 1500000],
            'negative selling price is clamped' => [[
                'selling_price' => -500,
                'promo_active' => 0,
                'promo_price' => 1250000,
                'promo_stock' => 4,
            ], true, 0],
        ];

        foreach ($cases as $label => [$product, $flashSaleActive, $expectedPrice]) {
            $this->assertSame(
                ['price' => $expectedPrice, 'original_price' => $expectedPrice, 'is_promo' => false],
                determineActivePrice($product, $flashSaleActive),
                $label
            );
        }
    }

    /** @test */
    public function calculatePromoStockPercentReturnsBoundedPercentForValidStockValues(): void
    {
        $this->assertSame(50, calculatePromoStockPercent([
            'promo_stock' => 5,
            'promo_stock_initial' => 10,
        ]));
        $this->assertSame(100, calculatePromoStockPercent([
            'promo_stock' => 15,
            'promo_stock_initial' => 10,
        ]));
        $this->assertSame(0, calculatePromoStockPercent([
            'promo_stock' => -3,
            'promo_stock_initial' => 10,
        ]));
    }

    /** @test */
    public function calculatePromoStockPercentOmitsProgressForMissingZeroOrNegativeInitialStock(): void
    {
        $this->assertNull(calculatePromoStockPercent([
            'promo_stock_initial' => 10,
        ]));
        $this->assertNull(calculatePromoStockPercent([
            'promo_stock' => 0,
            'promo_stock_initial' => 0,
        ]));
        $this->assertNull(calculatePromoStockPercent([
            'promo_stock' => 5,
            'promo_stock_initial' => -10,
        ]));
    }
}
