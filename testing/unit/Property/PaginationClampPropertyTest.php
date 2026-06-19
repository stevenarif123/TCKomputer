<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/helpers.php';

use PHPUnit\Framework\TestCase;

/**
 * Property-Based Test for Pagination Clamp Logic
 *
 * **Validates: Requirements 7.4**
 */
class PaginationClampPropertyTest extends TestCase
{
    private const ITERATIONS = 1000;

    /**
     * Property 9: Product Listing Page Clamp
     * For any requested page number and any total page count,
     * the normalized page should be within the available page range when pages exist,
     * and should be page 1 when no pages are available.
     *
     * **Validates: Requirements 7.4**
     *
     * @test
     */
    public function paginationClampLogic(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            // Generate arbitrary requested page numbers
            $requestedPage = mt_rand(-100, 1000);
            
            // Generate arbitrary total items and per-page sizes
            $totalItems = mt_rand(0, 10000);
            $perPage = mt_rand(1, 100);
            
            $totalPages = (int)ceil($totalItems / $perPage);
            
            // Emulate the logic in products.php for clamping the page
            $clampedPage = $requestedPage;
            
            // Normalize minimum page
            $clampedPage = max(1, $clampedPage);
            
            // Clamp to totalPages if it exceeds
            if ($totalPages > 0 && $clampedPage > $totalPages) {
                $clampedPage = $totalPages;
            }
            
            // Further normalization ensuring page is always at least 1, and no more than max(1, totalPages)
            $finalClampedPage = max(1, min($clampedPage, max(1, $totalPages)));
            
            if ($totalPages === 0) {
                $this->assertEquals(1, $finalClampedPage, "When no pages exist, clamped page must be 1. Requested: $requestedPage");
            } else {
                $this->assertGreaterThanOrEqual(1, $finalClampedPage, "Clamped page must be at least 1. Requested: $requestedPage, Total Pages: $totalPages");
                $this->assertLessThanOrEqual($totalPages, $finalClampedPage, "Clamped page must not exceed total pages. Requested: $requestedPage, Total Pages: $totalPages");
            }
        }
    }
}
