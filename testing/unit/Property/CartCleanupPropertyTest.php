<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/helpers.php';

use PHPUnit\Framework\TestCase;

class CartCleanupPropertyTest extends TestCase
{
    /**
     * Set up session state before each test.
     */
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION['cart'] = [];
        $_SESSION['checkout_items'] = [];
    }

    /**
     * Clear session state after each test.
     */
    protected function tearDown(): void
    {
        $_SESSION['cart'] = [];
        $_SESSION['checkout_items'] = [];
    }

    /**
     * Test cleanupCartSession with empty cart.
     */
    public function testCleanupCartSessionWithEmptyCart(): void
    {
        $_SESSION['cart'] = [];
        $_SESSION['checkout_items'] = [];
        
        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->never())->method('prepare');
        
        cleanupCartSession($pdo);
        
        $this->assertEquals([], $_SESSION['cart']);
        $this->assertEquals([], $_SESSION['checkout_items']);
    }

    /**
     * Test cleanupCartSession cleans up deleted or inactive products.
     */
    public function testCleanupCartSessionRemovesGhostItems(): void
    {
        // 101 is active, 102 is inactive/deleted
        $_SESSION['cart'] = [
            101 => ['quantity' => 2, 'name' => 'Product Active'],
            102 => ['quantity' => 1, 'name' => 'Product Inactive']
        ];
        $_SESSION['checkout_items'] = [101, 102];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
             ->method('execute')
             ->with([101, 102]);
        
        // Database only returns active product ID (101)
        $stmt->expects($this->once())
             ->method('fetchAll')
             ->with(PDO::FETCH_COLUMN)
             ->willReturn([101]);

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
             ->method('prepare')
             ->with($this->stringContains('SELECT id FROM products WHERE id IN (?,?) AND is_active = 1'))
             ->willReturn($stmt);

        cleanupCartSession($pdo);

        // Assert 102 is removed
        $this->assertArrayHasKey(101, $_SESSION['cart']);
        $this->assertArrayNotHasKey(102, $_SESSION['cart']);

        // Assert checkout_items only has 101
        $this->assertEquals([101], $_SESSION['checkout_items']);
    }
}
