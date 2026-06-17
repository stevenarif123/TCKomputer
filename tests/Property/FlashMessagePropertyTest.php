<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/helpers.php';

use PHPUnit\Framework\TestCase;

/**
 * Property-Based Test for getFlashMessage() single-use behavior.
 *
 * **Validates: Requirements 20.2**
 *
 * Property 16: Flash Message Single-Use
 * For ANY flash message set in the session, calling getFlashMessage():
 * - Returns the message on the first call
 * - Returns null on all subsequent calls (message consumed)
 * - Works for all message types (success, warning, error)
 * - Messages include both 'type' and 'message' fields
 * - Multiple sequential set-then-get cycles work independently
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class FlashMessagePropertyTest extends TestCase
{
    /**
     * Number of random iterations per test method.
     */
    private const ITERATIONS = 200;

    /**
     * Valid flash message types.
     */
    private const FLASH_TYPES = ['success', 'warning', 'error'];

    protected function setUp(): void
    {
        // Ensure a session is available for manipulation
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        // Clear any existing flash messages
        unset($_SESSION['flash']);
    }

    protected function tearDown(): void
    {
        unset($_SESSION['flash']);
    }

    /**
     * Generate a random flash message string.
     */
    private function generateRandomMessage(int $maxLength = 255): string
    {
        $length = mt_rand(1, $maxLength);
        $chars = '';

        for ($i = 0; $i < $length; $i++) {
            $type = mt_rand(0, 4);
            switch ($type) {
                case 0: // ASCII lowercase
                    $chars .= chr(mt_rand(97, 122));
                    break;
                case 1: // ASCII uppercase
                    $chars .= chr(mt_rand(65, 90));
                    break;
                case 2: // Digits
                    $chars .= chr(mt_rand(48, 57));
                    break;
                case 3: // Spaces and punctuation
                    $pool = " !@#$%^&*()_+-=[]{}|;':\",./<>?";
                    $chars .= $pool[mt_rand(0, strlen($pool) - 1)];
                    break;
                case 4: // Unicode (Latin Extended)
                    $codepoint = mt_rand(0x00C0, 0x024F);
                    $chars .= mb_chr($codepoint, 'UTF-8');
                    break;
            }
        }

        return $chars;
    }

    /**
     * Generate a random flash message type.
     */
    private function generateRandomType(): string
    {
        return self::FLASH_TYPES[mt_rand(0, count(self::FLASH_TYPES) - 1)];
    }

    /**
     * Set a flash message directly in the session (simulating what redirect() does).
     */
    private function setFlashMessage(string $message, string $type = 'success'): void
    {
        $_SESSION['flash'] = [
            'type' => $type,
            'message' => $message,
        ];
    }

    /**
     * Property: After setting a flash message, getFlashMessage() returns it on first call.
     *
     * @test
     */
    public function flashMessageIsReturnedOnFirstCall(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $message = $this->generateRandomMessage();
            $type = $this->generateRandomType();

            $this->setFlashMessage($message, $type);
            $result = getFlashMessage();

            $this->assertNotNull(
                $result,
                "getFlashMessage() returned null on first call for message: " . json_encode($message)
            );
            $this->assertSame(
                $message,
                $result['message'],
                "Returned message does not match for input: " . json_encode($message)
            );
            $this->assertSame(
                $type,
                $result['type'],
                "Returned type does not match. Expected '$type', got '{$result['type']}'"
            );
        }
    }

    /**
     * Property: After calling getFlashMessage() once, a second call returns null (message consumed).
     *
     * @test
     */
    public function flashMessageIsNullOnSecondCall(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $message = $this->generateRandomMessage();
            $type = $this->generateRandomType();

            $this->setFlashMessage($message, $type);

            // First call consumes the message
            $firstCall = getFlashMessage();
            $this->assertNotNull($firstCall, "First call should return the flash message");

            // Second call must return null
            $secondCall = getFlashMessage();
            $this->assertNull(
                $secondCall,
                "getFlashMessage() should return null on second call but got: " . json_encode($secondCall)
                . " | Original message: " . json_encode($message)
            );
        }
    }

    /**
     * Property: The message is never available on a "second page load" (second getFlashMessage call).
     * This simulates multiple page loads after a single flash message is set.
     *
     * @test
     */
    public function flashMessageNotAvailableOnSubsequentCalls(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $message = $this->generateRandomMessage();
            $type = $this->generateRandomType();

            $this->setFlashMessage($message, $type);

            // First "page load" retrieves
            getFlashMessage();

            // Simulate 2-5 additional "page loads"
            $additionalLoads = mt_rand(2, 5);
            for ($j = 0; $j < $additionalLoads; $j++) {
                $result = getFlashMessage();
                $this->assertNull(
                    $result,
                    "Flash message still available on page load #" . ($j + 2)
                    . " for message: " . json_encode($message)
                );
            }
        }
    }

    /**
     * Property: Random flash messages of all types (success, warning, error) are single-use.
     *
     * @test
     */
    public function allFlashTypesAreSingleUse(): void
    {
        foreach (self::FLASH_TYPES as $type) {
            for ($i = 0; $i < 100; $i++) {
                $message = $this->generateRandomMessage();

                $this->setFlashMessage($message, $type);

                $first = getFlashMessage();
                $this->assertNotNull($first, "Flash of type '$type' should be returned on first call");
                $this->assertSame($type, $first['type'], "Type mismatch for '$type'");

                $second = getFlashMessage();
                $this->assertNull(
                    $second,
                    "Flash of type '$type' should be null on second call but got: " . json_encode($second)
                );
            }
        }
    }

    /**
     * Property: If no flash message exists, getFlashMessage() returns null.
     *
     * @test
     */
    public function noFlashMessageReturnsNull(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            // Ensure session has no flash
            unset($_SESSION['flash']);

            $result = getFlashMessage();
            $this->assertNull(
                $result,
                "getFlashMessage() should return null when no flash message exists, got: " . json_encode($result)
            );
        }
    }

    /**
     * Property: Flash messages include both 'type' and 'message' fields.
     *
     * @test
     */
    public function flashMessageContainsBothTypeAndMessageFields(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $message = $this->generateRandomMessage();
            $type = $this->generateRandomType();

            $this->setFlashMessage($message, $type);
            $result = getFlashMessage();

            $this->assertNotNull($result, "Flash message should not be null on first call");
            $this->assertArrayHasKey(
                'type',
                $result,
                "Flash message missing 'type' key"
            );
            $this->assertArrayHasKey(
                'message',
                $result,
                "Flash message missing 'message' key"
            );
            $this->assertCount(
                2,
                $result,
                "Flash message should have exactly 2 keys (type, message), got: " . json_encode(array_keys($result))
            );
        }
    }

    /**
     * Property: Multiple sequential set-then-get cycles work correctly (each message consumed independently).
     *
     * @test
     */
    public function multipleSequentialCyclesWorkIndependently(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            // Generate a random number of sequential cycles
            $cycles = mt_rand(2, 6);

            for ($c = 0; $c < $cycles; $c++) {
                $message = $this->generateRandomMessage();
                $type = $this->generateRandomType();

                $this->setFlashMessage($message, $type);

                $result = getFlashMessage();
                $this->assertNotNull(
                    $result,
                    "Cycle $c: First call should return flash message"
                );
                $this->assertSame(
                    $message,
                    $result['message'],
                    "Cycle $c: Message mismatch"
                );
                $this->assertSame(
                    $type,
                    $result['type'],
                    "Cycle $c: Type mismatch"
                );

                // Verify consumed
                $secondResult = getFlashMessage();
                $this->assertNull(
                    $secondResult,
                    "Cycle $c: Flash message should be consumed after first get, got: " . json_encode($secondResult)
                );
            }
        }
    }
}
