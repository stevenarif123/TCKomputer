<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/security.php';

use PHPUnit\Framework\TestCase;

/**
 * Property-Based Test for isRateLimited() and retryAfterSeconds().
 *
 * **Validates: Requirements 11.2, 11.7, 11.8**
 *
 * Property 5: Rate-limit decision is a pure threshold.
 * isRateLimited is true iff failedCount >= maxAttempts AND oldestAge < window.
 * retryAfterSeconds is always in [0, window].
 * Zero failures can never be in a limited state.
 */
class RateLimitDecisionPropertyTest extends TestCase
{
    private const ITERATIONS = 500;

    /**
     * Property: isRateLimited returns true iff the two conditions hold.
     * Validates: Requirements 11.2
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function isRateLimitedMatchesThresholdPredicate(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $failed     = mt_rand(0, 20);
            $maxAttempts = mt_rand(1, 10);
            $window     = mt_rand(60, 3600);
            $oldestAge  = mt_rand(0, $window * 2); // may be inside or outside window

            $result = isRateLimited($failed, $maxAttempts, $window, $oldestAge);

            $expected = ($failed >= $maxAttempts) && ($oldestAge < $window);

            $this->assertSame(
                $expected,
                $result,
                "isRateLimited($failed, $maxAttempts, $window, $oldestAge) should be " . ($expected ? 'true' : 'false') . " (iter $i)"
            );
        }
    }

    /**
     * Property: Zero failed attempts can never be rate-limited (Req 11.8).
     * Validates: Requirement 11.8
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function zeroFailuresIsNeverRateLimited(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $maxAttempts = mt_rand(1, 20);
            $window      = mt_rand(60, 3600);
            $oldestAge   = mt_rand(0, $window);

            $this->assertFalse(
                isRateLimited(0, $maxAttempts, $window, $oldestAge),
                "Zero failures must never be rate-limited (iter $i)"
            );
        }
    }

    /**
     * Property: retryAfterSeconds is always in [0, window].
     * Validates: Requirement 11.7
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function retryAfterSecondsIsAlwaysBounded(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $window    = mt_rand(1, 3600);
            $oldestAge = mt_rand(0, $window * 2); // may exceed window

            $retry = retryAfterSeconds($oldestAge, $window);

            $this->assertIsInt($retry, "retryAfterSeconds must return int (iter $i)");
            $this->assertGreaterThanOrEqual(0,       $retry, "retryAfterSeconds must be >= 0 (iter $i)");
            $this->assertLessThanOrEqual($window, $retry, "retryAfterSeconds must be <= window (iter $i)");
        }
    }

    /**
     * Property: retryAfterSeconds == max(0, window - oldestAge) for typical cases.
     * Validates: Requirement 11.7
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function retryAfterSecondsMatchesFormula(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $window    = mt_rand(1, 3600);
            $oldestAge = mt_rand(0, $window * 2);

            $expected = max(0, min($window, $window - $oldestAge));
            $actual   = retryAfterSeconds($oldestAge, $window);

            $this->assertSame($expected, $actual, "retryAfterSeconds formula mismatch (iter $i)");
        }
    }

    /**
     * Property: buildRateLimitKey produces the same output for the same inputs.
     * Validates: determinism / purity
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function rateLimitKeyIsDeterministic(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $action     = ['login', 'register'][mt_rand(0, 1)];
            $identifier = 'user' . mt_rand(1, 9999);
            $ip         = mt_rand(1, 255) . '.' . mt_rand(0, 255) . '.' . mt_rand(0, 255) . '.' . mt_rand(0, 255);

            $key1 = buildRateLimitKey($action, $identifier, $ip);
            $key2 = buildRateLimitKey($action, $identifier, $ip);

            $this->assertSame($key1, $key2, "buildRateLimitKey must be deterministic (iter $i)");
        }
    }

    /**
     * Property: Different identifiers or IPs produce different keys.
     * Validates: Requirement 11.6 (scoped by action + identifier + IP)
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function differentIdentifiersProduceDifferentKeys(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $action = 'login';
            $ip     = '127.0.0.1';
            $id1    = 'user' . mt_rand(1, 4999);
            $id2    = 'user' . mt_rand(5000, 9999);

            $key1 = buildRateLimitKey($action, $id1, $ip);
            $key2 = buildRateLimitKey($action, $id2, $ip);

            $this->assertNotSame($key1, $key2, "Different identifiers must produce different keys (iter $i)");
        }
    }
}
