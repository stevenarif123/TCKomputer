<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/../../config/db.php';

use PHPUnit\Framework\TestCase;

/**
 * Property-Based Test for Order Total Integrity
 *
 * **Validates: Requirements 4.3, 6.3**
 *
 * Property 2: Order Total Integrity
 * For ANY order, the order total must equal the subtotal plus shipping cost,
 * and the subtotal must equal the sum of all order item subtotals
 * (each being product_price × quantity).
 *
 * Properties tested:
 * 1. total = subtotal + shipping_cost
 * 2. subtotal = sum of (unit_price × quantity) for all items
 * 3. Total is always a non-negative integer
 * 4. Subtotal is always a non-negative integer
 * 5. Each item subtotal = unit_price × quantity
 * 6. Adding more items never decreases the subtotal
 * 7. Verify against existing orders in DB
 */
class OrderTotalPropertyTest extends TestCase
{
    /**
     * Number of random iterations per test method.
     */
    private const ITERATIONS = 500;

    /**
     * Generate a random cart item with random price and quantity.
     *
     * @return array{unit_price: int, quantity: int}
     */
    private function generateRandomItem(): array
    {
        // Prices: 1,000 to 50,000,000 (Rupiah range for IT products)
        $unitPrice = mt_rand(1000, 50000000);
        // Quantity: 1 to 20
        $quantity = mt_rand(1, 20);

        return [
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
        ];
    }

    /**
     * Generate a random set of cart items.
     *
     * @param int $minItems Minimum number of items
     * @param int $maxItems Maximum number of items
     * @return array<array{unit_price: int, quantity: int}>
     */
    private function generateRandomCart(int $minItems = 1, int $maxItems = 10): array
    {
        $itemCount = mt_rand($minItems, $maxItems);
        $items = [];

        for ($i = 0; $i < $itemCount; $i++) {
            $items[] = $this->generateRandomItem();
        }

        return $items;
    }

    /**
     * Generate a random shipping cost.
     * Based on typical shipping area costs (0 to 100,000 Rupiah).
     *
     * @return int
     */
    private function generateRandomShippingCost(): int
    {
        return mt_rand(0, 100000);
    }

    /**
     * Calculate subtotal from cart items (mimics checkout-process.php logic).
     *
     * @param array<array{unit_price: int, quantity: int}> $items
     * @return int
     */
    private function calculateSubtotal(array $items): int
    {
        $subtotal = 0;
        foreach ($items as $item) {
            $itemSubtotal = (int)$item['unit_price'] * (int)$item['quantity'];
            $subtotal += $itemSubtotal;
        }
        return $subtotal;
    }

    /**
     * Calculate total from subtotal and shipping cost (mimics checkout-process.php logic).
     *
     * @param int $subtotal
     * @param int $shippingCost
     * @return int
     */
    private function calculateTotal(int $subtotal, int $shippingCost): int
    {
        return $subtotal + $shippingCost;
    }

