<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/helpers.php';

use PHPUnit\Framework\TestCase;

/**
 * Property-Based Test for homepage output escaping.
 *
 * **Validates: Requirements 5.3**
 *
 * Property 8: Output Escaping
 */
class HomepageOutputEscapingPropertyTest extends TestCase
{
    private const ITERATIONS = 180;

    /**
     * @test
     */
    public function renderedCardAndRailEscapeUserControlledTextAndAttributes(): void
    {
        for ($iteration = 0; $iteration < self::ITERATIONS; $iteration++) {
            $product = $this->generateProduct($iteration);
            $csrfToken = $this->randomSensitiveString();
            $railTitle = $this->randomSensitiveString();
            $railSubtitle = $this->randomSensitiveString();
            $viewAllUrl = 'products?filter=' . $this->randomSensitiveString();

            $cardHtml = renderHomepageProductCard($product, $csrfToken, true, [], false);
            $railHtml = renderHomepageProductRail([
                'title' => $railTitle,
                'subtitle' => $railSubtitle,
                'view_all_url' => $viewAllUrl,
                'products' => [$product],
                'limit' => 12,
            ], $csrfToken, true, []);

            foreach ([$cardHtml, $railHtml] as $html) {
                $this->assertEscapedPresent((string) $product['name'], $html, 'product name');
                $this->assertEscapedPresent((string) $product['category_name'], $html, 'category name');
                $this->assertEscapedPresent($csrfToken, $html, 'csrf token');

                $this->assertStringNotContainsString((string) $product['name'], $html, 'Raw product name must not appear unescaped.');
                $this->assertStringNotContainsString((string) $product['category_name'], $html, 'Raw category name must not appear unescaped.');
                $this->assertStringNotContainsString($csrfToken, $html, 'Raw CSRF token must not appear unescaped.');
                $this->assertStringNotContainsString('<script', strtolower($html), 'Rendered output must not contain executable script tags from source data.');
            }

            $this->assertEscapedPresent($railTitle, $railHtml, 'rail title');
            $this->assertEscapedPresent($railSubtitle, $railHtml, 'rail subtitle');
            $this->assertEscapedPresent($viewAllUrl, $railHtml, 'rail view-all URL');

            $this->assertStringNotContainsString($railTitle, $railHtml, 'Raw rail title must not appear unescaped.');
            $this->assertStringNotContainsString($railSubtitle, $railHtml, 'Raw rail subtitle must not appear unescaped.');
            $this->assertStringNotContainsString($viewAllUrl, $railHtml, 'Raw rail view-all URL must not appear unescaped.');
        }
    }

    /** @return array<string,mixed> */
    private function generateProduct(int $iteration): array
    {
        return [
            'id' => $iteration + 1,
            'name' => $this->randomSensitiveString(),
            'category_name' => $this->randomSensitiveString(),
            'slug' => 'safe-slug-' . $iteration,
            'image' => '',
            'selling_price' => mt_rand(1, 50000000),
            'promo_active' => (string) mt_rand(0, 1),
            'promo_price' => mt_rand(1, 40000000),
            'promo_stock' => mt_rand(0, 50),
            'promo_stock_initial' => mt_rand(1, 100),
            'stock' => mt_rand(1, 200),
            'status' => mt_rand(0, 1) === 1 ? 'ready' : 'po',
        ];
    }

    private function randomSensitiveString(): string
    {
        $fragments = [
            '<script>alert("x")</script>',
            '"quoted"',
            "'single'",
            '& ampersand',
            '<b>bold</b>',
            '> greater',
            'onmouseover="alert(1)"',
            '`tick`',
            'Produk Aman',
        ];

        shuffle($fragments);
        return implode(' ', array_slice($fragments, 0, mt_rand(3, count($fragments))));
    }

    private function assertEscapedPresent(string $source, string $html, string $context): void
    {
        $this->assertStringContainsString(
            sanitizeOutput($source),
            $html,
            ucfirst($context) . ' must be present only as sanitized output.'
        );
    }
}
