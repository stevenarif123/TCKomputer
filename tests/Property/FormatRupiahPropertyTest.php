<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/helpers.php';

use PHPUnit\Framework\TestCase;

/**
 * Property-Based Test for formatRupiah()
 *
 * **Validates: Requirements 6.1, 6.2**
 *
 * Property 11: Rupiah Formatting
 * For ANY non-negative integer, the formatted output must satisfy ALL of:
 * - Output always starts with "Rp " (prefix "Rp" followed by one space)
 * - For amount = 0, output is exactly "Rp 0"
 * - For any positive integer, the numeric part uses dots as thousands separators correctly
 * - Output never contains decimal places
 * - For negative inputs, output is "Rp 0" (treated as invalid)
 * - The numeric part after "Rp " contains only digits and dots
 */
class FormatRupiahPropertyTest extends TestCase
{
    /**
     * Number of random iterations per test method.
     */
    private const ITERATIONS = 500;

    /**
     * Generate a random non-negative integer.
     */
    private function generateRandomNonNegativeInt(): int
    {
        $type = mt_rand(0, 4);
        switch ($type) {
            case 0: // Small values
                return mt_rand(0, 999);
            case 1: // Thousands
                return mt_rand(1000, 999999);
            case 2: // Millions
                return mt_rand(1000000, 999999999);
            case 3: // Large values
                return mt_rand(1000000000, PHP_INT_MAX >> 1);
            default: // Zero and near-zero
                return mt_rand(0, 10);
        }
    }

    /**
     * Generate a random negative integer.
     */
    private function generateRandomNegativeInt(): int
    {
        return -mt_rand(1, PHP_INT_MAX >> 1);
    }

    /**
     * Edge case values for thorough testing.
     *
     * @return array<int>
     */
    private function edgeCaseValues(): array
    {
        return [
            0,                  // Zero
            1,                  // Single digit
            9,                  // Max single digit
            10,                 // Two digits
            99,                 // Max two digits
            100,                // Three digits
            999,                // Max three digits (no dot needed)
            1000,               // First value requiring dot separator
            1001,               // Just above thousand
            9999,               // Max four digits
            10000,              // Five digits
            99999,              // Max five digits
            100000,             // Six digits
            999999,             // Max six digits
            1000000,            // One million
            1500000,            // Typical product price
            10000000,           // Ten million
            100000000,          // Hundred million
            1000000000,         // One billion
            PHP_INT_MAX,        // Maximum integer value
        ];
    }

    /**
     * Property 1: Output always starts with "Rp " (the prefix "Rp" followed by one space).
     * Validates: Requirement 6.1
     *
     * @test
     */
    public function outputAlwaysStartsWithRpPrefix(): void
    {
        // Test edge cases
        foreach ($this->edgeCaseValues() as $amount) {
            $result = formatRupiah($amount);
            $this->assertStringStartsWith(
                'Rp ',
                $result,
                "Output does not start with 'Rp ' for amount: {$amount}, got: {$result}"
            );
        }

        // Test random non-negative inputs
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $amount = $this->generateRandomNonNegativeInt();
            $result = formatRupiah($amount);
            $this->assertStringStartsWith(
                'Rp ',
                $result,
                "Output does not start with 'Rp ' for random amount: {$amount}, got: {$result}"
            );
        }

