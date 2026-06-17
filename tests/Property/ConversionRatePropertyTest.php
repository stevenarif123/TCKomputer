<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/analytics.php';

use PHPUnit\Framework\TestCase;

/**
 * Property-Based Test for computeConversionRates().
 *
 * **Validates: Requirements 4.4, 4.5, 4.6, 4.7**
 *
 * Property 1: Funnel rates are bounded.
 * For all consistent non-negative inputs (registrations <= visits,
 * purchases <= registrations), each returned rate is a float in [0,1].
 * Zero denominators always yield exactly 0.0.
 */
class ConversionRatePropertyTest extends TestCase
{
    private const ITERATIONS = 500;

    /**
     * Property: All rates are floats in [0.0, 1.0] for consistent inputs.
     * Validates: Requirements 4.4, 4.5, 4.6, 4.7
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function allRatesAreBoundedForConsistentInputs(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $visits        = mt_rand(0, 10000);
            $registrations = mt_rand(0, $visits);
            $purchases     = mt_rand(0, $registrations);

            $rates = computeConversionRates($visits, $registrations, $purchases);

            $this->assertIsFloat($rates['registration_rate'], "registration_rate must be float (iter $i)");
            $this->assertIsFloat($rates['purchase_rate'],     "purchase_rate must be float (iter $i)");
            $this->assertIsFloat($rates['overall_rate'],      "overall_rate must be float (iter $i)");

            $this->assertGreaterThanOrEqual(0.0, $rates['registration_rate'], "registration_rate >= 0.0 (iter $i)");
            $this->assertGreaterThanOrEqual(0.0, $rates['purchase_rate'],     "purchase_rate >= 0.0 (iter $i)");
            $this->assertGreaterThanOrEqual(0.0, $rates['overall_rate'],      "overall_rate >= 0.0 (iter $i)");

            $this->assertLessThanOrEqual(1.0, $rates['registration_rate'], "registration_rate <= 1.0 (iter $i)");
            $this->assertLessThanOrEqual(1.0, $rates['purchase_rate'],     "purchase_rate <= 1.0 (iter $i)");
            $this->assertLessThanOrEqual(1.0, $rates['overall_rate'],      "overall_rate <= 1.0 (iter $i)");
        }
    }

    /**
     * Property: When visits == 0, registration_rate and overall_rate are exactly 0.0.
     * Validates: Requirements 4.4, 4.6
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function zeroVisitsYieldsZeroRates(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $registrations = mt_rand(0, 1000);
            $purchases     = mt_rand(0, $registrations);

            $rates = computeConversionRates(0, $registrations, $purchases);

            $this->assertSame(0.0, $rates['registration_rate'], "registration_rate must be 0.0 when visits=0 (iter $i)");
            $this->assertSame(0.0, $rates['overall_rate'],      "overall_rate must be 0.0 when visits=0 (iter $i)");
        }
    }

    /**
     * Property: When registrations == 0, purchase_rate is exactly 0.0.
     * Validates: Requirement 4.5
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function zeroRegistrationsYieldsZeroPurchaseRate(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $visits    = mt_rand(0, 10000);
            $purchases = mt_rand(0, 100);

            $rates = computeConversionRates($visits, 0, $purchases);

            $this->assertSame(0.0, $rates['purchase_rate'], "purchase_rate must be 0.0 when registrations=0 (iter $i)");
        }
    }

    /**
     * Property: Negative inputs are treated as 0 (max(0, n)).
     * Validates: Requirement 4.7 (inputs treated as max(0,n))
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function negativeInputsAreClampedToZero(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $v = mt_rand(-10000, -1);
            $r = mt_rand(-10000, -1);
            $p = mt_rand(-10000, -1);

            $rates = computeConversionRates($v, $r, $p);

            // All denominators are 0 after clamping → all rates 0.0
            $this->assertSame(0.0, $rates['registration_rate'], "negative inputs should clamp to 0 (iter $i)");
            $this->assertSame(0.0, $rates['purchase_rate'],     "negative inputs should clamp to 0 (iter $i)");
            $this->assertSame(0.0, $rates['overall_rate'],      "negative inputs should clamp to 0 (iter $i)");
        }
    }

    /**
     * Property: Return array always has exactly the three rate keys.
     * Validates: Requirement 4.4, 4.5, 4.6
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function returnArrayAlwaysHasThreeRateKeys(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $v = mt_rand(0, 10000);
            $r = mt_rand(0, $v);
            $p = mt_rand(0, $r);

            $rates = computeConversionRates($v, $r, $p);

            $this->assertArrayHasKey('registration_rate', $rates);
            $this->assertArrayHasKey('purchase_rate',     $rates);
            $this->assertArrayHasKey('overall_rate',      $rates);
        }
    }
}
