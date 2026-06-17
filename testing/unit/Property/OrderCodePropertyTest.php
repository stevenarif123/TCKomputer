<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/../../config/db.php';

use PHPUnit\Framework\TestCase;

/**
 * Property-Based Test for generateOrderCode()
 *
 * **Validates: Requirements 4.5**
 *
 * Property 3: Order Code Uniqueness and Format
 * For ANY number of consecutive calls to generateOrderCode(), the result must satisfy ALL of:
 * - Output matches the regex pattern /^SIT-\d{8}-\d{4}$/
 * - The date part (YYYYMMDD) is a valid calendar date
 * - The sequence part (XXXX) is a 4-digit zero-padded number (0001-9999)
 * - Multiple consecutive calls produce unique codes (no duplicates)
 * - Sequence increments correctly (each new code has sequence = previous + 1)
 * - First code of a day has sequence 0001
 */
class OrderCodePropertyTest extends TestCase
{
    private PDO $pdo;

    /**
     * Number of iterations for sequential generation tests.
     */
    private const ITERATIONS = 50;

    protected function setUp(): void
    {
        $this->pdo = getDBConnection();
        // Clean up any test orders before each test
        $this->cleanTestOrders();
    }

    protected function tearDown(): void
    {
        // Clean up test orders after each test
        $this->cleanTestOrders();
    }

    /**
     * Remove test orders inserted during testing.
     */
    private function cleanTestOrders(): void
    {
        $datePrefix = 'SIT-' . date('Ymd') . '-';
        // Delete order_items first (FK constraint), then orders
        $stmt = $this->pdo->prepare(
            "DELETE oi FROM order_items oi 
             INNER JOIN orders o ON oi.order_id = o.id 
             WHERE o.order_code LIKE ? AND o.buyer_name = 'PBT_TEST_ORDER'"
        );
        $stmt->execute([$datePrefix . '%']);

        $stmt = $this->pdo->prepare(
            "DELETE FROM orders WHERE order_code LIKE ? AND buyer_name = 'PBT_TEST_ORDER'"
        );
        $stmt->execute([$datePrefix . '%']);
    }

