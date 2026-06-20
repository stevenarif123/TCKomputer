<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/helpers.php';

use PHPUnit\Framework\TestCase;

/**
 * Property-Based Test for homepage cart form contract preservation.
 *
 * **Validates: Requirements 5.1**
 *
 * Property 5: Cart Contract Preservation
 */
class HomepageCartContractPreservationPropertyTest extends TestCase
{
    private const ITERATIONS = 200;

    /** @return array<string,mixed> */
    private function generatePurchasableProduct(int $iteration): array
    {
        $id = random_int(1, 1000000);
        $status = random_int(0, 1) === 1 ? 'ready' : 'po';

        return [
            'id' => $id,
            'name' => "Generated Product {$iteration}-{$id}",
            'category_name' => "Category {$iteration}",
            'slug' => "generated-product-{$iteration}-{$id}",
            'image' => random_int(0, 1) === 1 ? "uploads/product-{$iteration}.jpg" : '',
            'selling_price' => random_int(1, 50000000),
            'promo_active' => random_int(0, 1),
            'promo_price' => random_int(1, 40000000),
            'promo_stock' => random_int(1, 100),
            'stock' => random_int(1, 500),
            'status' => $status,
        ];
    }

    /** @return array<string,string> */
    private function extractCartInputs(string $formHtml): array
    {
        preg_match_all('/<input\b[^>]*>/i', $formHtml, $inputMatches);

        $inputs = [];
        foreach ($inputMatches[0] as $inputHtml) {
            if (!preg_match('/\bname="([^"]+)"/i', $inputHtml, $nameMatch)) {
                continue;
            }

            $value = '';
            if (preg_match('/\bvalue="([^"]*)"/i', $inputHtml, $valueMatch)) {
                $value = html_entity_decode($valueMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }

            $inputs[$nameMatch[1]] = $value;
        }

        return $inputs;
    }

    /**
     * @test
     * Property 5: Cart Contract Preservation
     * **Validates: Requirements 5.1**
     */
    public function propertyPurchasableHomepageCardsPreserveCartContract(): void
    {
        for ($iteration = 0; $iteration < self::ITERATIONS; $iteration++) {
            $product = $this->generatePurchasableProduct($iteration);
            $csrfToken = bin2hex(random_bytes(16));
            $html = renderHomepageProductCard($product, $csrfToken, random_int(0, 1) === 1);

            $this->assertMatchesRegularExpression(
                '/<form\b(?=[^>]*\baction="actions\/cart-add")(?=[^>]*\bmethod="POST")[^>]*>.*?<\/form>/is',
                $html,
                "Iteration {$iteration}: rendered purchasable card must contain the existing add-to-cart POST form."
            );

            preg_match(
                '/<form\b(?=[^>]*\baction="actions\/cart-add")(?=[^>]*\bmethod="POST")[^>]*>.*?<\/form>/is',
                $html,
                $formMatch
            );

            $inputs = $this->extractCartInputs($formMatch[0]);

            $this->assertArrayHasKey('csrf_token', $inputs, "Iteration {$iteration}: cart form must include csrf_token.");
            $this->assertNotSame('', $inputs['csrf_token'], "Iteration {$iteration}: csrf_token must be non-empty.");
            $this->assertSame($csrfToken, $inputs['csrf_token'], "Iteration {$iteration}: csrf_token must preserve the provided token.");

            $this->assertArrayHasKey('product_id', $inputs, "Iteration {$iteration}: cart form must include product_id.");
            $this->assertMatchesRegularExpression('/^-?\d+$/', $inputs['product_id'], "Iteration {$iteration}: product_id must render as an integer string.");
            $this->assertSame((string) (int) $product['id'], $inputs['product_id'], "Iteration {$iteration}: product_id must equal the integer product identifier.");

            $this->assertArrayHasKey('quantity', $inputs, "Iteration {$iteration}: cart form must include quantity.");
            $this->assertSame('1', $inputs['quantity'], "Iteration {$iteration}: quantity must default to 1.");
        }
    }
}
