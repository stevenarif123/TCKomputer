<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/../../config/db.php';

use PHPUnit\Framework\TestCase;

/**
 * Property-Based Test for Cart Add Logic
 *
 * **Validates: Requirements 3.1, 3.2, 2.3**
 *
 * Property 1: Cart Consistency - Cart items always have qty > 0 after any valid add
 * Property 6: Purchase Constraint - Habis/inactive products are never added to cart
 * Property 18: Cart Quantity Cap for Ready Products - quantity never exceeds stock
 *
 * Since cart-add.php uses redirects (exit calls), we simulate the pure logic
 * in a local testable function that mirrors the behavior but returns a result
 * instead of redirecting.
 */
class CartAddPropertyTest extends TestCase
{
    /**
     * Number of random iterations per test method.
     */
    private const ITERATIONS = 200;

    /**
     * Simulates the cart-add logic from actions/cart-add.php.
     * Returns an associative array with the result instead of redirecting.
     *
     * @param array $product  Product data: id, name, selling_price, stock, status, image, is_active
     * @param int   $quantity Requested quantity to add
     * @param array $cart     Current cart state (passed by reference)
     * @return array ['success' => bool, 'message' => string]
     */
    private function simulateCartAdd(array $product, int $quantity, array &$cart): array
    {
        // Ensure quantity is at least 1
        if ($quantity < 1) {
            $quantity = 1;
        }

        // Product not found or inactive
        if (empty($product) || $product['is_active'] !== 1) {
            return ['success' => false, 'message' => 'Produk tidak ditemukan'];
        }

        // Product sold out (habis)
        if ($product['status'] === 'habis') {
            return ['success' => false, 'message' => 'Produk tidak tersedia'];
        }

        // Ready product with zero stock
        if ($product['status'] === 'ready' && $product['stock'] <= 0) {
            return ['success' => false, 'message' => 'Stok habis'];
        }

        $productId = $product['id'];

        // Add to cart or increment existing quantity
        if (isset($cart[$productId])) {
            // Increment existing quantity
            $newQty = $cart[$productId]['quantity'] + $quantity;

            // For ready products, cap at available stock
            if ($product['status'] === 'ready' && $newQty > $product['stock']) {
                $newQty = $product['stock'];
                $cart[$productId]['quantity'] = $newQty;
                return ['success' => true, 'message' => 'Jumlah disesuaikan dengan stok tersedia'];
            }

            $cart[$productId]['quantity'] = $newQty;
        } else {
            // Cap initial quantity for ready products
            $cappedQuantity = $quantity;
            if ($product['status'] === 'ready' && $quantity > $product['stock']) {
                $cappedQuantity = $product['stock'];
            }

            $cart[$productId] = [
                'quantity' => $cappedQuantity,
                'name' => $product['name'],
                'price' => (int) $product['selling_price'],
                'image' => $product['image'],
            ];

            if ($cappedQuantity < $quantity) {
                return ['success' => true, 'message' => 'Jumlah disesuaikan dengan stok tersedia'];
            }
        }

        return ['success' => true, 'message' => 'Produk ditambahkan ke keranjang'];
    }

    /**
     * Generate a random product with the given status.
     *
     * @param string $status One of: 'ready', 'po', 'habis'
     * @param int|null $stock Override stock value (null for random)
     * @param int $isActive Whether product is active
     * @return array
     */
    private function generateProduct(string $status = 'ready', ?int $stock = null, int $isActive = 1): array
    {
        $id = mt_rand(1, 10000);

        if ($stock === null) {
            if ($status === 'ready') {
                $stock = mt_rand(1, 100);
            } elseif ($status === 'po') {
                $stock = mt_rand(0, 50);
            } else {
                $stock = 0;
            }
        }

        return [
            'id' => $id,
            'name' => 'Product ' . $id,
            'selling_price' => mt_rand(10000, 5000000),
            'stock' => $stock,
            'status' => $status,
            'image' => 'product_' . $id . '.jpg',
            'is_active' => $isActive,
        ];
    }

    /**
     * Generate a random positive quantity.
     */
    private function generateQuantity(int $max = 20): int
    {
        return mt_rand(1, $max);
    }

