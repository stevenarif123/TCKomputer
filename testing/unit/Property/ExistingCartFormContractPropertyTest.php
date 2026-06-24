<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/helpers.php';

use PHPUnit\Framework\TestCase;

/**
 * Property 16: Existing Cart Form Contract
 * Validates: Requirements 1.5, 13.1
 */
class ExistingCartFormContractPropertyTest extends TestCase
{
    /**
     * Verify that the add-to-cart action contains csrf_token, product_id, and quantity targeting actions/cart-add.
     */
    public function testExistingCartFormContract()
    {
        // Path to product-detail.php
        $productDetailPath = __DIR__ . '/../../../product-detail.php';
        $productDetailContent = file_get_contents($productDetailPath);

        // Path to products.php
        $productsPath = __DIR__ . '/../../../products.php';
        $productsContent = file_get_contents($productsPath);

        // Verify product-detail.php
        $this->assertMatchesRegularExpression('/action="actions\/cart-add(\.php)?"/', $productDetailContent);
        $this->assertStringContainsString('name="csrf_token"', $productDetailContent);
        $this->assertStringContainsString('name="product_id"', $productDetailContent);
        $this->assertStringContainsString('name="quantity"', $productDetailContent);
        
        // Verify the javascript form submission works correctly on that form in product-detail.php
        $this->assertStringContainsString('document.getElementById(\'add-to-cart-form\')', $productDetailContent);
        $this->assertStringContainsString('form.submit()', $productDetailContent);

        // Verify products.php
        $this->assertMatchesRegularExpression('/action="actions\/cart-add(\.php)?"/', $productsContent);
        $this->assertStringContainsString('name="csrf_token"', $productsContent);
        $this->assertStringContainsString('name="product_id"', $productsContent);
        $this->assertStringContainsString('name="quantity"', $productsContent);
    }
}
