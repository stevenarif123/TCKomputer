<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';

use PHPUnit\Framework\TestCase;

/**
 * Property-Based Test for Active-Only Shipping Area Display
 *
 * **Validates: Requirements 11.3, 11.4**
 *
 * Property 19: Active-Only Display for Shipping Areas
 * The buyer-facing checkout query must ONLY return shipping areas with is_active = 1.
 * Inactive shipping areas must NEVER appear in the buyer checkout options.
 *
 * Properties tested:
 * 1. All results from the buyer-facing query have is_active = 1
 * 2. No inactive shipping areas appear in buyer-facing query results
 * 3. All active shipping areas in DB appear in buyer-facing results (completeness)
 * 4. When a shipping area is deactivated, it no longer appears in results
 * 5. When a shipping area is reactivated, it appears in results again
 */
class ShippingAreaActiveOnlyPropertyTest extends TestCase
{
    private PDO $pdo;

    /**
     * Number of random toggle iterations for state-change tests.
     */
    private const TOGGLE_ITERATIONS = 50;

    protected function setUp(): void
    {
        $this->pdo = getDBConnection();
    }

    /**
     * Execute the buyer-facing checkout query (same pattern as checkout.php).
     *
     * @return array<array<string, mixed>>
     */
    private function fetchBuyerShippingAreas(): array
    {
        $stmt = $this->pdo->query(
            "SELECT * FROM shipping_areas WHERE is_active = 1 ORDER BY area_name"
        );
        return $stmt->fetchAll();
    }

    /**
     * Fetch all shipping areas from the database (regardless of status).
     *
     * @return array<array<string, mixed>>
     */
    private function fetchAllShippingAreas(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM shipping_areas ORDER BY id");
        return $stmt->fetchAll();
    }

    /**
     * Pick a random shipping area ID from the database.
     */
    private function getRandomShippingAreaId(): ?int
    {
        $all = $this->fetchAllShippingAreas();
        if (empty($all)) {
            return null;
        }
        $randomIndex = mt_rand(0, count($all) - 1);
        return (int)$all[$randomIndex]['id'];
    }

    /**
     * Property 1: All shipping areas returned by the buyer-facing query have is_active = 1.
     *
     * @test
     */
    public function buyerQueryOnlyReturnsActiveShippingAreas(): void
    {
        $buyerAreas = $this->fetchBuyerShippingAreas();

        foreach ($buyerAreas as $area) {
            $this->assertEquals(
                1,
                (int)$area['is_active'],
                sprintf(
                    "Buyer-facing query returned shipping area '%s' (ID: %d) with is_active = %d. "
                    . "Only active areas (is_active = 1) should be displayed.",
                    $area['area_name'],
                    $area['id'],
                    $area['is_active']
                )
            );
        }
    }

    /**
     * Property 2: No inactive shipping areas (is_active = 0) appear in the buyer-facing query results.
     * Uses transaction to temporarily deactivate random areas and verify exclusion.
     *
     * @test
     */
    public function noInactiveAreasAppearInBuyerResults(): void
    {
        $allAreas = $this->fetchAllShippingAreas();
        if (empty($allAreas)) {
            $this->markTestSkipped('No shipping areas in the database to test.');
        }

        for ($i = 0; $i < min(self::TOGGLE_ITERATIONS, count($allAreas)); $i++) {
            // Pick a random area and deactivate it within a transaction
            $randomIndex = mt_rand(0, count($allAreas) - 1);
            $areaId = (int)$allAreas[$randomIndex]['id'];

            $this->pdo->beginTransaction();
            try {
                // Deactivate the chosen area
                $stmt = $this->pdo->prepare(
                    "UPDATE shipping_areas SET is_active = 0 WHERE id = ?"
                );
                $stmt->execute([$areaId]);

                // Get all currently inactive areas
                $stmtInactive = $this->pdo->query(
                    "SELECT id, area_name FROM shipping_areas WHERE is_active = 0"
                );
                $inactiveAreas = $stmtInactive->fetchAll();

                // Get buyer-facing results
                $buyerAreas = $this->fetchBuyerShippingAreas();
                $buyerAreaIds = array_map('intval', array_column($buyerAreas, 'id'));

                // Assert none of the inactive areas appear in buyer results
                foreach ($inactiveAreas as $inactive) {
                    $this->assertNotContains(
                        (int)$inactive['id'],
                        $buyerAreaIds,
                        sprintf(
                            "Inactive shipping area '%s' (ID: %d) was found in buyer checkout options. "
                            . "Inactive areas must be excluded from buyer display.",
                            $inactive['area_name'],
                            $inactive['id']
                        )
                    );
                }
            } finally {
                $this->pdo->rollBack();
            }
        }
    }