        // Test negative inputs (should still produce valid prefix)
        for ($i = 0; $i < 50; $i++) {
            $amount = $this->generateRandomNegativeInt();
            $result = formatRupiah($amount);
            $this->assertStringStartsWith(
                'Rp ',
                $result,
                "Output does not start with 'Rp ' for negative amount: {$amount}, got: {$result}"
            );
        }
    }

    /**
     * Property 2: For amount = 0, output is exactly "Rp 0".
     * Validates: Requirement 6.2
     *
     * @test
     */
    public function zeroAmountProducesExactlyRpZero(): void
    {
        $result = formatRupiah(0);
        $this->assertSame(
            'Rp 0',
            $result,
            "Zero amount should produce exactly 'Rp 0', got: {$result}"
        );
    }

    /**
     * Property 3: For any positive integer, the numeric part uses dots as thousands separators correctly.
     * Validates: Requirement 6.1
     *
     * @test
     */
    public function positiveIntegersUseDotThousandsSeparatorsCorrectly(): void
    {
        // Test edge cases
        foreach ($this->edgeCaseValues() as $amount) {
            if ($amount <= 0) {
                continue;
            }
            $result = formatRupiah($amount);
            $numericPart = substr($result, 3); // Remove "Rp "

            // Verify the numeric part, when dots are removed, equals the original number
            $withoutDots = str_replace('.', '', $numericPart);
            $this->assertSame(
                (string) $amount,
                $withoutDots,
                "Numeric value mismatch for amount: {$amount}, got numeric part: {$numericPart}"
            );

            // Verify dot placement: dots separate groups of exactly 3 digits from the right
            $parts = explode('.', $numericPart);
            // First part can be 1-3 digits, remaining parts must be exactly 3 digits
            if (count($parts) > 1) {
                $this->assertMatchesRegularExpression(
                    '/^[1-9]\d{0,2}$/',
                    $parts[0],
                    "First group should be 1-3 digits for amount: {$amount}, got: {$parts[0]}"
                );
                for ($j = 1; $j < count($parts); $j++) {
                    $this->assertMatchesRegularExpression(
                        '/^\d{3}$/',
                        $parts[$j],
                        "Group {$j} should be exactly 3 digits for amount: {$amount}, got: {$parts[$j]}"
                    );
                }
            }
        }

        // Test random positive inputs
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $amount = mt_rand(1, PHP_INT_MAX >> 1);
            $result = formatRupiah($amount);
            $numericPart = substr($result, 3);

            // Verify numeric value is preserved
            $withoutDots = str_replace('.', '', $numericPart);
            $this->assertSame(
                (string) $amount,
                $withoutDots,
                "Numeric value mismatch for random amount: {$amount}, got numeric part: {$numericPart}"
            );

            // Verify dot placement
            $parts = explode('.', $numericPart);
            if (count($parts) > 1) {
                $this->assertMatchesRegularExpression(
                    '/^[1-9]\d{0,2}$/',
                    $parts[0],
                    "First group should be 1-3 digits for random amount: {$amount}, got: {$parts[0]}"
                );
                for ($j = 1; $j < count($parts); $j++) {
                    $this->assertMatchesRegularExpression(
                        '/^\d{3}$/',
                        $parts[$j],
                        "Group {$j} should be exactly 3 digits for random amount: {$amount}, got: {$parts[$j]}"
                    );
                }
            }
        }
    }

    /**
     * Property 4: Output never contains decimal places.
     * Validates: Requirement 6.1
     *
     * @test
     */
    public function outputNeverContainsDecimalPlaces(): void
    {
        // Test edge cases
        foreach ($this->edgeCaseValues() as $amount) {
            $result = formatRupiah($amount);
            // The output should not contain a comma (which would indicate decimal places)
            $this->assertStringNotContainsString(
                ',',
                $result,
                "Output contains comma (decimal separator) for amount: {$amount}, got: {$result}"
            );
        }

        // Test random inputs
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $amount = $this->generateRandomNonNegativeInt();
            $result = formatRupiah($amount);
            $this->assertStringNotContainsString(
                ',',
                $result,
                "Output contains comma (decimal separator) for random amount: {$amount}, got: {$result}"
            );
        }
    }

    /**
     * Property 5: For negative inputs, output is "Rp 0" (treated as invalid per Req 6.5).
     * Validates: Requirement 6.2 (negative treated as zero)
     *
     * @test
     */
    public function negativeInputsProduceRpZero(): void
    {
        // Test specific negative edge cases
        $negativeValues = [-1, -100, -1000, -999999, -PHP_INT_MAX];
        foreach ($negativeValues as $amount) {
            $result = formatRupiah($amount);
            $this->assertSame(
                'Rp 0',
                $result,
                "Negative amount {$amount} should produce 'Rp 0', got: {$result}"
            );
        }

        // Test random negative inputs
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $amount = $this->generateRandomNegativeInt();
            $result = formatRupiah($amount);
            $this->assertSame(
                'Rp 0',
                $result,
                "Random negative amount {$amount} should produce 'Rp 0', got: {$result}"
            );
        }
    }

    /**
     * Property 6: The numeric part after "Rp " contains only digits and dots.
     * Validates: Requirement 6.1
     *
     * @test
     */
    public function numericPartContainsOnlyDigitsAndDots(): void
    {
        // Test edge cases
        foreach ($this->edgeCaseValues() as $amount) {
            $result = formatRupiah($amount);
            $numericPart = substr($result, 3); // Remove "Rp "
            $this->assertMatchesRegularExpression(
                '/^[0-9.]+$/',
                $numericPart,
                "Numeric part contains invalid characters for amount: {$amount}, got: '{$numericPart}'"
            );
        }

        // Test random non-negative inputs
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $amount = $this->generateRandomNonNegativeInt();
            $result = formatRupiah($amount);
            $numericPart = substr($result, 3);
            $this->assertMatchesRegularExpression(
                '/^[0-9.]+$/',
                $numericPart,
                "Numeric part contains invalid characters for random amount: {$amount}, got: '{$numericPart}'"
            );
        }

        // Test negative inputs (should produce "0" as numeric part)
        for ($i = 0; $i < 50; $i++) {
            $amount = $this->generateRandomNegativeInt();
            $result = formatRupiah($amount);
            $numericPart = substr($result, 3);
            $this->assertMatchesRegularExpression(
                '/^[0-9.]+$/',
                $numericPart,
                "Numeric part contains invalid characters for negative amount: {$amount}, got: '{$numericPart}'"
            );
        }
    }

    /**
     * Combined property: ALL properties hold simultaneously for every input.
     * This catches any interactions between properties that individual tests might miss.
     *
     * @test
     */
    public function allRupiahPropertiesHoldSimultaneously(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $amount = $this->generateRandomNonNegativeInt();
            $result = formatRupiah($amount);

            // Property 1: Starts with "Rp "
            $this->assertStringStartsWith(
                'Rp ',
                $result,
                "Combined check - missing prefix for amount: {$amount}"
            );

            // Property 2: Zero check
            if ($amount === 0) {
                $this->assertSame(
                    'Rp 0',
                    $result,
                    "Combined check - zero should produce 'Rp 0'"
                );
            }

            // Property 3: Numeric value preserved (for positive amounts)
            if ($amount > 0) {
                $numericPart = substr($result, 3);
                $withoutDots = str_replace('.', '', $numericPart);
                $this->assertSame(
                    (string) $amount,
                    $withoutDots,
                    "Combined check - numeric value mismatch for amount: {$amount}"
                );
            }

            // Property 4: No commas (no decimal places)
            $this->assertStringNotContainsString(
                ',',
                $result,
                "Combined check - contains comma for amount: {$amount}"
            );

            // Property 6: Numeric part is only digits and dots
            $numericPart = substr($result, 3);
            $this->assertMatchesRegularExpression(
                '/^[0-9.]+$/',
                $numericPart,
                "Combined check - invalid chars in numeric part for amount: {$amount}"
            );
        }
    }
}
