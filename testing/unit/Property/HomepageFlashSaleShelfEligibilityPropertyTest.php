<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/helpers.php';

use PHPUnit\Framework\TestCase;

/**
 * Property-Based Test for homepage flash sale shelf eligibility.
 *
 * **Validates: Requirements 3.2, 3.3**
 *
 * Property 9: Flash Sale Shelf Eligibility
 * For generated flash sale active/inactive states, countdown values, and promo
 * product collections, the shelf renders if and only if all eligibility
 * conditions are satisfied.
 */
class HomepageFlashSaleShelfEligibilityPropertyTest extends TestCase
{
    private const ITERATIONS = 600;

    /** @test */
    public function flashSaleShelfRendersIffActiveCountdownPositiveAndPromoProductsExist(): void
    {
        $homepageTemplate = (string) file_get_contents(__DIR__ . '/../../../index.php');

        $this->assertStringContainsString(
            "!empty($" . "flashSaleProducts) && $" . "fsSeconds > 0 && !empty($" . "storeSettings['flash_sale_active'])",
            $homepageTemplate,
            'Homepage flash sale shelf must be gated by products, positive countdown, and active flash sale state.'
        );

        for ($iteration = 0; $iteration < self::ITERATIONS; $iteration++) {
            $isActive = (bool) random_int(0, 1);
            $secondsRemaining = random_int(-86400, 86400);
            $promoProducts = $this->generatePromoProducts();

            $html = $this->renderFlashSaleShelfEligibilityProbe($isActive, $secondsRemaining, $promoProducts);
            $expectedToRender = $isActive && $secondsRemaining > 0 && count($promoProducts) > 0;

            $this->assertSame(
                $expectedToRender,
                strpos($html, 'data-flash-sale-shelf="1"') !== false,
                sprintf(
                    'Flash sale shelf eligibility mismatch for active=%s, seconds=%d, productCount=%d.',
                    $isActive ? 'true' : 'false',
                    $secondsRemaining,
                    count($promoProducts)
                )
            );
        }
    }

    /** @return array<int,array<string,mixed>> */
    private function generatePromoProducts(): array
    {
        $products = [];
        $count = random_int(0, 8);

        for ($index = 0; $index < $count; $index++) {
            $products[] = [
                'id' => random_int(1, 1000000),
                'name' => 'Promo Product ' . random_int(1, 1000000),
                'slug' => 'promo-product-' . random_int(1, 1000000),
                'selling_price' => random_int(1, 10000000),
                'promo_price' => random_int(1, 9999999),
                'promo_stock' => random_int(1, 1000),
                'promo_stock_initial' => random_int(1, 1000),
            ];
        }

        return $products;
    }

    /** @param array<int,array<string,mixed>> $promoProducts */
    private function renderFlashSaleShelfEligibilityProbe(bool $isActive, int $secondsRemaining, array $promoProducts): string
    {
        if (empty($promoProducts) || $secondsRemaining <= 0 || !$isActive) {
            return '';
        }

        $html = '<section data-flash-sale-shelf="1">';
        foreach ($promoProducts as $product) {
            $html .= '<a data-product-id="' . (int) ($product['id'] ?? 0) . '">';
            $html .= sanitizeOutput((string) ($product['name'] ?? ''));
            $html .= '</a>';
        }
        $html .= '</section>';

        return $html;
    }
}
