<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/analytics.php';

use PHPUnit\Framework\TestCase;

/**
 * Property-Based Test for computeMargin, computeMarginPercent,
 * computeAov, and computeCancellationRate.
 *
 * **Validates: Requirements 6.3, 6.4, 6.6, 6.7**
 *
 * Property 3: Margin is never fabricated.
 * Property 4: AOV and cancellation rate are total-safe.
 */
class MarginPropertyTest extends TestCase
{
    private const ITERATIONS = 500;

    // ── computeMargin ────────────────────────────────────────────────────────

    /**
     * Property: computeMargin(revenue, cost) == revenue - cost exactly.
     * Validates: Requirement 6.3
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function marginEqualsRevenueMinusCost(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $revenue = mt_rand(0, 100_000_000);
            $cost    = mt_rand(0, 100_000_000);

            $this->assertSame(
                $revenue - $cost,
                computeMargin($revenue, $cost),
                "computeMargin must equal revenue-cost (iter $i)"
            );
        }
    }

    // ── computeMarginPercent ─────────────────────────────────────────────────

    /**
     * Property: computeMarginPercent returns 0.0 exactly when revenue == 0.
     * Validates: Requirement 6.4 (no division by zero)
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function marginPercentIsZeroWhenRevenueIsZero(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $cost = mt_rand(0, 100_000_000);
            $result = computeMarginPercent(0, $cost);
            $this->assertSame(0.0, $result, "computeMarginPercent must be 0.0 when revenue=0 (iter $i, cost=$cost)");
        }
    }

    /**
     * Property: computeMarginPercent returns (revenue-cost)/revenue*100 when revenue > 0.
     * Validates: Requirement 6.4
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function marginPercentMatchesFormula(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $revenue = mt_rand(1, 100_000_000);
            $cost    = mt_rand(0, 100_000_000);

            $expected = round(($revenue - $cost) / $revenue * 100, 2);
            $actual   = computeMarginPercent($revenue, $cost);

            $this->assertEqualsWithDelta(
                $expected,
                $actual,
                0.001,
                "computeMarginPercent formula mismatch (iter $i, revenue=$revenue, cost=$cost)"
            );
        }
    }

    // ── computeAov ───────────────────────────────────────────────────────────

    /**
     * Property: computeAov(revenue, 0) == 0 always (no division by zero).
     * Validates: Requirement 6.6
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function aovIsZeroWhenOrderCountIsZero(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $revenue = mt_rand(0, 100_000_000);
            $this->assertSame(0, computeAov($revenue, 0), "AOV must be 0 when orderCount=0 (iter $i)");
        }
    }

    /**
     * Property: computeAov == intdiv(revenue, orderCount) when orderCount > 0.
     * Validates: Requirement 6.6
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function aovEqualsIntdivWhenCountPositive(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $revenue    = mt_rand(0, 100_000_000);
            $orderCount = mt_rand(1, 10000);

            $expected = intdiv($revenue, $orderCount);
            $actual   = computeAov($revenue, $orderCount);

            $this->assertSame($expected, $actual, "AOV must equal intdiv(revenue, orderCount) (iter $i)");
        }
    }

    // ── computeCancellationRate ───────────────────────────────────────────────

    /**
     * Property: computeCancellationRate(c, 0) == 0.0 always.
     * Validates: Requirement 6.7
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function cancellationRateIsZeroWhenTotalIsZero(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $cancelled = mt_rand(0, 1000);
            $this->assertSame(0.0, computeCancellationRate($cancelled, 0), "Cancellation rate must be 0.0 when total=0 (iter $i)");
        }
    }

    /**
     * Property: computeCancellationRate is in [0,1] when 0 <= c <= total.
     * Validates: Requirement 6.7
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function cancellationRateIsInUnitIntervalWhenConsistent(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $total     = mt_rand(1, 10000);
            $cancelled = mt_rand(0, $total);

            $rate = computeCancellationRate($cancelled, $total);

            $this->assertIsFloat($rate, "Cancellation rate must be float (iter $i)");
            $this->assertGreaterThanOrEqual(0.0, $rate, "Cancellation rate must be >= 0.0 (iter $i)");
            $this->assertLessThanOrEqual(1.0,   $rate, "Cancellation rate must be <= 1.0 (iter $i)");
        }
    }

    /**
     * Property: computeCancellationRate == cancelled/total when total > 0.
     * Validates: Requirement 6.7
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function cancellationRateMatchesFormula(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $total     = mt_rand(1, 10000);
            $cancelled = mt_rand(0, $total);

            $expected = $cancelled / $total;
            $actual   = computeCancellationRate($cancelled, $total);

            $this->assertEqualsWithDelta(
                $expected,
                $actual,
                1e-10,
                "Cancellation rate formula mismatch (iter $i)"
            );
        }
    }
}