    // =========================================================================
    // Property 1: Cart Consistency
    // Cart items always have quantity > 0 after any valid add operation
    // =========================================================================

    /**
     * Property: After a successful cart add, all cart items have quantity > 0.
     *
     * **Validates: Requirements 3.1**
     *
     * @test
     */
    public function cartItemsAlwaysHavePositiveQuantityAfterValidAdd(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $cart = [];
            $status = ['ready', 'po'][mt_rand(0, 1)];
            $product = $this->generateProduct($status);
            $quantity = $this->generateQuantity();

            $result = $this->simulateCartAdd($product, $quantity, $cart);

            if ($result['success']) {
                foreach ($cart as $productId => $item) {
                    $this->assertGreaterThan(
                        0,
                        $item['quantity'],
                        "Cart item has qty <= 0 after valid add. Product status: $status, "
                        . "requested qty: $quantity, stock: {$product['stock']}"
                    );
                }
            }
        }
    }

    /**
     * Property: After multiple sequential adds, all cart items still have qty > 0.
     *
     * **Validates: Requirements 3.1**
     *
     * @test
     */
    public function cartItemsRemainPositiveAfterMultipleAdds(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $cart = [];
            $product = $this->generateProduct('ready', mt_rand(1, 50));
            $numAdds = mt_rand(2, 5);

            for ($j = 0; $j < $numAdds; $j++) {
                $quantity = $this->generateQuantity(10);
                $this->simulateCartAdd($product, $quantity, $cart);
            }

            foreach ($cart as $productId => $item) {
                $this->assertGreaterThan(
                    0,
                    $item['quantity'],
                    "Cart item has qty <= 0 after $numAdds sequential adds."
                );
            }
        }
    }

    // =========================================================================
    // Property 6: Purchase Constraint
    // Habis products are never added to cart (rejected)
    // Inactive products are never added to cart (rejected)
    // =========================================================================

    /**
     * Property: Products with status 'habis' are NEVER added to the cart.
     *
     * **Validates: Requirements 2.3**
     *
     * @test
     */
    public function habisProductsAreNeverAddedToCart(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $cart = [];
            $product = $this->generateProduct('habis', 0);
            $quantity = $this->generateQuantity();

            $result = $this->simulateCartAdd($product, $quantity, $cart);

            $this->assertFalse(
                $result['success'],
                "Habis product was accepted into cart. Qty requested: $quantity"
            );
            $this->assertEmpty(
                $cart,
                "Cart is not empty after attempting to add habis product."
            );
        }
    }

    /**
     * Property: Inactive products (is_active = 0) are NEVER added to the cart.
     *
     * **Validates: Requirements 3.1**
     *
     * @test
     */
    public function inactiveProductsAreNeverAddedToCart(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $cart = [];
            $status = ['ready', 'po', 'habis'][mt_rand(0, 2)];
            $product = $this->generateProduct($status, mt_rand(0, 50), 0);
            $quantity = $this->generateQuantity();

            $result = $this->simulateCartAdd($product, $quantity, $cart);

            $this->assertFalse(
                $result['success'],
                "Inactive product was accepted into cart. Status: $status, qty: $quantity"
            );
            $this->assertEmpty(
                $cart,
                "Cart is not empty after attempting to add inactive product."
            );
        }
    }

    /**
     * Property: Ready products with zero stock are NEVER added to the cart.
     *
     * **Validates: Requirements 3.1**
     *
     * @test
     */
    public function readyProductsWithZeroStockAreRejected(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $cart = [];
            $product = $this->generateProduct('ready', 0);
            $quantity = $this->generateQuantity();

            $result = $this->simulateCartAdd($product, $quantity, $cart);

            $this->assertFalse(
                $result['success'],
                "Ready product with 0 stock was accepted. Qty: $quantity"
            );
            $this->assertEmpty(
                $cart,
                "Cart is not empty after attempting to add zero-stock ready product."
            );
        }
    }

    // =========================================================================
    // Property 18: Cart Quantity Cap for Ready Products
    // For Ready products, cart quantity never exceeds available stock
    // =========================================================================

    /**
     * Property: For Ready products, cart quantity NEVER exceeds available stock (initial add).
     *
     * **Validates: Requirements 3.2**
     *
     * @test
     */
    public function readyProductQuantityNeverExceedsStockOnInitialAdd(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $cart = [];
            $stock = mt_rand(1, 50);
            $product = $this->generateProduct('ready', $stock);
            // Request quantity potentially larger than stock
            $quantity = mt_rand(1, $stock * 3);

            $result = $this->simulateCartAdd($product, $quantity, $cart);

            if ($result['success'] && isset($cart[$product['id']])) {
                $this->assertLessThanOrEqual(
                    $stock,
                    $cart[$product['id']]['quantity'],
                    "Ready product cart qty ({$cart[$product['id']]['quantity']}) exceeds stock ($stock). "
                    . "Requested: $quantity"
                );
            }
        }
    }

    /**
     * Property: For Ready products, cart quantity NEVER exceeds stock after multiple adds.
     *
     * **Validates: Requirements 3.2**
     *
     * @test
     */
    public function readyProductQuantityNeverExceedsStockAfterMultipleAdds(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $cart = [];
            $stock = mt_rand(1, 30);
            $product = $this->generateProduct('ready', $stock);
            $numAdds = mt_rand(2, 6);

            for ($j = 0; $j < $numAdds; $j++) {
                $quantity = mt_rand(1, $stock);
                $this->simulateCartAdd($product, $quantity, $cart);
            }

            if (isset($cart[$product['id']])) {
                $this->assertLessThanOrEqual(
                    $stock,
                    $cart[$product['id']]['quantity'],
                    "After $numAdds adds, ready product cart qty ({$cart[$product['id']]['quantity']}) "
                    . "exceeds stock ($stock)."
                );
            }
        }
    }

    /**
     * Property: For PO products, any positive quantity is accepted (no stock cap).
     *
     * **Validates: Requirements 3.1**
     *
     * @test
     */
    public function poProductsAcceptAnyPositiveQuantity(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $cart = [];
            $product = $this->generateProduct('po', mt_rand(0, 100));
            $quantity = mt_rand(1, 1000);

            $result = $this->simulateCartAdd($product, $quantity, $cart);

            $this->assertTrue(
                $result['success'],
                "PO product with qty $quantity was rejected."
            );
            $this->assertEquals(
                $quantity,
                $cart[$product['id']]['quantity'],
                "PO product quantity in cart doesn't match requested quantity."
            );
        }
    }

    /**
     * Property: Adding a product already in cart increments quantity (not duplicates entry).
     *
     * **Validates: Requirements 3.1**
     *
     * @test
     */
    public function addingExistingProductIncrementsQuantity(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $cart = [];
            $product = $this->generateProduct('po', mt_rand(0, 100));
            $qty1 = mt_rand(1, 50);
            $qty2 = mt_rand(1, 50);

            $this->simulateCartAdd($product, $qty1, $cart);
            $this->simulateCartAdd($product, $qty2, $cart);

            // Cart should have exactly one entry for this product
            $this->assertCount(
                1,
                $cart,
                "Cart has duplicate entries after adding same product twice."
            );
            $this->assertEquals(
                $qty1 + $qty2,
                $cart[$product['id']]['quantity'],
                "Cart quantity should be sum of both adds for PO product."
            );
        }
    }

    /**
     * Property: After capping at stock for Ready products, quantity equals stock exactly.
     *
     * **Validates: Requirements 3.2**
     *
     * @test
     */
    public function readyProductCappedQuantityEqualsStockExactly(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $cart = [];
            $stock = mt_rand(1, 30);
            $product = $this->generateProduct('ready', $stock);
            // Request MORE than stock to trigger capping
            $quantity = $stock + mt_rand(1, 50);

            $result = $this->simulateCartAdd($product, $quantity, $cart);

            $this->assertTrue(
                $result['success'],
                "Ready product with stock $stock should be added even if qty ($quantity) exceeds stock."
            );
            $this->assertEquals(
                $stock,
                $cart[$product['id']]['quantity'],
                "When qty ($quantity) exceeds stock ($stock), cart qty should equal stock exactly. "
                . "Got: {$cart[$product['id']]['quantity']}"
            );
        }
    }
}
