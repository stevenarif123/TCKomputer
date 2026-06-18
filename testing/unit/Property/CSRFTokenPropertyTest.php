<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/helpers.php';

use PHPUnit\Framework\TestCase;

/**
 * Property-Based Test for CSRF Token generation and validation.
 *
 * **Validates: Requirements 14.1, 14.2, 14.3**
 *
 * Property 8: CSRF Protection
 * Tests that generated tokens are validated correctly,
 * and mismatched/missing tokens are rejected.
 */
class CSRFTokenPropertyTest extends TestCase
{
    /**
     * Number of random iterations per test method.
     */
    private const ITERATIONS = 500;

    protected function setUp(): void
    {
        // Ensure a clean session state for each test
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
     * Generate a random hex string of a given length.
     */
    private function generateRandomHexString(int $length = 64): string
    {
        $chars = '0123456789abcdef';
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= $chars[mt_rand(0, 15)];
        }
        return $result;
    }

    /**
     * Generate a random string that is NOT the given token.
     */
    private function generateDifferentToken(string $originalToken): string
    {
        // Strategy: flip a random character in the token
        $modified = $originalToken;
        $pos = mt_rand(0, strlen($modified) - 1);
        $currentChar = $modified[$pos];

        // Pick a different hex character
        $hexChars = '0123456789abcdef';
        do {
            $newChar = $hexChars[mt_rand(0, 15)];
        } while ($newChar === $currentChar);

        $modified[$pos] = $newChar;
        return $modified;
    }

    /**
     * Property: Generated token is always at least 64 hex characters (32 bytes = 64 hex chars).
     * Validates: Requirement 14.1
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function generatedTokenIsAtLeast64HexCharacters(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $token = generateCSRFToken();
            $this->assertGreaterThanOrEqual(
                64,
                strlen($token),
                "Generated CSRF token is shorter than 64 characters (32 bytes). Got length: " . strlen($token)
            );
        }
    }

    /**
     * Property: Generated token contains only hexadecimal characters [0-9a-f].
     * Validates: Requirement 14.1
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function generatedTokenContainsOnlyHexCharacters(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $token = generateCSRFToken();
            $this->assertMatchesRegularExpression(
                '/^[0-9a-f]+$/',
                $token,
                "Generated CSRF token contains non-hex characters: " . $token
            );
        }
    }

    /**
     * Property: A freshly generated token is always validated successfully.
     * generateCSRFToken() stores it in session, then validateCSRFToken() returns true.
     * Validates: Requirement 14.2
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function freshlyGeneratedTokenAlwaysValidates(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $token = generateCSRFToken();
            $this->assertTrue(
                validateCSRFToken($token),
                "Freshly generated token failed validation. Token: " . $token
            );
        }
    }

    /**
     * Property: A randomly modified/different token always fails validation.
     * Validates: Requirement 14.3
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function modifiedTokenAlwaysFailsValidation(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $validToken = generateCSRFToken();
            $invalidToken = $this->generateDifferentToken($validToken);

            $this->assertFalse(
                validateCSRFToken($invalidToken),
                "Modified token was incorrectly validated. Valid: {$validToken}, Submitted: {$invalidToken}"
            );
        }
    }

    /**
     * Property: An empty string always fails validation.
     * Validates: Requirement 14.3
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function emptyStringAlwaysFailsValidation(): void
    {
        // Test with a session token present
        generateCSRFToken();
        $this->assertFalse(
            validateCSRFToken(''),
            "Empty string was incorrectly validated when session token exists"
        );

        // Test without a session token
        unset($_SESSION['csrf_token']);
        $this->assertFalse(
            validateCSRFToken(''),
            "Empty string was incorrectly validated when no session token exists"
        );

        // Test with various empty-like edge cases over iterations
        $emptyVariants = ['', ' ', '0', "\0", "\t", "\n"];
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            generateCSRFToken();
            // Empty string must always fail
            $this->assertFalse(
                validateCSRFToken(''),
                "Empty string was incorrectly validated on iteration {$i}"
            );
        }
    }

    /**
     * Property: Each call to generateCSRFToken() produces a unique token.
     * No two consecutive calls produce the same token.
     * Validates: Requirement 14.1
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function consecutiveTokensAreAlwaysUnique(): void
    {
        $previousToken = generateCSRFToken(true);

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $currentToken = generateCSRFToken(true);
            $this->assertNotEquals(
                $previousToken,
                $currentToken,
                "Two consecutive calls to generateCSRFToken() produced the same token: " . $currentToken
            );
            $previousToken = $currentToken;
        }
    }

    /**
     * Property: After generating a new token, the old token is no longer valid.
     * Validates: Requirement 14.2, 14.3
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function oldTokenIsInvalidatedAfterNewGeneration(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $oldToken = generateCSRFToken(true);
            $newToken = generateCSRFToken(true);

            // Old token must no longer validate
            $this->assertFalse(
                validateCSRFToken($oldToken),
                "Old token still validates after new token generation. Old: {$oldToken}, New: {$newToken}"
            );

            // New token must validate
            $this->assertTrue(
                validateCSRFToken($newToken),
                "New token fails validation after generation. Token: {$newToken}"
            );
        }
    }
}