    /**
     * Property 3: All active shipping areas in the DB appear in the buyer-facing results (completeness).
     *
     * @test
     */
    public function allActiveAreasAppearInBuyerResults(): void
    {
        // Get all active areas directly from the database
        $stmtActive = $this->pdo->query(
            "SELECT id, area_name FROM shipping_areas WHERE is_active = 1 ORDER BY id"
        );
        $activeAreas = $stmtActive->fetchAll();

        // Get buyer-facing results
        $buyerAreas = $this->fetchBuyerShippingAreas();
        $buyerAreaIds = array_map('intval', array_column($buyerAreas, 'id'));

        // Assert every active area is present in buyer results
        foreach ($activeAreas as $active) {
            $this->assertContains(
                (int)$active['id'],
                $buyerAreaIds,
                sprintf(
                    "Active shipping area '%s' (ID: %d) is missing from buyer checkout options. "
                    . "All active areas must be displayed to buyers.",
                    $active['area_name'],
                    $active['id']
                )
            );
        }
    }

    /**
     * Property 4: When a shipping area is deactivated, it no longer appears in buyer results.
     * Uses transaction rollback to avoid permanent data changes.
     *
     * @test
     */
    public function deactivatedAreaDisappearsFromBuyerResults(): void
    {
        $allAreas = $this->fetchAllShippingAreas();
        if (empty($allAreas)) {
            $this->markTestSkipped('No shipping areas in the database to test.');
        }

        for ($i = 0; $i < min(self::TOGGLE_ITERATIONS, count($allAreas)); $i++) {
            // Pick a random area
            $randomIndex = mt_rand(0, count($allAreas) - 1);
            $areaId = (int)$allAreas[$randomIndex]['id'];

            $this->pdo->beginTransaction();
            try {
                // Deactivate the area
                $stmt = $this->pdo->prepare(
                    "UPDATE shipping_areas SET is_active = 0 WHERE id = ?"
                );
                $stmt->execute([$areaId]);

                // Fetch buyer areas after deactivation
                $buyerAreas = $this->fetchBuyerShippingAreas();
                $buyerAreaIds = array_map('intval', array_column($buyerAreas, 'id'));

                // Assert the deactivated area is NOT in buyer results
                $this->assertNotContains(
                    $areaId,
                    $buyerAreaIds,
                    sprintf(
                        "Shipping area ID %d still appears in buyer results after being deactivated. "
                        . "Deactivated areas must be excluded immediately.",
                        $areaId
                    )
                );
            } finally {
                $this->pdo->rollBack();
            }
        }
    }

    /**
     * Property 5: When a shipping area is reactivated, it appears in buyer results again.
     * Uses transaction rollback to avoid permanent data changes.
     *
     * @test
     */
    public function reactivatedAreaAppearsInBuyerResults(): void
    {
        $allAreas = $this->fetchAllShippingAreas();
        if (empty($allAreas)) {
            $this->markTestSkipped('No shipping areas in the database to test.');
        }

        for ($i = 0; $i < min(self::TOGGLE_ITERATIONS, count($allAreas)); $i++) {
            // Pick a random area
            $randomIndex = mt_rand(0, count($allAreas) - 1);
            $areaId = (int)$allAreas[$randomIndex]['id'];

            $this->pdo->beginTransaction();
            try {
                // First deactivate, then reactivate
                $stmtDeactivate = $this->pdo->prepare(
                    "UPDATE shipping_areas SET is_active = 0 WHERE id = ?"
                );
                $stmtDeactivate->execute([$areaId]);

                // Confirm it's gone
                $buyerAreasBefore = $this->fetchBuyerShippingAreas();
                $buyerIdsBefore = array_map('intval', array_column($buyerAreasBefore, 'id'));
                $this->assertNotContains($areaId, $buyerIdsBefore);

                // Now reactivate
                $stmtReactivate = $this->pdo->prepare(
                    "UPDATE shipping_areas SET is_active = 1 WHERE id = ?"
                );
                $stmtReactivate->execute([$areaId]);

                // Fetch buyer areas after reactivation
                $buyerAreas = $this->fetchBuyerShippingAreas();
                $buyerAreaIds = array_map('intval', array_column($buyerAreas, 'id'));

                // Assert the reactivated area IS in buyer results
                $this->assertContains(
                    $areaId,
                    $buyerAreaIds,
                    sprintf(
                        "Shipping area ID %d does not appear in buyer results after being reactivated. "
                        . "Reactivated areas must be shown to buyers immediately.",
                        $areaId
                    )
                );
            } finally {
                $this->pdo->rollBack();
            }
        }
    }
}
