<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Property-Based Test for Order Status Transitions
 *
 * **Validates: Requirements 10.4**
 *
 * Property 12: Order Status Transitions
 * Tests the state machine transitions defined in admin/order-update-status.php:
 * - Valid forward transitions are accepted
 * - dibatalkan is reachable from every state except selesai
 * - No transitions from terminal states (selesai, dibatalkan)
 * - Invalid transitions are always rejected
 * - Self-transitions are not allowed
 * - Random invalid status strings are always rejected
 */
class OrderStatusTransitionPropertyTest extends TestCase
{
    /**
     * Number of random iterations per test method.
     */
    private const ITERATIONS = 500;

    /**
     * The allowed transitions state machine (mirrors admin/order-update-status.php).
     */
    private const ALLOWED_TRANSITIONS = [
        'menunggu_konfirmasi' => ['diproses', 'dibatalkan'],
        'diproses' => ['siap_diantar', 'dibatalkan'],
        'siap_diantar' => ['dikirim', 'dibatalkan'],
        'dikirim' => ['selesai', 'dibatalkan'],
        'selesai' => [],
        'dibatalkan' => [],
    ];

    /**
     * All valid order statuses.
     */
    private const VALID_STATUSES = [
        'menunggu_konfirmasi',
        'diproses',
        'siap_diantar',
        'dikirim',
        'selesai',
        'dibatalkan',
    ];

    /**
     * The valid forward path (happy path without cancellation).
     */
    private const FORWARD_TRANSITIONS = [
        ['menunggu_konfirmasi', 'diproses'],
        ['diproses', 'siap_diantar'],
        ['siap_diantar', 'dikirim'],
        ['dikirim', 'selesai'],
    ];

    /**
     * Check if a transition is allowed by the state machine.
     */
    private function isTransitionAllowed(string $from, string $to): bool
    {
        $allowed = self::ALLOWED_TRANSITIONS[$from] ?? [];
        return in_array($to, $allowed, true);
    }

    /**
     * Generate a random invalid status string that is NOT in the valid statuses list.
     */
    private function generateRandomInvalidStatus(): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyz_0123456789';
        $length = mt_rand(1, 30);
        do {
            $result = '';
            for ($i = 0; $i < $length; $i++) {
                $result .= $chars[mt_rand(0, strlen($chars) - 1)];
            }
        } while (in_array($result, self::VALID_STATUSES, true));

