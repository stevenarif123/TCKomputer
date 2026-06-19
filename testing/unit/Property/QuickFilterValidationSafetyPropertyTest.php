<?php

use PHPUnit\Framework\TestCase;

class QuickFilterValidationSafetyPropertyTest extends TestCase
{
    protected function setUp(): void
    {
        // Load the helpers file
        require_once dirname(__DIR__, 3) . '/config/helpers.php';
    }

    /**
     * Property 11: Quick Filter Validation Safety
     * For any raw filter query value, `validateQuickFilter()` should return 
     * only `''`, `ready`, `promo`, or `new`; values outside the allowed set should return `''`
     * Validates: Requirements 9.1, 9.2
     */
    public function testQuickFilterValidationSafety(): void
    {
        $allowedValues = ['', 'ready', 'promo', 'new'];

        // Test the valid exact inputs
        $this->assertEquals('ready', validateQuickFilter('ready'), "Failed for 'ready'");
        $this->assertEquals('promo', validateQuickFilter('promo'), "Failed for 'promo'");
        $this->assertEquals('new', validateQuickFilter('new'), "Failed for 'new'");
        $this->assertEquals('', validateQuickFilter(''), "Failed for ''");

        // Property testing for random values (counter-examples / fuzzed data)
        for ($i = 0; $i < 1000; $i++) {
            // Generate a random string
            $randomString = $this->generateRandomString(rand(1, 20));
            
            // Generate random edge cases
            if ($i % 5 === 0) {
                $randomString = strtoupper($allowedValues[rand(1, 3)]); // Case sensitivity check
            } elseif ($i % 5 === 1) {
                $randomString = ' ' . $allowedValues[rand(1, 3)]; // Leading space
            } elseif ($i % 5 === 2) {
                $randomString = $allowedValues[rand(1, 3)] . ' '; // Trailing space
            } elseif ($i % 5 === 3) {
                $randomString = $allowedValues[rand(1, 3)] . rand(1, 9); // Similar string
            }

            // Test the function
            $result = validateQuickFilter($randomString);

            // Assert that the result is strictly one of the allowed values
            $this->assertTrue(
                in_array($result, $allowedValues, true),
                "validateQuickFilter returned an invalid value '{$result}' for input '{$randomString}'"
            );

            // Additional logic: if the random string is exactly one of the allowed non-empty,
            // it should return itself, otherwise empty string.
            if (in_array($randomString, ['ready', 'promo', 'new'], true)) {
                $this->assertEquals($randomString, $result);
            } else {
                $this->assertEquals('', $result, "Expected '' for input '{$randomString}', got '{$result}'");
            }
        }
    }

    private function generateRandomString(int $length): string
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ !@#$%^&*()_+-=[]{}|;:\'",.<>/?`~';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
}
