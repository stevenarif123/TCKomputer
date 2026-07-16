<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/helpers.php';
require_once __DIR__ . '/../../../config/import.php';

use PHPUnit\Framework\TestCase;

/**
 * Property-Based Test for product import completed-row filtering.
 *
 * **Validates: Requirements 2.1, 5.1**
 */
class ProductImportCompletedRowFilteringTest extends TestCase
{
    /**
     * Property 1: Only Completed Rows Imported
     *
     * **Validates: Requirements 2.1, 5.1**
     *
     * @test
     */
    public function onlyCompletedRowsCanBecomeValidImportCandidates(): void
    {
        $categoryMap = [1 => 'Keyboard'];
        $baseRow = [
            'nama' => 'Keyboard Mechanical',
            'kategori_id' => '1',
            'harga_jual' => '150000',
            'stock' => '3',
        ];

        foreach (['completed', 'Completed', ' completed ', 'pending', 'failed', 'draft', '', 'complete'] as $status) {
            $result = validateAndMapRow($baseRow + ['status' => $status], $categoryMap);

            $this->assertSame(strtolower(trim($status)) === 'completed', $result['valid'], "status: {$status}");
        }
    }
}
