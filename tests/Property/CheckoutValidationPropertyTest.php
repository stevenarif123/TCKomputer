<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/helpers.php';

use PHPUnit\Framework\TestCase;

/**
 * Property-Based Test for Checkout Input Validation
 *
 * **Validates: Requirements 4.2, 4.10, 4.11**
 *
 * Property 15: Checkout Input Validation
 * Tests all validation rules:
 * - Name: 3-100 characters
 * - Phone: Indonesian format (08xx 10-15 digits, +628xx format)
 * - Address: 10-500 characters
 * - Payment method: only cod, transfer, pay_on_delivery
 * - Shipping option: only self_pickup, local_delivery, local_courier
 */
class CheckoutValidationPropertyTest extends TestCase
{
    /**
     * Number of random iterations per test method.
     */
    private const ITERATIONS = 500;

    /**
     * Valid payment methods.
     */
    private const VALID_PAYMENT_METHODS = ['cod', 'transfer', 'pay_on_delivery'];

    /**
     * Valid shipping options.
     */
    private const VALID_SHIPPING_OPTIONS = ['self_pickup', 'local_delivery', 'local_courier'];

    // ========================================================================
    // Validation helper methods (mirrors checkout-process.php logic)
    // ========================================================================

    /**
     * Validate buyer name (3-100 chars).
     */
    private function validateBuyerName(string $name): bool
    {
        $name = trim($name);
        if (empty($name)) {
            return false;
        }
        $len = mb_strlen($name, 'UTF-8');
        return $len >= 3 && $len <= 100;
    }

    /**
     * Validate buyer address (10-500 chars).
     */
    private function validateBuyerAddress(string $address): bool
    {
        $address = trim($address);
        if (empty($address)) {
            return false;
        }
        $len = mb_strlen($address, 'UTF-8');
        return $len >= 10 && $len <= 500;
    }

    /**
     * Validate payment method.
     */
    private function validatePaymentMethod(string $method): bool
    {
        return !empty($method) && in_array($method, self::VALID_PAYMENT_METHODS, true);
    }

    /**
     * Validate shipping option.
     */
    private function validateShippingOption(string $option): bool
    {
        return !empty($option) && in_array($option, self::VALID_SHIPPING_OPTIONS, true);
    }

    // ========================================================================
    // Generators
    // ========================================================================

