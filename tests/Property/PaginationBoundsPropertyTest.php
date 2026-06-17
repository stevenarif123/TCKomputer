<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/../../config/db.php';

use PHPUnit\Framework\TestCase;

/**
 * Property-Based Test for Pagination Bounds
 *
 * **Validates: Requirements 1.1, 1.7**
 *
 * Property 14: Pagination Bounds
 * For ANY valid page number and perPage value, the paginate() helper must satisfy:
 * - Each page has at most `perPage` items
 * - The sum of items across all pages equals the total count
 * - Page numbers are always >= 1
 * - Current page never exceeds total pages (when total > 0)
 * - The offset is correctly calculated: (page - 1) * perPage
 * - When requesting a page beyond total pages, it clamps to the last page
 * - When there are 0 results, pages = 0 and data is empty
 */
class PaginationBoundsPropertyTest extends TestCase
{
    /**
     * Number of random iterations per test method.
     */
    private const ITERATIONS = 100;

    /**
     * @var PDO
     */
    private PDO $pdo;

    /**
     * Base query for active products.
     */
    private const BASE_QUERY = "SELECT p.* FROM products p WHERE p.is_active = 1";

    protected function setUp(): void
    {
        $this->pdo = getDBConnection();
    }

    /**
     * Generate a random perPage value between 1 and 50.
     */
    private function randomPerPage(): int
    {
        return mt_rand(1, 50);
    }

    /**
     * Generate a random page number between 1 and a reasonable upper bound.
     */
    private function randomPage(int $max = 200): int
    {
        return mt_rand(1, $max);
    }

