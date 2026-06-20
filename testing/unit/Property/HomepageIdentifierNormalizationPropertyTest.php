<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/helpers.php';

use PHPUnit\Framework\TestCase;

/**
 * Property-Based Test for homepage identifier output normalization.
 *
 * **Validates: Requirements 5.2**
 *
 * Property 10: Identifier Normalization
 * Product and category identifiers with numeric and string-like inputs are
 * rendered through integer-cast values in forms, URLs/attributes, and JavaScript calls.
 */
class HomepageIdentifierNormalizationPropertyTest extends TestCase
{
    private const ITERATIONS = 300;

    /**
     * @test
     */
    public function productCardIdentifiersAreIntegerCastInFormsAndJavascriptCalls(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $rawId = $this->generateIdentifierLikeValue();
            $expectedId = (string) (int) $rawId;
            $product = $this->generatePurchasableProduct($rawId, $i);

            $html = renderHomepageProductCard($product, 'csrf-token-' . $i, false, [$rawId]);

            $this->assertMatchesRegularExpression(
                '/toggleWishlist\(this\.querySelector\(\'button\'\),\s*' . preg_quote($expectedId, '/') . '\);/',
                $html,
                'Wishlist JavaScript call must receive the integer-cast product identifier for source id: ' . var_export($rawId, true)
            );

            $this->assertSame(
                [$expectedId],
                $this->extractHiddenInputValues($html, 'product_id'),
                'Wishlist and cart product_id inputs must use only the integer-cast product identifier.'
            );

            if ((string) $rawId !== $expectedId) {
                $this->assertStringNotContainsString(
                    'value="' . sanitizeOutput((string) $rawId) . '"',
                    $html,
                    'Rendered form identifiers must not expose the raw source identifier when it differs from the integer cast.'
                );
            }
        }
    }

    /**
     * @test
     */
    public function categoryDiscoveryIdentifierContractUsesIntegerCastValues(): void
    {
        $indexSource = (string) file_get_contents(__DIR__ . '/../../../index.php');

        $this->assertStringContainsString(
            '$categoryId = (int)($category[\'id\'] ?? 0);',
            $indexSource,
            'Homepage category rendering must normalize category ids with an integer cast before output.'
        );
        $this->assertStringContainsString(
            'data-category-id="<?= $categoryId ?>"',
            $indexSource,
            'Homepage category links must output the pre-normalized category identifier.'
        );

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $rawId = $this->generateIdentifierLikeValue();
            $expectedId = (string) (int) $rawId;
            $category = [
                'id' => $rawId,
                'slug' => 'category-' . $i,
                'name' => 'Category ' . $i,
            ];

            $categoryId = (int)($category['id'] ?? 0);
            $html = '<a href="category?slug=' . sanitizeOutput((string) $category['slug']) . '" data-category-id="' . $categoryId . '">'
                . sanitizeOutput((string) $category['name'])
                . '</a>';

            $this->assertStringContainsString(
                'data-category-id="' . $expectedId . '"',
                $html,
                'Rendered category identifier attributes must use the integer-cast category id for source id: ' . var_export($rawId, true)
            );
            if ((string) $rawId !== $expectedId) {
                $this->assertStringNotContainsString(
                    'data-category-id="' . sanitizeOutput((string) $rawId) . '"',
                    $html,
                    'Rendered category identifier attributes must not expose raw string-like category ids when they differ from the integer cast.'
                );
            }
        }
    }

    /** @return mixed */
    private function generateIdentifierLikeValue()
    {
        $kind = mt_rand(0, 9);

        if ($kind <= 2) {
            return mt_rand(-1000, 100000);
        }

        if ($kind <= 4) {
            return (string) mt_rand(-1000, 100000);
        }

        if ($kind === 5) {
            return str_repeat(' ', mt_rand(0, 3)) . mt_rand(0, 9999) . 'abc' . mt_rand(0, 999) . str_repeat(' ', mt_rand(0, 3));
        }

        if ($kind === 6) {
            return 'id-' . mt_rand(0, 9999);
        }

        if ($kind === 7) {
            return (string) (mt_rand(0, 9999) / 10);
        }

        if ($kind === 8) {
            return '';
        }

        return null;
    }

    /** @return array<string,mixed> */
    private function generatePurchasableProduct($rawId, int $iteration): array
    {
        return [
            'id' => $rawId,
            'name' => 'Generated Product ' . $iteration,
            'slug' => 'generated-product-' . $iteration,
            'category_name' => 'Generated Category',
            'image' => '',
            'selling_price' => mt_rand(1, 10000000),
            'promo_active' => 0,
            'promo_price' => 0,
            'stock' => mt_rand(1, 50),
            'status' => 'ready',
        ];
    }

    /** @return array<int,string> */
    private function extractHiddenInputValues(string $html, string $name): array
    {
        $values = [];
        $pattern = '/<input\s+type="hidden"\s+name="' . preg_quote($name, '/') . '"\s+value="([^"]*)">/';
        preg_match_all($pattern, $html, $matches);

        foreach ($matches[1] as $value) {
            $values[] = $value;
        }

        return array_values(array_unique($values));
    }
}
