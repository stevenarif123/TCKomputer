<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/analytics.php';

use PHPUnit\Framework\TestCase;

/**
 * Property-Based Test for normalizeDateRange().
 *
 * **Validates: Requirements 5.1, 5.2, 5.3, 5.4, 5.5, 5.6, 5.7, 5.8**
 *
 * Property 2: Date range is always well-formed.
 * For any input (garbage, reversed, future, null), normalizeDateRange returns
 * valid Y-m-d dates with start_date <= end_date, span <= maxDays, end <= today.
 */
class DateRangeNormalizationPropertyTest extends TestCase
{
    private const ITERATIONS = 500;

    /** Generate a random date string, possibly invalid. */
    private function randomDateString(): ?string
    {
        $type = mt_rand(0, 5);
        switch ($type) {
            case 0: return null;
            case 1: return '';
            case 2: return 'not-a-date';
            case 3: return '9999-99-99';
            case 4:
                // Future date
                $days = mt_rand(1, 3000);
                return date('Y-m-d', strtotime("+{$days} days"));
            default:
                // Random date in past/present/future
                $days = mt_rand(-1000, 1000);
                return date('Y-m-d', strtotime("{$days} days"));
        }
    }

    /** Check if a string is a valid Y-m-d date. */
    private function isValidDate(string $s): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
            return false;
        }
        $dt = DateTime::createFromFormat('Y-m-d', $s);
        return $dt !== false && $dt->format('Y-m-d') === $s;
    }

    /**
     * Property: Output always contains valid Y-m-d dates.
     * Validates: Requirement 5.1
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function outputAlwaysContainsValidDates(): void
    {
        $now = time();
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $from = $this->randomDateString();
            $to   = $this->randomDateString();

            $range = normalizeDateRange($from, $to, 366, $now);

            $this->assertIsArray($range, "Result must be an array (iter $i)");
            $this->assertArrayHasKey('start_date', $range, "Must have start_date (iter $i)");
            $this->assertArrayHasKey('end_date',   $range, "Must have end_date (iter $i)");

            $this->assertTrue(
                $this->isValidDate($range['start_date']),
                "start_date '{$range['start_date']}' is not a valid Y-m-d date (iter $i)"
            );
            $this->assertTrue(
                $this->isValidDate($range['end_date']),
                "end_date '{$range['end_date']}' is not a valid Y-m-d date (iter $i)"
            );
        }
    }

    /**
     * Property: start_date is always <= end_date.
     * Validates: Requirement 5.2, 5.3
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function startDateIsAlwaysLessThanOrEqualToEndDate(): void
    {
        $now = time();
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $from = $this->randomDateString();
            $to   = $this->randomDateString();

            $range = normalizeDateRange($from, $to, 366, $now);

            $this->assertLessThanOrEqual(
                $range['end_date'],
                $range['start_date'],
                "start_date '{$range['start_date']}' must be <= end_date '{$range['end_date']}' (iter $i)"
            );
        }
    }

    /**
     * Property: Span never exceeds maxDays.
     * Validates: Requirements 5.5, 5.6
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function spanNeverExceedsMaxDays(): void
    {
        $now = time();
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $maxDays = mt_rand(2, 366); // min 2 to ensure at least 1-day fallback works
            $from    = $this->randomDateString();
            $to      = $this->randomDateString();

            $range = normalizeDateRange($from, $to, $maxDays, $now);

            $start = new DateTime($range['start_date']);
            $end   = new DateTime($range['end_date']);
            $span  = (int)$start->diff($end)->days; // days between start and end (exclusive of start)

            // span = diffDays, which must be < maxDays (i.e., at most maxDays-1 days apart)
            $this->assertLessThan(
                $maxDays,
                $span,
                "Span $span days must be < maxDays=$maxDays (iter $i): [{$range['start_date']} .. {$range['end_date']}]"
            );
        }
    }

    /**
     * Property: end_date is never later than today.
     * Validates: Requirements 5.7, 5.8
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function endDateIsNeverInFuture(): void
    {
        $now = time();
        $todayStr = (new DateTime('@' . $now))->setTimezone(new DateTimeZone('Asia/Makassar'))->format('Y-m-d');

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $from = $this->randomDateString();
            $to   = $this->randomDateString();

            $range = normalizeDateRange($from, $to, 366, $now);

            $this->assertLessThanOrEqual(
                $todayStr,
                $range['end_date'],
                "end_date '{$range['end_date']}' must not exceed today '$todayStr' (iter $i)"
            );
        }
    }

    /**
     * Property: Invalid/null/empty inputs fall back to last 30 days.
     * Validates: Requirement 5.4
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function invalidInputsFallBackToLast30Days(): void
    {
        $now = mktime(12, 0, 0, 6, 15, 2025); // fixed reference time
        $tz  = new DateTimeZone('Asia/Makassar');
        $today = (new DateTime('@' . $now))->setTimezone($tz)->format('Y-m-d');
        $expectedStart = (new DateTime('@' . $now))->setTimezone($tz)->modify('-29 days')->format('Y-m-d');

        $invalidCases = [null, '', 'invalid', '99-99-9999', '2025-13-01'];

        foreach ($invalidCases as $bad) {
            $range = normalizeDateRange($bad, $bad, 366, $now);
            $this->assertSame($expectedStart, $range['start_date'], "Fallback start_date mismatch for input: " . var_export($bad, true));
            $this->assertSame($today,         $range['end_date'],   "Fallback end_date mismatch for input: "   . var_export($bad, true));
        }
    }

    /**
     * Property: Reversed valid inputs are swapped so start <= end.
     * Validates: Requirement 5.3
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function reversedValidInputsAreSwapped(): void
    {
        $now = time();
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $daysA = mt_rand(-365, 0);
            $daysB = mt_rand(-365, 0);
            if ($daysA === $daysB) { $daysA--; }

            $a = date('Y-m-d', strtotime("{$daysA} days"));
            $b = date('Y-m-d', strtotime("{$daysB} days"));

            // Force reversed input (later date first)
            $later  = max($a, $b);
            $earlier = min($a, $b);

            $range = normalizeDateRange($later, $earlier, 366, $now);

            $this->assertLessThanOrEqual(
                $range['end_date'],
                $range['start_date'],
                "Reversed input must produce start <= end (iter $i)"
            );
        }
    }
}