    /**
     * Get the total count of active products for verification.
     */
    private function getTotalActiveProducts(): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM products p WHERE p.is_active = 1");
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Property: Each page has at most `perPage` items.
     * Validates: Requirement 1.1
     *
     * @test
     */
    public function eachPageHasAtMostPerPageItems(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $perPage = $this->randomPerPage();
            $page = $this->randomPage();

            $result = paginate($this->pdo, self::BASE_QUERY, [], $perPage, $page);

            $this->assertLessThanOrEqual(
                $perPage,
                count($result['data']),
                "Page $page with perPage=$perPage returned more than $perPage items"
            );
        }
    }

    /**
     * Property: The sum of items across all pages equals the total count.
     * Validates: Requirements 1.1, 1.7
     *
     * @test
     */
    public function sumOfItemsAcrossAllPagesEqualsTotalCount(): void
    {
        // Test with various perPage values
        $perPageValues = [1, 2, 3, 5, 7, 12, 15, 20, 50];

        foreach ($perPageValues as $perPage) {
            $result = paginate($this->pdo, self::BASE_QUERY, [], $perPage, 1);
            $totalPages = $result['pages'];
            $expectedTotal = $result['total'];

            $sumItems = 0;
            for ($page = 1; $page <= $totalPages; $page++) {
                $pageResult = paginate($this->pdo, self::BASE_QUERY, [], $perPage, $page);
                $sumItems += count($pageResult['data']);
            }

            $this->assertSame(
                $expectedTotal,
                $sumItems,
                "Sum of items across all pages ($sumItems) does not equal total ($expectedTotal) for perPage=$perPage"
            );
        }
    }

    /**
     * Property: Page numbers (current_page) are always >= 1.
     * Validates: Requirement 1.1
     *
     * @test
     */
    public function pageNumbersAreAlwaysAtLeastOne(): void
    {
        // Test with negative and zero page numbers
        $edgeCasePages = [-100, -10, -1, 0, 1];

        foreach ($edgeCasePages as $page) {
            $result = paginate($this->pdo, self::BASE_QUERY, [], 12, $page);

            $this->assertGreaterThanOrEqual(
                1,
                $result['current_page'],
                "current_page is less than 1 when requesting page $page"
            );
        }

        // Random testing
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $page = mt_rand(-100, 500);
            $perPage = $this->randomPerPage();

            $result = paginate($this->pdo, self::BASE_QUERY, [], $perPage, $page);

            $this->assertGreaterThanOrEqual(
                1,
                $result['current_page'],
                "current_page is less than 1 when requesting page=$page, perPage=$perPage"
            );
        }
    }

    /**
     * Property: Current page never exceeds total pages (when total > 0).
     * Validates: Requirement 1.1
     *
     * @test
     */
    public function currentPageNeverExceedsTotalPages(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $perPage = $this->randomPerPage();
            $page = $this->randomPage();

            $result = paginate($this->pdo, self::BASE_QUERY, [], $perPage, $page);

            if ($result['total'] > 0) {
                $this->assertLessThanOrEqual(
                    $result['pages'],
                    $result['current_page'],
                    "current_page ({$result['current_page']}) exceeds total pages ({$result['pages']}) for page=$page, perPage=$perPage"
                );
            }
        }
    }

    /**
     * Property: When requesting a page beyond total pages, it clamps to the last page.
     * Validates: Requirement 1.1
     *
     * @test
     */
    public function requestingPageBeyondTotalClampsToLastPage(): void
    {
        $total = $this->getTotalActiveProducts();

        if ($total === 0) {
            $this->markTestSkipped('No active products in database to test clamping.');
        }

        // Test with various perPage values and pages well beyond total
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $perPage = $this->randomPerPage();
            $totalPages = (int) ceil($total / $perPage);
            $beyondPage = $totalPages + mt_rand(1, 100);

            $result = paginate($this->pdo, self::BASE_QUERY, [], $perPage, $beyondPage);

            $this->assertSame(
                $totalPages,
                $result['current_page'],
                "Requesting page $beyondPage (beyond $totalPages total) did not clamp to last page. Got: {$result['current_page']}"
            );
        }
    }

    /**
     * Property: When there are 0 results, pages = 0 and data is empty.
     * Validates: Requirement 1.1
     *
     * @test
     */
    public function zeroResultsReturnsZeroPagesAndEmptyData(): void
    {
        // Use a query that will certainly return 0 results
        $emptyQuery = "SELECT p.* FROM products p WHERE p.is_active = 1 AND p.name = 'NONEXISTENT_PRODUCT_XYZ_12345'";

        for ($i = 0; $i < 20; $i++) {
            $perPage = $this->randomPerPage();
            $page = $this->randomPage();

            $result = paginate($this->pdo, $emptyQuery, [], $perPage, $page);

            $this->assertSame(
                0,
                $result['pages'],
                "Expected 0 pages for empty result set, got {$result['pages']}"
            );

            $this->assertEmpty(
                $result['data'],
                "Expected empty data for empty result set"
            );

            $this->assertSame(
                0,
                $result['total'],
                "Expected total=0 for empty result set"
            );
        }
    }

    /**
     * Property: Default perPage is 12 (products page standard).
     * Validates: Requirement 1.1
     *
     * @test
     */
    public function defaultPerPageIsTwelve(): void
    {
        $result = paginate($this->pdo, self::BASE_QUERY, []);
        $total = $result['total'];

        if ($total > 0) {
            $this->assertLessThanOrEqual(
                12,
                count($result['data']),
                "Default pagination returned more than 12 items"
            );

            $expectedPages = (int) ceil($total / 12);
            $this->assertSame(
                $expectedPages,
                $result['pages'],
                "Default pagination total pages mismatch: expected $expectedPages, got {$result['pages']}"
            );
        }
    }

    /**
     * Combined property: ALL pagination properties hold simultaneously for every random input.
     * Validates: Requirements 1.1, 1.7
     *
     * @test
     */
    public function allPaginationPropertiesHoldSimultaneously(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $perPage = $this->randomPerPage();
            $page = mt_rand(-10, 500);

            $result = paginate($this->pdo, self::BASE_QUERY, [], $perPage, $page);

            // Property 1: At most perPage items
            $this->assertLessThanOrEqual(
                $perPage,
                count($result['data']),
                "Combined - more than perPage items for page=$page, perPage=$perPage"
            );

            // Property 2: current_page >= 1
            $this->assertGreaterThanOrEqual(
                1,
                $result['current_page'],
                "Combined - current_page < 1 for page=$page, perPage=$perPage"
            );

            // Property 3: current_page <= pages (when total > 0)
            if ($result['total'] > 0) {
                $this->assertLessThanOrEqual(
                    $result['pages'],
                    $result['current_page'],
                    "Combined - current_page exceeds pages for page=$page, perPage=$perPage"
                );
            }

            // Property 4: pages calculation is correct
            $expectedPages = (int) ceil($result['total'] / $perPage);
            $this->assertSame(
                $expectedPages,
                $result['pages'],
                "Combined - pages calculation wrong for perPage=$perPage, total={$result['total']}"
            );

            // Property 5: When total is 0, pages is 0 and data is empty
            if ($result['total'] === 0) {
                $this->assertSame(0, $result['pages'], "Combined - pages should be 0 when total is 0");
                $this->assertEmpty($result['data'], "Combined - data should be empty when total is 0");
            }
        }
    }
}
