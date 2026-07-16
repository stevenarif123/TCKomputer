<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/helpers.php';
require_once __DIR__ . '/../../../config/import.php';

use PHPUnit\Framework\TestCase;

/**
 * Property-Based Test for product import invalid-row errors.
 *
 * **Validates: Requirements 2.7**
 */
class ProductImportInvalidRowErrorsTest extends TestCase
{
    /**
     * Property 6: Invalid Rows Have Errors
     *
     * **Validates: Requirements 2.7**
     *
     * @test
     */
    public function everyValidationFailureReturnsSpecificErrorMessage(): void
    {
        $categoryMap = [1 => 'Keyboard'];
        $names = ['', str_repeat('A', 256), 'Valid Keyboard'];
        $categoryIds = ['', '0', '999', '1'];
        $prices = ['', '0', '-10', '150000'];

        foreach ($names as $name) {
            foreach ($categoryIds as $categoryId) {
                foreach ($prices as $price) {
                    if ($name === 'Valid Keyboard' && $categoryId === '1' && $price === '150000') {
                        continue;
                    }

                    $result = validateAndMapRow([
                        'status' => 'completed',
                        'nama' => $name,
                        'kategori_id' => $categoryId,
                        'harga_jual' => $price,
                    ], $categoryMap);

                    $this->assertFalse($result['valid'], "name={$name}; category={$categoryId}; price={$price}");
                    $this->assertFalse($result['skipped']);
                    $this->assertNotEmpty($result['errors']);
                    foreach ($result['errors'] as $error) {
                        $this->assertIsString($error);
                        $this->assertMatchesRegularExpression('/[A-Za-zÀ-ÿ]{3,}/', $error);
                    }
                }
            }
        }
    }
}
