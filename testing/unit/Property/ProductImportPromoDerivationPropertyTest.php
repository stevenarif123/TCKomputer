<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/import.php';

use PHPUnit\Framework\TestCase;

/**
 * Property 3: Promo Fields Correctly Derived
 *
 * **Validates: Requirements 2.5, 2.6**
 */
class ProductImportPromoDerivationPropertyTest extends TestCase
{
    public function testPromoFieldsAreDerivedFromPromoPriceAndStock(): void
    {
        $categoryMap = [1 => 'Accessories'];

        for ($i = 0; $i < 100; $i++) {
            $stock = random_int(0, 500);
            $promoPrice = random_int(1, 10000000);
            $mapped = validateAndMapRow($this->row($stock, (string) $promoPrice), $categoryMap)['mapped'];

            $this->assertSame($promoPrice, $mapped['promo_price']);
            $this->assertSame(1, $mapped['promo_active']);
            $this->assertSame($stock, $mapped['promo_stock']);
            $this->assertSame($stock, $mapped['promo_stock_initial']);
        }
    }

    public function testMissingOrZeroPromoClearsPromoFields(): void
    {
        $categoryMap = [1 => 'Accessories'];

        for ($i = 0; $i < 100; $i++) {
            $stock = random_int(0, 500);
            foreach (['', '0'] as $promoPrice) {
                $mapped = validateAndMapRow($this->row($stock, $promoPrice), $categoryMap)['mapped'];

                $this->assertNull($mapped['promo_price']);
                $this->assertSame(0, $mapped['promo_active']);
                $this->assertSame(0, $mapped['promo_stock']);
                $this->assertSame(0, $mapped['promo_stock_initial']);
            }
        }
    }

    private function row(int $stock, string $promoPrice): array
    {
        return [
            'status' => 'completed',
            'nama' => 'Produk Promo',
            'kategori_id' => '1',
            'harga_jual' => '100000',
            'stock' => (string) $stock,
            'promo_price' => $promoPrice,
        ];
    }
}
