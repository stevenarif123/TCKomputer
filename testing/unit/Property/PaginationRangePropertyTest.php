<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/helpers.php';

use PHPUnit\Framework\TestCase;

/**
 * Property-Based Test for Pagination Range
 *
 * **Validates: Requirements 10.3, 10.4, 10.5, 10.6**
 */
class PaginationRangePropertyTest extends TestCase
{
    private const ITERATIONS = 1000;

    /**
     * Property 12: Pagination First And Last Inclusion
     * For any total page count greater than 1 and any current page,
     * `generatePaginationRange()` should include page 1 and the final page.
     *
     * **Validates: Requirements 10.3**
     *
     * @test
     */
    public function paginationFirstAndLastInclusion(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $totalPages = mt_rand(2, 100);
            $currentPage = mt_rand(-10, $totalPages + 10);
            $neighbors = mt_rand(0, 5);

            $range = generatePaginationRange($currentPage, $totalPages, $neighbors);

            $this->assertContains(1, $range, "Page 1 is missing when totalPages > 1");
            $this->assertContains($totalPages, $range, "Final page ($totalPages) is missing when totalPages > 1");
        }
    }

    /**
     * Property 13: Pagination Neighbor Inclusion
     * For any current page, total page count, and non-negative neighbor count,
     * `generatePaginationRange()` should include every configured neighbor page
     * that exists within the valid page range.
     *
     * **Validates: Requirements 10.4**
     *
     * @test
     */
    public function paginationNeighborInclusion(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $totalPages = mt_rand(1, 100);
            $currentPageRaw = mt_rand(-10, $totalPages + 10);
            $currentPage = max(1, min($currentPageRaw, $totalPages));
            $neighbors = mt_rand(0, 10);

            $range = generatePaginationRange($currentPageRaw, $totalPages, $neighbors);

            for ($n = $currentPage - $neighbors; $n <= $currentPage + $neighbors; $n++) {
                if ($n >= 1 && $n <= $totalPages) {
                    $this->assertContains($n, $range, "Neighbor page $n is missing");
                }
            }
        }
    }

    /**
     * Property 14: Pagination Ellipsis Correctness
     * Ellipsis markers should appear only between non-consecutive page numbers
     * and should not appear at the beginning or end of the range.
     *
     * **Validates: Requirements 10.5**
     *
     * @test
     */
    public function paginationEllipsisCorrectness(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $totalPages = mt_rand(0, 100);
            $currentPage = mt_rand(-10, $totalPages + 10);
            $neighbors = mt_rand(0, 5);

            $range = generatePaginationRange($currentPage, $totalPages, $neighbors);
            $count = count($range);

            if ($count === 0) {
                continue;
            }

            // Cannot start or end with ellipsis
            $this->assertNotSame('...', $range[0], "Range starts with ellipsis");
            $this->assertNotSame('...', $range[$count - 1], "Range ends with ellipsis");

            // Ellipsis only between non-consecutive
            for ($j = 0; $j < $count; $j++) {
                if ($range[$j] === '...') {
                    $this->assertGreaterThan(0, $j);
                    $this->assertLessThan($count - 1, $j);

                    $prev = $range[$j - 1];
                    $next = $range[$j + 1];

                    $this->assertIsInt($prev);
                    $this->assertIsInt($next);
                    $this->assertGreaterThan(1, $next - $prev, "Ellipsis between consecutive pages $prev and $next");
                } else {
                    if ($j > 0 && $range[$j - 1] !== '...') {
                        $prev = $range[$j - 1];
                        $curr = $range[$j];
                        $this->assertSame($prev + 1, $curr, "Missing ellipsis between non-consecutive pages $prev and $curr");
                    }
                }
            }
        }
    }

    /**
     * Property 15: Pagination Element Bounds
     * Every element returned should be either an integer in the valid page range
     * or the ellipsis marker string.
     *
     * **Validates: Requirements 10.6**
     *
     * @test
     */
    public function paginationElementBounds(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $totalPages = mt_rand(0, 100);
            $currentPage = mt_rand(-10, $totalPages + 10);
            $neighbors = mt_rand(-5, 5); // include negative to test max(0, $neighbors)

            $range = generatePaginationRange($currentPage, $totalPages, $neighbors);

            foreach ($range as $element) {
                if (is_string($element)) {
                    $this->assertSame('...', $element, "Invalid string element: $element");
                } else {
                    $this->assertIsInt($element);
                    $this->assertGreaterThanOrEqual(1, $element, "Element less than 1");
                    $this->assertLessThanOrEqual($totalPages, $element, "Element greater than totalPages ($totalPages)");
                }
            }
        }
    }
}