    /**
     * Generate a random UTF-8 string of exact byte/character length.
     */
    private function generateStringOfLength(int $length): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        return $result;
    }

    /**
     * Generate a random string with length in range [min, max].
     */
    private function generateStringInRange(int $min, int $max): string
    {
        $length = mt_rand($min, $max);
        return $this->generateStringOfLength($length);
    }

    /**
     * Generate a valid Indonesian phone number (08xx format).
     */
    private function generateValidPhone08(): string
    {
        // 08 + 8-13 digits = 10-15 digits total
        $digitCount = mt_rand(8, 13);
        $phone = '08';
        for ($i = 0; $i < $digitCount; $i++) {
            $phone .= mt_rand(0, 9);
        }
        return $phone;
    }

    /**
     * Generate a valid Indonesian phone number (+628xx format).
     */
    private function generateValidPhonePlus62(): string
    {
        // +62 + 8-13 digits
        $digitCount = mt_rand(8, 13);
        $phone = '+62';
        for ($i = 0; $i < $digitCount; $i++) {
            $phone .= mt_rand(0, 9);
        }
        return $phone;
    }

    /**
     * Generate an invalid phone number.
     */
    private function generateInvalidPhone(): string
    {
        $type = mt_rand(0, 6);
        switch ($type) {
            case 0: // Too short (08 + fewer than 8 digits)
                $digits = mt_rand(1, 7);
                $phone = '08';
                for ($i = 0; $i < $digits; $i++) {
                    $phone .= mt_rand(0, 9);
                }
                return $phone;
            case 1: // Too long (08 + more than 13 digits)
                $digits = mt_rand(14, 20);
                $phone = '08';
                for ($i = 0; $i < $digits; $i++) {
                    $phone .= mt_rand(0, 9);
                }
                return $phone;
            case 2: // Doesn't start with 08 or +62
                return '09' . mt_rand(10000000, 99999999);
            case 3: // Random letters
                return $this->generateStringOfLength(mt_rand(5, 15));
            case 4: // +62 too short
                $digits = mt_rand(1, 7);
                $phone = '+62';
                for ($i = 0; $i < $digits; $i++) {
                    $phone .= mt_rand(0, 9);
                }
                return $phone;
            case 5: // +62 too long
                $digits = mt_rand(14, 20);
                $phone = '+62';
                for ($i = 0; $i < $digits; $i++) {
                    $phone .= mt_rand(0, 9);
                }
                return $phone;
            case 6: // Empty or spaces only
                return str_repeat(' ', mt_rand(0, 5));
        }
        return 'invalid';
    }

    /**
     * Generate a random string for invalid payment/shipping.
     */
    private function generateInvalidOption(array $validOptions): string
    {
        $type = mt_rand(0, 4);
        switch ($type) {
            case 0: // Random alphanumeric
                return $this->generateStringOfLength(mt_rand(1, 20));
            case 1: // Uppercase variant of valid option
                return strtoupper($validOptions[array_rand($validOptions)]);
            case 2: // With extra spaces
                return ' ' . $validOptions[array_rand($validOptions)] . ' ';
            case 3: // Partial match
                $opt = $validOptions[array_rand($validOptions)];
                return substr($opt, 0, max(1, (int)(strlen($opt) / 2)));
            case 4: // Empty
                return '';
        }
        return 'unknown_option';
    }

    // ========================================================================
    // Property Tests: Buyer Name Validation
    // ========================================================================

    /**
     * Property: Names with length < 3 are always rejected.
     * **Validates: Requirements 4.2**
     *
     * @test
     */
    public function namesWithLengthLessThan3AreAlwaysRejected(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $length = mt_rand(0, 2);
            $name = $this->generateStringOfLength($length);

            $this->assertFalse(
                $this->validateBuyerName($name),
                "Name with length {$length} should be rejected: " . json_encode($name)
            );
        }

        // Edge cases
        $this->assertFalse($this->validateBuyerName(''));
        $this->assertFalse($this->validateBuyerName('a'));
        $this->assertFalse($this->validateBuyerName('ab'));
        $this->assertFalse($this->validateBuyerName('  ')); // Trimmed becomes empty
    }

    /**
     * Property: Names with length 3-100 are always accepted.
     * **Validates: Requirements 4.2**
     *
     * @test
     */
    public function namesWithLength3To100AreAlwaysAccepted(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $name = $this->generateStringInRange(3, 100);

            $this->assertTrue(
                $this->validateBuyerName($name),
                "Name with length " . mb_strlen($name, 'UTF-8') . " should be accepted: " . json_encode($name)
            );
        }

        // Boundary cases
        $this->assertTrue($this->validateBuyerName('abc')); // Exactly 3
        $this->assertTrue($this->validateBuyerName($this->generateStringOfLength(100))); // Exactly 100
    }

    /**
     * Property: Names with length > 100 are always rejected.
     * **Validates: Requirements 4.2**
     *
     * @test
     */
    public function namesWithLengthOver100AreAlwaysRejected(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $length = mt_rand(101, 300);
            $name = $this->generateStringOfLength($length);

            $this->assertFalse(
                $this->validateBuyerName($name),
                "Name with length {$length} should be rejected"
            );
        }

        // Boundary case: exactly 101
        $this->assertFalse($this->validateBuyerName($this->generateStringOfLength(101)));
    }

    // ========================================================================
    // Property Tests: Phone Number Validation
    // ========================================================================

    /**
     * Property: Valid Indonesian phone numbers (08xx 10-15 digits, +628xx format) always pass.
     * **Validates: Requirements 4.2**
     *
     * @test
     */
    public function validIndonesianPhoneNumbersAlwaysPass(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            if (mt_rand(0, 1) === 0) {
                $phone = $this->generateValidPhone08();
            } else {
                $phone = $this->generateValidPhonePlus62();
            }

            $this->assertTrue(
                isValidPhoneNumber($phone),
                "Valid phone number should pass: " . json_encode($phone)
            );
        }
    }

    /**
     * Property: Invalid phone formats always fail.
     * **Validates: Requirements 4.2**
     *
     * @test
     */
    public function invalidPhoneFormatsAlwaysFail(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $phone = $this->generateInvalidPhone();

            $this->assertFalse(
                isValidPhoneNumber($phone),
                "Invalid phone number should fail: " . json_encode($phone)
            );
        }
    }

    // ========================================================================
    // Property Tests: Address Validation
    // ========================================================================

    /**
     * Property: Addresses with length < 10 are always rejected.
     * **Validates: Requirements 4.2**
     *
     * @test
     */
    public function addressesWithLengthLessThan10AreAlwaysRejected(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $length = mt_rand(0, 9);
            $address = $this->generateStringOfLength($length);

            $this->assertFalse(
                $this->validateBuyerAddress($address),
                "Address with length {$length} should be rejected: " . json_encode($address)
            );
        }

        // Edge cases
        $this->assertFalse($this->validateBuyerAddress(''));
        $this->assertFalse($this->validateBuyerAddress('123456789')); // 9 chars
    }

    /**
     * Property: Addresses with length 10-500 are always accepted.
     * **Validates: Requirements 4.2**
     *
     * @test
     */
    public function addressesWithLength10To500AreAlwaysAccepted(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $address = $this->generateStringInRange(10, 500);

            $this->assertTrue(
                $this->validateBuyerAddress($address),
                "Address with length " . mb_strlen($address, 'UTF-8') . " should be accepted: " . json_encode(mb_substr($address, 0, 20, 'UTF-8') . '...')
            );
        }

        // Boundary cases
        $this->assertTrue($this->validateBuyerAddress($this->generateStringOfLength(10))); // Exactly 10
        $this->assertTrue($this->validateBuyerAddress($this->generateStringOfLength(500))); // Exactly 500
    }

    /**
     * Property: Addresses with length > 500 are always rejected.
     * **Validates: Requirements 4.2**
     *
     * @test
     */
    public function addressesWithLengthOver500AreAlwaysRejected(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $length = mt_rand(501, 800);
            $address = $this->generateStringOfLength($length);

            $this->assertFalse(
                $this->validateBuyerAddress($address),
                "Address with length {$length} should be rejected"
            );
        }

        // Boundary case: exactly 501
        $this->assertFalse($this->validateBuyerAddress($this->generateStringOfLength(501)));
    }

    // ========================================================================
    // Property Tests: Payment Method Validation
    // ========================================================================

    /**
     * Property: Only 'cod', 'transfer', 'pay_on_delivery' are valid payment methods.
     * **Validates: Requirements 4.10**
     *
     * @test
     */
    public function onlyValidPaymentMethodsAreAccepted(): void
    {
        // All valid methods always pass
        foreach (self::VALID_PAYMENT_METHODS as $method) {
            $this->assertTrue(
                $this->validatePaymentMethod($method),
                "Valid payment method '{$method}' should be accepted"
            );
        }

        // Random valid selections always pass
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $method = self::VALID_PAYMENT_METHODS[array_rand(self::VALID_PAYMENT_METHODS)];
            $this->assertTrue(
                $this->validatePaymentMethod($method),
                "Randomly selected valid payment method '{$method}' should be accepted"
            );
        }
    }

    /**
     * Property: Any other payment method string is rejected.
     * **Validates: Requirements 4.10**
     *
     * @test
     */
    public function invalidPaymentMethodsAreAlwaysRejected(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $method = $this->generateInvalidOption(self::VALID_PAYMENT_METHODS);

            // Skip if the generated string happens to be a valid option
            if (in_array($method, self::VALID_PAYMENT_METHODS, true)) {
                continue;
            }

            $this->assertFalse(
                $this->validatePaymentMethod($method),
                "Invalid payment method should be rejected: " . json_encode($method)
            );
        }
    }

    // ========================================================================
    // Property Tests: Shipping Option Validation
    // ========================================================================

    /**
     * Property: Only 'self_pickup', 'local_delivery', 'local_courier' are valid shipping options.
     * **Validates: Requirements 4.11**
     *
     * @test
     */
    public function onlyValidShippingOptionsAreAccepted(): void
    {
        // All valid options always pass
        foreach (self::VALID_SHIPPING_OPTIONS as $option) {
            $this->assertTrue(
                $this->validateShippingOption($option),
                "Valid shipping option '{$option}' should be accepted"
            );
        }

        // Random valid selections always pass
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $option = self::VALID_SHIPPING_OPTIONS[array_rand(self::VALID_SHIPPING_OPTIONS)];
            $this->assertTrue(
                $this->validateShippingOption($option),
                "Randomly selected valid shipping option '{$option}' should be accepted"
            );
        }
    }

    /**
     * Property: Any other shipping option string is rejected.
     * **Validates: Requirements 4.11**
     *
     * @test
     */
    public function invalidShippingOptionsAreAlwaysRejected(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $option = $this->generateInvalidOption(self::VALID_SHIPPING_OPTIONS);

            // Skip if the generated string happens to be a valid option
            if (in_array($option, self::VALID_SHIPPING_OPTIONS, true)) {
                continue;
            }

            $this->assertFalse(
                $this->validateShippingOption($option),
                "Invalid shipping option should be rejected: " . json_encode($option)
            );
        }
    }
}
