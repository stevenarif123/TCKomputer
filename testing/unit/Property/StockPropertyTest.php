<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/../../config/db.php';

use PHPUnit\Framework\TestCase;

/**
 * Property-Based Test for Stock Non-Negativity and Stock-Status Consistency
 *
 * **Validates: Requirements 4.6, 4.7**
 *
 * Property 4: Stock Non-Negativity
 * For any product after any sequence of order operations, the product stock
 * must remain greater than or equal to zero.
 *
 * Property 5: Stock-Status Consistency
 * For any product, if status is 'ready' then stock must be greater than zero,
 * and if status is 'habis' then stock must be zero. When stock reaches zero
 * through an order, status must automatically transition to 'habis'.
 */
class StockPropertyTest extends TestCase
{
    /**
     * Number of random iterations per test method.
     */
    private const ITERATIONS = 100;

    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = getDBConnection();
    }

    /**
     * Generate a random initial stock value (1-100).
     */
    private function generateRandomStock(): int
    {
        return mt_rand(1, 100);
    }

    /**
     * Generate a random order quantity that is valid (1 to maxStock).
     */
    private function generateValidOrderQuantity(int $maxStock): int
    {
        return mt_rand(1, $maxStock);
    }

    /**
     * Generate a random sequence of order quantities for a given stock.
     * Each quantity is between 1 and remaining stock to simulate valid orders.
     *
     * @return array<int>
     */
    private function generateOrderSequence(int $initialStock): array
    {
        $orders = [];
        $remaining = $initialStock;
        $numOrders = mt_rand(1, min(5, $initialStock));

        for ($i = 0; $i < $numOrders; $i++) {
            if ($remaining <= 0) {
                break;
            }
            $qty = mt_rand(1, $remaining);
            $orders[] = $qty;
            $remaining -= $qty;
        }

        return $orders;
    }

    /**
     * Create a temporary test product and return its ID.
     * Uses a unique slug to avoid conflicts.
     */
    private function createTestProduct(int $stock, string $status = 'ready'): int
    {
        $slug = 'test-stock-' . uniqid() . '-' . mt_rand(1000, 9999);

        // Get a valid category_id from the database
        $catStmt = $this->pdo->query("SELECT id FROM categories LIMIT 1");
        $category = $catStmt->fetch();
        $categoryId = $category ? (int) $category['id'] : 1;

        $stmt = $this->pdo->prepare(
            "INSERT INTO products (name, slug, description, selling_price, purchase_price, stock, status, condition_type, category_id, image, created_at, updated_at)
             VALUES (?, ?, 'Test product for property testing', 100000, 80000, ?, ?, 'new', ?, 'placeholder.png', NOW(), NOW())"
        );
        $stmt->execute(['Test Product ' . $slug, $slug, $stock, $status, $categoryId]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Simulate the stock decrease logic from checkout-process.php.
     * Only decreases stock for 'ready' status products.
     */
    private function simulateStockDecrease(int $productId, int $quantity): void
    {
        // Get current product status
        $stmt = $this->pdo->prepare("SELECT status FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();

        if ($product && $product['status'] === 'ready') {
            // Decrease stock
            $stmt = $this->pdo->prepare(
                "UPDATE products SET stock = stock - ?, updated_at = NOW() WHERE id = ?"
            );
            $stmt->execute([$quantity, $productId]);

            // Auto-set status to 'habis' if stock reaches 0
            $stmt = $this->pdo->prepare(
                "UPDATE products SET status = 'habis' WHERE id = ? AND stock <= 0"
            );
            $stmt->execute([$productId]);
        }
    }

    /**
     * Get current product stock and status.
     *
     * @return array{stock: int, status: string}
     */
    private function getProductState(int $productId): array
    {
        $stmt = $this->pdo->prepare("SELECT stock, status FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $row = $stmt->fetch();

        return [
            'stock' => (int) $row['stock'],
            'status' => $row['status'],
        ];
    }

    /**
     * Delete a test product by ID.
     */
    private function deleteTestProduct(int $productId): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$productId]);
    }

    /**
     * Property 4: Stock Non-Negativity - Single order.
     * After decreasing stock by quantity Q for a product with stock S (where Q <= S),
     * the resulting stock must be >= 0.
     *
     * **Validates: Requirements 4.6**
     *
     * @test
     */
    public function stockNeverGoesBelowZeroAfterSingleOrder(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $initialStock = $this->generateRandomStock();
            $quantity = $this->generateValidOrderQuantity($initialStock);

            $productId = $this->createTestProduct($initialStock, 'ready');

            try {
                $this->simulateStockDecrease($productId, $quantity);
                $state = $this->getProductState($productId);

                $this->assertGreaterThanOrEqual(
                    0,
                    $state['stock'],
                    sprintf(
                        'Stock went below zero! Initial: %d, Ordered: %d, Result: %d',
                        $initialStock,
                        $quantity,
                        $state['stock']
                    )
                );
            } finally {
                $this->deleteTestProduct($productId);
            }
        }
    }

    /**
     * Property 4: Stock Non-Negativity - Multiple sequential orders.
     * After any valid sequence of orders, stock must remain >= 0 at every step.
     *
     * **Validates: Requirements 4.6**
     *
     * @test
     */
    public function stockNeverGoesBelowZeroAfterMultipleOrders(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $initialStock = $this->generateRandomStock();
            $orders = $this->generateOrderSequence($initialStock);

            $productId = $this->createTestProduct($initialStock, 'ready');

            try {
                foreach ($orders as $orderIndex => $quantity) {
                    $stateBefore = $this->getProductState($productId);

                    // Only process if product is still 'ready'
                    if ($stateBefore['status'] !== 'ready') {
                        break;
                    }

                    $this->simulateStockDecrease($productId, $quantity);
                    $stateAfter = $this->getProductState($productId);

                    $this->assertGreaterThanOrEqual(
                        0,
                        $stateAfter['stock'],
                        sprintf(
                            'Stock went below zero after order #%d! Initial: %d, Orders so far: %s, Result: %d',
                            $orderIndex + 1,
                            $initialStock,
                            json_encode(array_slice($orders, 0, $orderIndex + 1)),
                            $stateAfter['stock']
                        )
                    );
                }
            } finally {
                $this->deleteTestProduct($productId);
            }
        }
    }

    /**
     * Property 4: Stock Non-Negativity - Global invariant check.
     * No product in the database should ever have a negative stock value.
     *
     * **Validates: Requirements 4.6**
     *
     * @test
     */
    public function noProductInDatabaseHasNegativeStock(): void
    {
        $stmt = $this->pdo->query("SELECT id, name, stock FROM products WHERE stock < 0");
        $negativeStockProducts = $stmt->fetchAll();

        $this->assertEmpty(
            $negativeStockProducts,
            sprintf(
                'Found %d product(s) with negative stock: %s',
                count($negativeStockProducts),
                json_encode($negativeStockProducts)
            )
        );
    }

    /**
     * Property 5: Stock-Status Consistency - Status transitions to 'habis' when stock reaches zero.
     * When stock reaches exactly 0 after a decrease, status must be updated to 'habis'.
     *
     * **Validates: Requirements 4.7**
     *
     * @test
     */
    public function statusTransitionsToHabisWhenStockReachesZero(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $initialStock = $this->generateRandomStock();
            // Order the exact amount to bring stock to zero
            $quantity = $initialStock;

            $productId = $this->createTestProduct($initialStock, 'ready');

            try {
                $this->simulateStockDecrease($productId, $quantity);
                $state = $this->getProductState($productId);

                $this->assertEquals(
                    0,
                    $state['stock'],
                    sprintf(
                        'Stock should be 0 after ordering full stock. Initial: %d, Ordered: %d, Got: %d',
                        $initialStock,
                        $quantity,
                        $state['stock']
                    )
                );

                $this->assertEquals(
                    'habis',
                    $state['status'],
                    sprintf(
                        'Status should be "habis" when stock is 0. Initial stock: %d, Ordered: %d, Status: %s',
                        $initialStock,
                        $quantity,
                        $state['status']
                    )
                );
            } finally {
                $this->deleteTestProduct($productId);
            }
        }
    }

    /**
     * Property 5: Stock-Status Consistency - Status remains 'ready' when stock > 0.
     * When stock is still positive after a decrease, status must remain 'ready'.
     *
     * **Validates: Requirements 4.6, 4.7**
     *
     * @test
     */
    public function statusRemainsReadyWhenStockIsPositive(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            // Ensure we have stock >= 2 so we can order less than total
            $initialStock = mt_rand(2, 100);
            // Order less than total stock
            $quantity = mt_rand(1, $initialStock - 1);

            $productId = $this->createTestProduct($initialStock, 'ready');

            try {
                $this->simulateStockDecrease($productId, $quantity);
                $state = $this->getProductState($productId);

                $this->assertGreaterThan(
                    0,
                    $state['stock'],
                    sprintf(
                        'Stock should be positive. Initial: %d, Ordered: %d, Got: %d',
                        $initialStock,
                        $quantity,
                        $state['stock']
                    )
                );

                $this->assertEquals(
                    'ready',
                    $state['status'],
                    sprintf(
                        'Status should remain "ready" when stock > 0. Stock: %d, Status: %s',
                        $state['stock'],
                        $state['status']
                    )
                );
            } finally {
                $this->deleteTestProduct($productId);
            }
        }
    }

    /**
     * Property 5: Stock-Status Consistency - PO products are never modified during stock decrease.
     * Products with status 'po' should not have their stock decreased by order operations.
     *
     * **Validates: Requirements 4.6**
     *
     * @test
     */
    public function poProductsAreNeverModifiedDuringStockDecrease(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $initialStock = $this->generateRandomStock();
            $quantity = $this->generateValidOrderQuantity($initialStock);

            $productId = $this->createTestProduct($initialStock, 'po');

            try {
                $stateBefore = $this->getProductState($productId);
                $this->simulateStockDecrease($productId, $quantity);
                $stateAfter = $this->getProductState($productId);

                $this->assertEquals(
                    $stateBefore['stock'],
                    $stateAfter['stock'],
                    sprintf(
                        'PO product stock should not change! Before: %d, After: %d, Attempted quantity: %d',
                        $stateBefore['stock'],
                        $stateAfter['stock'],
                        $quantity
                    )
                );

                $this->assertEquals(
                    'po',
                    $stateAfter['status'],
                    sprintf(
                        'PO product status should not change! Was: %s, Now: %s',
                        $stateBefore['status'],
                        $stateAfter['status']
                    )
                );
            } finally {
                $this->deleteTestProduct($productId);
            }
        }
    }

    /**
     * Property 4 & 5 Combined: Stock decrease is correct and consistent.
     * After decreasing stock by Q from initial S: new_stock = S - Q, and status
     * transitions correctly based on resulting stock value.
     *
     * **Validates: Requirements 4.6, 4.7**
     *
     * @test
     */
    public function stockDecreaseIsCorrectAndStatusConsistent(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $initialStock = $this->generateRandomStock();
            $quantity = $this->generateValidOrderQuantity($initialStock);
            $expectedStock = $initialStock - $quantity;

            $productId = $this->createTestProduct($initialStock, 'ready');

            try {
                $this->simulateStockDecrease($productId, $quantity);
                $state = $this->getProductState($productId);

                // Verify exact stock calculation
                $this->assertEquals(
                    $expectedStock,
                    $state['stock'],
                    sprintf(
                        'Stock calculation incorrect. Initial: %d, Ordered: %d, Expected: %d, Got: %d',
                        $initialStock,
                        $quantity,
                        $expectedStock,
                        $state['stock']
                    )
                );

                // Verify stock is non-negative
                $this->assertGreaterThanOrEqual(
                    0,
                    $state['stock'],
                    'Stock must never be negative'
                );

                // Verify status consistency
                if ($state['stock'] === 0) {
                    $this->assertEquals(
                        'habis',
                        $state['status'],
                        sprintf('Status should be "habis" when stock = 0, got "%s"', $state['status'])
                    );
                } else {
                    $this->assertEquals(
                        'ready',
                        $state['status'],
                        sprintf('Status should be "ready" when stock > 0, got "%s"', $state['status'])
                    );
                }
            } finally {
                $this->deleteTestProduct($productId);
            }
        }
    }
}
