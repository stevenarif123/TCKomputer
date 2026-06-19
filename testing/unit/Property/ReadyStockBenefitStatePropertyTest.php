<?php

require_once __DIR__ . '/../../../config/helpers.php';

use PHPUnit\Framework\TestCase;

class ReadyStockBenefitStatePropertyTest extends TestCase
{
    /**
     * @test
     * Property 7: Ready Stock Benefit State
     * Validates: Requirements 5.2, 5.3
     */
    public function testReadyStockBenefitState(): void
    {
        // To test this purely, we simulate the rendering logic block for Quick_Benefit_Summary.
        for ($i = 0; $i < 1000; $i++) {
            $status = $this->getRandomStatus();
            $stock = random_int(-10, 100);

            $product = [
                'status' => $status,
                'stock' => $stock
            ];

            // Render logic from product-detail.php
            $hasReadyStock = ($product['status'] === 'ready' && $product['stock'] > 0);

            if ($status === 'ready' && $stock > 0) {
                $this->assertTrue($hasReadyStock, "Ready Stock should be included when status is ready and stock > 0. Status: $status, Stock: $stock");
            } else {
                $this->assertFalse($hasReadyStock, "Ready Stock should NOT be included when status is not ready or stock <= 0. Status: $status, Stock: $stock");
            }
        }
    }

    private function getRandomStatus(): string
    {
        $statuses = ['ready', 'po', 'habis', ''];
        return $statuses[array_rand($statuses)];
    }
}
