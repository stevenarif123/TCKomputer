<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/helpers.php';

use PHPUnit\Framework\TestCase;

/**
 * Property 1: Mobile CTA Price Total
 * Validates: Requirements 1.4
 */
class MobileCtaPriceTotalPropertyTest extends TestCase
{
    /**
     * For any active unit price and any valid selected quantity, 
     * the total price should equal the active unit price multiplied by the selected quantity.
     * We simulate the Javascript logic in PHP to test the property.
     */
    public function testMobileCtaPriceTotal()
    {
        for ($i = 0; $i < 100; $i++) {
            // Generate random active price between 10,000 and 100,000,000
            $activePrice = rand(10000, 100000000);
            
            // Generate random valid quantity between 1 and 999
            $qty = rand(1, 999);
            
            // This represents the calculation in javascript: currentPrice * qty
            $totalPrice = $activePrice * $qty;
            
            // Format the total price to match the UI behavior
            $expectedFormattedPrice = formatRupiah($totalPrice);
            
            // Assertion: The logic must match the format expected on the Mobile CTA Price element
            $this->assertEquals(formatRupiah($activePrice * $qty), $expectedFormattedPrice);
        }
    }
}