    /**
     * Insert a test order with the given order code into the database.
     */
    private function insertTestOrder(string $orderCode): void
    {
        // We need a valid shipping_area_id - get the first one
        $stmt = $this->pdo->query("SELECT id FROM shipping_areas LIMIT 1");
        $area = $stmt->fetch();
        $shippingAreaId = $area ? $area['id'] : 1;

        $stmt = $this->pdo->prepare(
            "INSERT INTO orders (order_code, buyer_name, buyer_phone, buyer_address, 
             shipping_area_id, shipping_cost, subtotal, total, payment_method, 
             payment_status, order_status, shipping_option) 
             VALUES (?, 'PBT_TEST_ORDER', '081234567890', 'Test Address', 
             ?, 0, 0, 0, 'transfer', 'belum_dibayar', 'menunggu_konfirmasi', 'self_pickup')"
        );
        $stmt->execute([$orderCode, $shippingAreaId]);
    }

    /**
     * Property: Generated code always matches regex pattern /^SIT-\d{8}-\d{4}$/
     *
     * @test
     */
    public function orderCodeMatchesExpectedFormat(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $code = generateOrderCode($this->pdo);

            $this->assertMatchesRegularExpression(
                '/^SIT-\d{8}-\d{4}$/',
                $code,
                "Order code does not match SIT-YYYYMMDD-XXXX format: " . $code
            );

            // Insert so next iteration gets a different code
            $this->insertTestOrder($code);
        }
    }

    /**
     * Property: The date part (YYYYMMDD) is a valid calendar date.
     *
     * @test
     */
    public function orderCodeDatePartIsValidDate(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $code = generateOrderCode($this->pdo);

            // Extract date part: positions 4-11 (after "SIT-")
            $datePart = substr($code, 4, 8);
            $year = (int) substr($datePart, 0, 4);
            $month = (int) substr($datePart, 4, 2);
            $day = (int) substr($datePart, 6, 2);

            $this->assertTrue(
                checkdate($month, $day, $year),
                "Order code contains invalid date: {$datePart} in code: {$code}"
            );

            // Also verify it matches today's date
            $this->assertSame(
                date('Ymd'),
                $datePart,
                "Order code date does not match today's date: {$datePart} in code: {$code}"
            );

            $this->insertTestOrder($code);
        }
    }

    /**
     * Property: The sequence part (XXXX) is a 4-digit zero-padded number between 0001 and 9999.
     *
     * @test
     */
    public function orderCodeSequenceIsValid4DigitZeroPadded(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $code = generateOrderCode($this->pdo);

            // Extract sequence part: last 4 characters
            $seqPart = substr($code, -4);

            // Must be exactly 4 digits
            $this->assertMatchesRegularExpression(
                '/^\d{4}$/',
                $seqPart,
                "Sequence part is not 4 digits: {$seqPart} in code: {$code}"
            );

            // Numeric value must be between 1 and 9999
            $seqNum = (int) $seqPart;
            $this->assertGreaterThanOrEqual(
                1,
                $seqNum,
                "Sequence number is less than 1: {$seqNum} in code: {$code}"
            );
            $this->assertLessThanOrEqual(
                9999,
                $seqNum,
                "Sequence number exceeds 9999: {$seqNum} in code: {$code}"
            );

            // Verify zero-padding: string must be the zero-padded version of the number
            $this->assertSame(
                str_pad((string) $seqNum, 4, '0', STR_PAD_LEFT),
                $seqPart,
                "Sequence is not properly zero-padded: {$seqPart} in code: {$code}"
            );

            $this->insertTestOrder($code);
        }
    }

    /**
     * Property: Multiple consecutive calls produce unique codes (no duplicates).
     *
     * @test
     */
    public function consecutiveCallsProduceUniqueCodes(): void
    {
        $codes = [];

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $code = generateOrderCode($this->pdo);

            $this->assertNotContains(
                $code,
                $codes,
                "Duplicate order code detected: {$code} (iteration {$i})"
            );

            $codes[] = $code;
            $this->insertTestOrder($code);
        }

        // Final verification: all codes are unique
        $this->assertCount(
            count(array_unique($codes)),
            $codes,
            "Generated codes contain duplicates"
        );
    }

    /**
     * Property: Sequence increments correctly (each new code has sequence = previous + 1).
     *
     * @test
     */
    public function sequenceIncrementsCorrectly(): void
    {
        $previousSeq = null;

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $code = generateOrderCode($this->pdo);
            $currentSeq = (int) substr($code, -4);

            if ($previousSeq !== null) {
                $this->assertSame(
                    $previousSeq + 1,
                    $currentSeq,
                    "Sequence did not increment correctly. Expected " .
                    ($previousSeq + 1) . " but got {$currentSeq} in code: {$code}"
                );
            }

            $previousSeq = $currentSeq;
            $this->insertTestOrder($code);
        }
    }

    /**
     * Property: First code of a day has sequence 0001.
     *
     * @test
     */
    public function firstCodeOfDayHasSequence0001(): void
    {
        // Ensure no orders exist for today (cleanup already done in setUp)
        $datePrefix = 'SIT-' . date('Ymd') . '-';
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM orders WHERE order_code LIKE ?"
        );
        $stmt->execute([$datePrefix . '%']);
        $existingCount = (int) $stmt->fetchColumn();

        // If there are existing orders for today (not from our tests), skip this specific check
        if ($existingCount > 0) {
            // Delete all today's orders to ensure clean state
            $stmt = $this->pdo->prepare(
                "DELETE oi FROM order_items oi 
                 INNER JOIN orders o ON oi.order_id = o.id 
                 WHERE o.order_code LIKE ?"
            );
            $stmt->execute([$datePrefix . '%']);

            $stmt = $this->pdo->prepare(
                "DELETE FROM orders WHERE order_code LIKE ?"
            );
            $stmt->execute([$datePrefix . '%']);
        }

        // Now generate the first code
        $code = generateOrderCode($this->pdo);
        $seqPart = substr($code, -4);

        $this->assertSame(
            '0001',
            $seqPart,
            "First code of the day should have sequence 0001 but got: {$seqPart} in code: {$code}"
        );
    }
}
