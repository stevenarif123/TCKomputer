<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/admin-auth.php';

use PHPUnit\Framework\TestCase;

/**
 * Property-Based Test for Admin Session Guard (isAdminLoggedIn)
 *
 * **Validates: Requirements 7.1**
 *
 * Property 7: Admin Session Guard
 * For ANY session state, isAdminLoggedIn() must correctly determine
 * whether a valid admin session exists:
 * - Returns false when $_SESSION is empty
 * - Returns false when $_SESSION['admin_id'] is not set
 * - Returns false when $_SESSION['admin_id'] is empty/falsy (0, '', null, false)
 * - Returns true when $_SESSION['admin_id'] is a positive integer
 * - Returns true when $_SESSION['admin_id'] is a non-empty value
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class AdminSessionGuardPropertyTest extends TestCase
{
    /**
     * Number of random iterations per test method.
     */
    private const ITERATIONS = 200;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    /**
     * Property: isAdminLoggedIn() returns false when $_SESSION is empty.
     *
     * @test
     */
    public function returnsFalseWhenSessionIsEmpty(): void
    {
        $_SESSION = [];

        $this->assertFalse(
            isAdminLoggedIn(),
            'isAdminLoggedIn() should return false when $_SESSION is empty'
        );
    }

    /**
     * Property: isAdminLoggedIn() returns false when $_SESSION['admin_id'] is not set.
     *
     * @test
     */
    public function returnsFalseWhenAdminIdIsNotSet(): void
    {
        // Test with various other session keys but no admin_id
        $otherKeys = ['user_id', 'cart', 'flash', 'csrf_token', 'username', 'role'];

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $_SESSION = [];

            // Add random other session keys
            $numKeys = mt_rand(1, 4);
            for ($k = 0; $k < $numKeys; $k++) {
                $key = $otherKeys[mt_rand(0, count($otherKeys) - 1)];
                $_SESSION[$key] = 'value_' . mt_rand(1, 1000);
            }

            // Ensure admin_id is NOT set
            unset($_SESSION['admin_id']);

            $this->assertFalse(
                isAdminLoggedIn(),
                'isAdminLoggedIn() should return false when admin_id is not set. Session: ' . json_encode(array_keys($_SESSION))
            );
        }
    }

    /**
     * Property: isAdminLoggedIn() returns false when $_SESSION['admin_id'] is empty/falsy.
     *
     * @test
     */
    public function returnsFalseWhenAdminIdIsEmpty(): void
    {
        $falsyValues = [0, '', null, false, '0', []];

        foreach ($falsyValues as $value) {
            $_SESSION['admin_id'] = $value;

            $this->assertFalse(
                isAdminLoggedIn(),
                'isAdminLoggedIn() should return false when admin_id is: ' . json_encode($value)
            );
        }
    }

    /**
     * Property: isAdminLoggedIn() returns true when $_SESSION['admin_id'] is a positive integer.
     *
     * @test
     */
    public function returnsTrueWhenAdminIdIsPositiveInteger(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $adminId = mt_rand(1, 999999);
            $_SESSION['admin_id'] = $adminId;

            $this->assertTrue(
                isAdminLoggedIn(),
                'isAdminLoggedIn() should return true when admin_id is positive integer: ' . $adminId
            );
        }
    }

    /**
     * Property: isAdminLoggedIn() returns true when $_SESSION['admin_id'] is a non-empty value.
     *
     * @test
     */
    public function returnsTrueWhenAdminIdIsNonEmptyValue(): void
    {
        $nonEmptyValues = [];

        // Generate random positive integers
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $type = mt_rand(0, 2);
            switch ($type) {
                case 0: // Positive integers
                    $nonEmptyValues[] = mt_rand(1, 999999);
                    break;
                case 1: // Non-empty strings
                    $nonEmptyValues[] = 'admin_' . mt_rand(1, 1000);
                    break;
                case 2: // String representations of positive integers
                    $nonEmptyValues[] = (string) mt_rand(1, 999999);
                    break;
            }
        }

        foreach ($nonEmptyValues as $value) {
            $_SESSION['admin_id'] = $value;

            $this->assertTrue(
                isAdminLoggedIn(),
                'isAdminLoggedIn() should return true when admin_id is non-empty: ' . json_encode($value)
            );
        }
    }

    /**
     * Property: Various random empty/falsy values for admin_id all fail.
     *
     * @test
     */
    public function randomFalsyValuesAllFail(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $type = mt_rand(0, 4);
            switch ($type) {
                case 0:
                    $_SESSION['admin_id'] = null;
                    break;
                case 1:
                    $_SESSION['admin_id'] = 0;
                    break;
                case 2:
                    $_SESSION['admin_id'] = '';
                    break;
                case 3:
                    $_SESSION['admin_id'] = false;
                    break;
                case 4:
                    $_SESSION['admin_id'] = '0';
                    break;
            }

            $this->assertFalse(
                isAdminLoggedIn(),
                'isAdminLoggedIn() should return false for falsy admin_id: ' . json_encode($_SESSION['admin_id'])
            );
        }
    }

    /**
     * Property: Various random non-empty values pass.
     *
     * @test
     */
    public function randomNonEmptyValuesPass(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $type = mt_rand(0, 3);
            switch ($type) {
                case 0: // Positive integers
                    $_SESSION['admin_id'] = mt_rand(1, 999999);
                    break;
                case 1: // Non-empty strings
                    $length = mt_rand(1, 20);
                    $str = '';
                    for ($c = 0; $c < $length; $c++) {
                        $str .= chr(mt_rand(97, 122)); // a-z
                    }
                    $_SESSION['admin_id'] = $str;
                    break;
                case 2: // String number (positive)
                    $_SESSION['admin_id'] = (string) mt_rand(1, 999999);
                    break;
                case 3: // Large integers
                    $_SESSION['admin_id'] = mt_rand(100000, PHP_INT_MAX >> 1);
                    break;
            }

            $this->assertTrue(
                isAdminLoggedIn(),
                'isAdminLoggedIn() should return true for non-empty admin_id: ' . json_encode($_SESSION['admin_id'])
            );
        }
    }
}
