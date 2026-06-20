<?php

require_once __DIR__ . '/../../../config/helpers.php';

use PHPUnit\Framework\TestCase;

/**
 * Property-Based Test for homepage promo stock progress bounds.
 *
 * **Validates: Requirements 3.4**
 *
 * Property 6: Promo Stock Percent Bounds
 * For all integer promo stock inputs, calculatePromoStockPercent() returns an
 * integer between 0 and 100 inclusive when progress is calculable, and uses
 * only promo_stock and promo_stock_initial as source values.
 */
class HomepagePromoStockPercentPropertyTest extends TestCase
{
    private const ITERATIONS = 1000;

    /**
     * @test
     * Property 6: Promo Stock Percent Bounds
     * **Validates: Requirements 3.4**
     */
    public function propertyPromoStockPercentIsBoundedAndSourceLimited(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $promoStock = random_int(-1000000, 1000000);
            $promoStockInitial = random_int(-1000000, 1000000);

            $baseProduct = [
                'promo_stock' => $promoStock,
                'promo_stock_initial' => $promoStockInitial,
            ];
            $productWithUnrelatedValues = $baseProduct + [
                'stock' => random_int(-1000000, 1000000),
                'promo_price' => random_int(-1000000, 1000000),
                'selling_price' => random_int(-1000000, 1000000),
                'promo_active' => (bool) random_int(0, 1),
            ];

            $result = calculatePromoStockPercent($baseProduct);
            $resultWithUnrelatedValues = calculatePromoStockPercent($productWithUnrelatedValues);

            $this->assertSame(
                $result,
                $resultWithUnrelatedValues,
                'Promo stock percent must use only promo_stock and promo_stock_initial.'
            );

            if ($promoStockInitial <= 0) {
                $this->assertNull($result, 'Progress is omitted when promo_stock_initial is not positive.');
                continue;
            }

            $expected = max(0, min(100, (int) round(($promoStock / $promoStockInitial) * 100)));

            $this->assertIsInt($result);
            $this->assertGreaterThanOrEqual(0, $result);
            $this->assertLessThanOrEqual(100, $result);
            $this->assertSame($expected, $result);
        }
    }
}