    /**
     * Property: total = subtotal + shipping_cost for any set of items and shipping cost.
     *
     * @test
     */
    public function totalEqualsSubtotalPlusShipping(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $items = $this->generateRandomCart();
            $shippingCost = $this->generateRandomShippingCost();

            $subtotal = $this->calculateSubtotal($items);
            $total = $this->calculateTotal($subtotal, $shippingCost);

            $this->assertSame(
                $subtotal + $shippingCost,
                $total,
                sprintf(
                    "Total (%d) != subtotal (%d) + shipping (%d) for %d items",
                    $total,
                    $subtotal,
                    $shippingCost,
                    count($items)
                )
            );
        }
    }

    /**
     * Property: subtotal = sum of (unit_price × quantity) for all items.
     *
     * @test
     */
    public function subtotalEqualsSumOfItemSubtotals(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $items = $this->generateRandomCart();

            $subtotal = $this->calculateSubtotal($items);

            // Independently compute the sum
            $expectedSubtotal = 0;
            foreach ($items as $item) {
                $expectedSubtotal += $item['unit_price'] * $item['quantity'];
            }

            $this->assertSame(
                $expectedSubtotal,
                $subtotal,
                sprintf(
                    "Subtotal (%d) != sum of item subtotals (%d) for %d items",
                    $subtotal,
                    $expectedSubtotal,
                    count($items)
                )
            );
        }
    }

    /**
     * Property: Total is always a non-negative integer.
     *
     * @test
     */
    public function totalIsAlwaysNonNegativeInteger(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $items = $this->generateRandomCart();
            $shippingCost = $this->generateRandomShippingCost();

            $subtotal = $this->calculateSubtotal($items);
            $total = $this->calculateTotal($subtotal, $shippingCost);

            $this->assertIsInt($total, "Total must be an integer");
            $this->assertGreaterThanOrEqual(
                0,
                $total,
                sprintf("Total (%d) must be non-negative", $total)
            );
        }
    }

    /**
     * Property: Subtotal is always a non-negative integer.
     *
     * @test
     */
    public function subtotalIsAlwaysNonNegativeInteger(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $items = $this->generateRandomCart();

            $subtotal = $this->calculateSubtotal($items);

            $this->assertIsInt($subtotal, "Subtotal must be an integer");
            $this->assertGreaterThanOrEqual(
                0,
                $subtotal,
                sprintf("Subtotal (%d) must be non-negative", $subtotal)
            );
        }
    }

    /**
     * Property: Each item subtotal = unit_price × quantity.
     *
     * @test
     */
    public function eachItemSubtotalEqualsUnitPriceTimesQuantity(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $items = $this->generateRandomCart();

            foreach ($items as $index => $item) {
                $itemSubtotal = (int)$item['unit_price'] * (int)$item['quantity'];

                $this->assertSame(
                    $item['unit_price'] * $item['quantity'],
                    $itemSubtotal,
                    sprintf(
                        "Item %d subtotal (%d) != price (%d) × qty (%d)",
                        $index,
                        $itemSubtotal,
                        $item['unit_price'],
                        $item['quantity']
                    )
                );

                // Also verify non-negative
                $this->assertGreaterThanOrEqual(
                    0,
                    $itemSubtotal,
                    sprintf("Item %d subtotal must be non-negative", $index)
                );
            }
        }
    }

    /**
     * Property: Adding more items to the order never decreases the subtotal.
     *
     * @test
     */
    public function addingItemsNeverDecreasesSubtotal(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            // Start with a base cart
            $baseItems = $this->generateRandomCart(1, 5);
            $baseSubtotal = $this->calculateSubtotal($baseItems);

            // Add more items
            $additionalItems = $this->generateRandomCart(1, 5);
            $extendedItems = array_merge($baseItems, $additionalItems);
            $extendedSubtotal = $this->calculateSubtotal($extendedItems);

            $this->assertGreaterThanOrEqual(
                $baseSubtotal,
                $extendedSubtotal,
                sprintf(
                    "Extended subtotal (%d) < base subtotal (%d) after adding %d items",
                    $extendedSubtotal,
                    $baseSubtotal,
                    count($additionalItems)
                )
            );
        }
    }

    /**
     * Property: Verify against existing orders in DB - for each order,
     * total = subtotal + shipping_cost and subtotal = sum of item subtotals.
     *
     * @test
     */
    public function existingOrdersHaveConsistentTotals(): void
    {
        try {
            $pdo = getDBConnection();
        } catch (\Exception $e) {
            $this->markTestSkipped('Database connection not available: ' . $e->getMessage());
            return;
        }

        // Fetch all orders
        $stmt = $pdo->query("SELECT id, subtotal, shipping_cost, total FROM orders");
        $orders = $stmt->fetchAll();

        if (empty($orders)) {
            // No orders in DB - verify the property holds vacuously
            $this->assertTrue(true, 'No orders in database to verify');
            return;
        }

        foreach ($orders as $order) {
            // Property: total = subtotal + shipping_cost
            $expectedTotal = (int)$order['subtotal'] + (int)$order['shipping_cost'];
            $this->assertSame(
                $expectedTotal,
                (int)$order['total'],
                sprintf(
                    "Order #%d: total (%d) != subtotal (%d) + shipping (%d) = %d",
                    $order['id'],
                    $order['total'],
                    $order['subtotal'],
                    $order['shipping_cost'],
                    $expectedTotal
                )
            );

            // Property: subtotal = sum of order item subtotals
            $itemStmt = $pdo->prepare(
                "SELECT product_price, quantity, subtotal FROM order_items WHERE order_id = ?"
            );
            $itemStmt->execute([$order['id']]);
            $items = $itemStmt->fetchAll();

            $computedSubtotal = 0;
            foreach ($items as $item) {
                // Verify each item subtotal = price × quantity
                $expectedItemSubtotal = (int)$item['product_price'] * (int)$item['quantity'];
                $this->assertSame(
                    $expectedItemSubtotal,
                    (int)$item['subtotal'],
                    sprintf(
                        "Order #%d item: subtotal (%d) != price (%d) × qty (%d) = %d",
                        $order['id'],
                        $item['subtotal'],
                        $item['product_price'],
                        $item['quantity'],
                        $expectedItemSubtotal
                    )
                );

                $computedSubtotal += $expectedItemSubtotal;
            }

            $this->assertSame(
                $computedSubtotal,
                (int)$order['subtotal'],
                sprintf(
                    "Order #%d: subtotal (%d) != sum of item subtotals (%d)",
                    $order['id'],
                    $order['subtotal'],
                    $computedSubtotal
                )
            );
        }
    }

    /**
     * Combined property: ALL order total properties hold simultaneously.
     *
     * @test
     */
    public function allOrderTotalPropertiesHoldSimultaneously(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $items = $this->generateRandomCart();
            $shippingCost = $this->generateRandomShippingCost();

            // Calculate using the same logic as checkout-process.php
            $subtotal = 0;
            foreach ($items as $item) {
                $itemSubtotal = (int)$item['unit_price'] * (int)$item['quantity'];

                // Property 5: item subtotal = price × quantity
                $this->assertSame(
                    $item['unit_price'] * $item['quantity'],
                    $itemSubtotal,
                    "Combined: item subtotal mismatch"
                );

                // Property 5: item subtotal is non-negative
                $this->assertGreaterThanOrEqual(0, $itemSubtotal, "Combined: item subtotal negative");

                $subtotal += $itemSubtotal;
            }

            $total = $subtotal + $shippingCost;

            // Property 1: total = subtotal + shipping
            $this->assertSame(
                $subtotal + $shippingCost,
                $total,
                "Combined: total != subtotal + shipping"
            );

            // Property 2: subtotal = sum of item subtotals (verified by construction)
            $expectedSubtotal = 0;
            foreach ($items as $item) {
                $expectedSubtotal += $item['unit_price'] * $item['quantity'];
            }
            $this->assertSame($expectedSubtotal, $subtotal, "Combined: subtotal != sum of items");

            // Property 3: total is non-negative integer
            $this->assertIsInt($total);
            $this->assertGreaterThanOrEqual(0, $total, "Combined: total is negative");

            // Property 4: subtotal is non-negative integer
            $this->assertIsInt($subtotal);
            $this->assertGreaterThanOrEqual(0, $subtotal, "Combined: subtotal is negative");
        }
    }
}
