<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/helpers.php';

use PHPUnit\Framework\TestCase;

/**
 * Property-Based Test: CSRF Parity Regression Guard for Buyer Endpoints.
 *
 * **Validates: Requirements 12.1, 12.3, 12.5**
 *
 * Property 9: CSRF parity (regression guard).
 * Reuses the CSRFTokenPropertyTest invariants applied to buyer endpoints:
 * - A matching token is accepted (validateCSRFToken returns true).
 * - Any non-matching or empty token is rejected (validateCSRFToken returns false).
 * - Validation fails when no session token exists.
 *
 * This test guards against regressions where buyer auth actions might bypass
 * CSRF validation or use a weaker comparison.
 */
class BuyerCSRFParityPropertyTest extends TestCase
{
    private const ITERATIONS = 500;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
    }

    /**
     * Property: A token generated for the session always passes validation.
     * Validates: Requirement 12.4 (matching token accepted)
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function matchingTokenAlwaysAccepted(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $token = generateCSRFToken();
            $this->assertTrue(
                validateCSRFToken($token),
                "A freshly generated CSRF token must be accepted by validateCSRFToken (iter $i)"
            );
        }
    }

    /**
     * Property: A non-matching token (any modification) is always rejected.
     * Validates: Requirement 12.3
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function nonMatchingTokenAlwaysRejected(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $valid = generateCSRFToken();

            // Flip one character to create a non-matching token
            $chars  = str_split($valid);
            $pos    = mt_rand(0, strlen($valid) - 1);
            $hex    = '0123456789abcdef';
            do {
                $chars[$pos] = $hex[mt_rand(0, 15)];
            } while ($chars[$pos] === $valid[$pos]);
            $invalid = implode('', $chars);

            $this->assertFalse(
                validateCSRFToken($invalid),
                "Modified CSRF token must be rejected (iter $i, pos=$pos)"
            );
        }
    }

    /**
     * Property: Empty or whitespace tokens are always rejected.
     * Validates: Requirement 12.2
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function emptyTokenAlwaysRejected(): void
    {
        // With a session token present
        generateCSRFToken();

        $empties = ['', ' ', "\t", "\n", "0", "\0"];
        foreach ($empties as $empty) {
            $this->assertFalse(
                validateCSRFToken($empty),
                "Empty/whitespace-like token must be rejected: " . var_export($empty, true)
            );
        }

        // Over multiple iterations
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            generateCSRFToken();
            $this->assertFalse(
                validateCSRFToken(''),
                "Empty string must always fail CSRF validation (iter $i)"
            );
        }
    }

    /**
     * Property: Validation fails when no session token exists.
     * Validates: Requirement 12.5
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function validationFailsWhenNoSessionToken(): void
    {
        // Ensure no token in session
        unset($_SESSION['csrf_token']);

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            // Generate a random hex string (not stored in session)
            $randomToken = bin2hex(random_bytes(32));

            // Unset again to be sure (loop may not reset it)
            unset($_SESSION['csrf_token']);

            $this->assertFalse(
                validateCSRFToken($randomToken),
                "Any token must fail validation when no session token exists (iter $i)"
            );
        }
    }

    /**
     * Property: CSRF token is constant for the session (no regeneration unless forced).
     * Ensures buyer forms sending the same token across a session still validate.
     * Validates: Requirement 12.4
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function tokenRemainsValidAcrossMultipleValidations(): void
    {
        $token = generateCSRFToken();

        // Simulate the buyer form submitting the same token multiple times in a session
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $this->assertTrue(
                validateCSRFToken($token),
                "Token must remain valid for the lifetime of the session (iter $i)"
            );
        }
    }

    /**
     * Property: A token from a different session cannot validate the current session.
     * Validates: Requirement 12.3
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function tokenFromDifferentSessionIsRejected(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            // "Old" token (simulating a token from a previous or different session)
            $oldToken = bin2hex(random_bytes(32));

            // Generate a new token for the current session
            generateCSRFToken(true); // force regeneration

            // Old token must not validate
            $this->assertFalse(
                validateCSRFToken($oldToken),
                "Token from a different session must be rejected (iter $i)"
            );
        }
    }
}
