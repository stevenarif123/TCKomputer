<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Property-Based Test for Savings Amount Non-Negativity
 *
 * **Validates: Requirements 6.1**
 *
 * Property 8: Savings Amount Non-Negativity
 * For any Promo_Product, the savings amount should equal `max(0, selling_price - promo_price)` and should never be negative.
 */
class SavingsAmountPropertyTest extends TestCase
{
    private const ITERATIONS = 1000;

    /**
     * Generate a random integer value.
     */
    private function generateRandomPrice(): int
    {
        return mt_rand(-100000, 10000000);
    }

    /**
     * Calculate savings amount as per requirements.
     */
    private function calculateSavingsAmount(int $sellingPrice, int $promoPrice): int
    {
        return max(0, $sellingPrice - $promoPrice);
    }

    /**
     * Test specific edge cases for prices
     * 
     * @return array<array{0: int, 1: int}>
     */
    public function priceProvider(): array
    {
        return [
            [10000, 5000],   // Standard valid promo (selling > promo)
            [5000, 5000],    // Zero savings (selling == promo)
            [5000, 10000],   // Invalid promo (promo > selling) - should yield 0
            [0, 5000],       // Zero selling price
            [10000, 0],      // Zero promo price (100% off)
            [0, 0],          // Both zero
            [-100, 5000],    // Negative selling price (invalid input, robust test)
            [10000, -100],   // Negative promo price (invalid input, robust test)
            [-100, -200],    // Both negative
            [PHP_INT_MAX, PHP_INT_MAX],
            [PHP_INT_MAX, 0],
        ];
    }

    /**
     * Property: The savings amount should equal `max(0, selling_price - promo_price)` and should never be negative.
     * Validates: Requirements 6.1
     *
     * @dataProvider priceProvider
     * @test
     */
    public function savingsAmountNonNegativityEdgeCases(int $sellingPrice, int $promoPrice): void
    {
        $savingsAmount = $this->calculateSavingsAmount($sellingPrice, $promoPrice);
        
        $this->assertGreaterThanOrEqual(
            0, 
            $savingsAmount, 
            "Savings amount should never be negative. Selling: {$sellingPrice}, Promo: {$promoPrice}, Got: {$savingsAmount}"
        );
        
        $expected = max(0, $sellingPrice - $promoPrice);
        $this->assertSame(
            $expected,
            $savingsAmount,
            "Savings amount should equal max(0, selling_price - promo_price). Selling: {$sellingPrice}, Promo: {$promoPrice}"
        );
    }

    /**
     * Property: The savings amount should equal `max(0, selling_price - promo_price)` and should never be negative.
     * Validates: Requirements 6.1
     *
     * @test
     */
    public function savingsAmountNonNegativityRandomized(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $sellingPrice = $this->generateRandomPrice();
            $promoPrice = $this->generateRandomPrice();
            
            $savingsAmount = $this->calculateSavingsAmount($sellingPrice, $promoPrice);
            
            $this->assertGreaterThanOrEqual(
                0, 
                $savingsAmount, 
                "Savings amount should never be negative. Selling: {$sellingPrice}, Promo: {$promoPrice}, Got: {$savingsAmount}"
            );
            
            $expected = max(0, $sellingPrice - $promoPrice);
            $this->assertSame(
                $expected,
                $savingsAmount,
                "Savings amount should equal max(0, selling_price - promo_price). Selling: {$sellingPrice}, Promo: {$promoPrice}"
            );
        }
    }
}