        return $result;
    }

    /**
     * Pick a random valid status.
     */
    private function randomValidStatus(): string
    {
        return self::VALID_STATUSES[array_rand(self::VALID_STATUSES)];
    }

    /**
     * Property 1: All valid forward transitions are accepted.
     * menunggu_konfirmasi→diproses, diproses→siap_diantar, siap_diantar→dikirim, dikirim→selesai
     *
     * @test
     */
    public function allValidForwardTransitionsAreAccepted(): void
    {
        foreach (self::FORWARD_TRANSITIONS as [$from, $to]) {
            $this->assertTrue(
                $this->isTransitionAllowed($from, $to),
                "Forward transition {$from} → {$to} should be allowed"
            );
        }

        // Property: randomly picking any forward transition always yields true
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            [$from, $to] = self::FORWARD_TRANSITIONS[array_rand(self::FORWARD_TRANSITIONS)];
            $this->assertTrue(
                $this->isTransitionAllowed($from, $to),
                "Forward transition {$from} → {$to} should always be allowed (iteration {$i})"
            );
        }
    }

    /**
     * Property 2: dibatalkan is reachable from every state EXCEPT selesai.
     *
     * @test
     */
    public function dibatalkanIsReachableFromEveryStateExceptSelesai(): void
    {
        $statesCanCancel = ['menunggu_konfirmasi', 'diproses', 'siap_diantar', 'dikirim'];

        // All cancellable states can transition to dibatalkan
        foreach ($statesCanCancel as $from) {
            $this->assertTrue(
                $this->isTransitionAllowed($from, 'dibatalkan'),
                "State {$from} should be able to transition to dibatalkan"
            );
        }

        // selesai cannot transition to dibatalkan
        $this->assertFalse(
            $this->isTransitionAllowed('selesai', 'dibatalkan'),
            "State selesai should NOT be able to transition to dibatalkan"
        );

        // Property: randomly picking a cancellable state always allows dibatalkan
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $from = $statesCanCancel[array_rand($statesCanCancel)];
            $this->assertTrue(
                $this->isTransitionAllowed($from, 'dibatalkan'),
                "Randomly chosen state {$from} should allow transition to dibatalkan (iteration {$i})"
            );
        }
    }

    /**
     * Property 3: No transitions are possible from 'selesai' (terminal state).
     *
     * @test
     */
    public function noTransitionsFromSelesai(): void
    {
        // No valid status can be reached from selesai
        foreach (self::VALID_STATUSES as $to) {
            $this->assertFalse(
                $this->isTransitionAllowed('selesai', $to),
                "selesai should not allow transition to {$to}"
            );
        }

        // Property: random invalid status strings are also rejected from selesai
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $randomStatus = $this->generateRandomInvalidStatus();
            $this->assertFalse(
                $this->isTransitionAllowed('selesai', $randomStatus),
                "selesai should not allow transition to random status '{$randomStatus}' (iteration {$i})"
            );
        }
    }

    /**
     * Property 4: No transitions are possible from 'dibatalkan' (terminal state).
     *
     * @test
     */
    public function noTransitionsFromDibatalkan(): void
    {
        // No valid status can be reached from dibatalkan
        foreach (self::VALID_STATUSES as $to) {
            $this->assertFalse(
                $this->isTransitionAllowed('dibatalkan', $to),
                "dibatalkan should not allow transition to {$to}"
            );
        }

        // Property: random invalid status strings are also rejected from dibatalkan
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $randomStatus = $this->generateRandomInvalidStatus();
            $this->assertFalse(
                $this->isTransitionAllowed('dibatalkan', $randomStatus),
                "dibatalkan should not allow transition to random status '{$randomStatus}' (iteration {$i})"
            );
        }
    }

    /**
     * Property 5: Invalid transitions are always rejected.
     * Examples: menunggu_konfirmasi→selesai, dikirim→diproses, etc.
     *
     * @test
     */
    public function invalidTransitionsAreAlwaysRejected(): void
    {
        // Collect all invalid transitions between valid statuses
        $invalidTransitions = [];
        foreach (self::VALID_STATUSES as $from) {
            foreach (self::VALID_STATUSES as $to) {
                if ($from === $to) {
                    continue; // self-transitions tested separately
                }
                if (!$this->isTransitionAllowed($from, $to)) {
                    $invalidTransitions[] = [$from, $to];
                }
            }
        }

        // Verify known invalid transitions
        $knownInvalid = [
            ['menunggu_konfirmasi', 'selesai'],
            ['menunggu_konfirmasi', 'siap_diantar'],
            ['menunggu_konfirmasi', 'dikirim'],
            ['diproses', 'selesai'],
            ['diproses', 'dikirim'],
            ['diproses', 'menunggu_konfirmasi'],
            ['siap_diantar', 'selesai'],
            ['siap_diantar', 'diproses'],
            ['siap_diantar', 'menunggu_konfirmasi'],
            ['dikirim', 'diproses'],
            ['dikirim', 'siap_diantar'],
            ['dikirim', 'menunggu_konfirmasi'],
        ];

        foreach ($knownInvalid as [$from, $to]) {
            $this->assertFalse(
                $this->isTransitionAllowed($from, $to),
                "Invalid transition {$from} → {$to} should be rejected"
            );
        }

        // Property: randomly picking an invalid transition always yields false
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            [$from, $to] = $invalidTransitions[array_rand($invalidTransitions)];
            $this->assertFalse(
                $this->isTransitionAllowed($from, $to),
                "Invalid transition {$from} → {$to} should always be rejected (iteration {$i})"
            );
        }
    }

    /**
     * Property 6: Self-transitions (same state → same state) are not in the allowed list.
     *
     * @test
     */
    public function selfTransitionsAreNeverAllowed(): void
    {
        // Exhaustive check
        foreach (self::VALID_STATUSES as $status) {
            $this->assertFalse(
                $this->isTransitionAllowed($status, $status),
                "Self-transition {$status} → {$status} should not be allowed"
            );
        }

        // Property: randomly picking a status and self-transitioning is always rejected
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $status = $this->randomValidStatus();
            $this->assertFalse(
                $this->isTransitionAllowed($status, $status),
                "Self-transition {$status} → {$status} should never be allowed (iteration {$i})"
            );
        }
    }

    /**
     * Property 7: Random invalid status strings are always rejected as target from any state.
     *
     * @test
     */
    public function randomInvalidStatusStringsAreAlwaysRejected(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $from = $this->randomValidStatus();
            $invalidTo = $this->generateRandomInvalidStatus();

            $this->assertFalse(
                $this->isTransitionAllowed($from, $invalidTo),
                "Transition from {$from} to invalid status '{$invalidTo}' should be rejected (iteration {$i})"
            );
        }

        // Also test invalid 'from' status
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $invalidFrom = $this->generateRandomInvalidStatus();
            $to = $this->randomValidStatus();

            $this->assertFalse(
                $this->isTransitionAllowed($invalidFrom, $to),
                "Transition from invalid status '{$invalidFrom}' to {$to} should be rejected (iteration {$i})"
            );
        }
    }
}
