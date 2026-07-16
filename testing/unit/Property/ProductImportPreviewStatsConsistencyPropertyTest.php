<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/helpers.php';
require_once __DIR__ . '/../../../config/import.php';

use PHPUnit\Framework\TestCase;

/**
 * Property-Based Test for product import preview stats consistency.
 *
 * **Validates: Requirements 4.3**
 */
class ProductImportPreviewStatsConsistencyPropertyTest extends TestCase
{
    /**
     * Property 7: Stats Consistency
     *
     * **Validates: Requirements 4.3**
     *
     * @test
     */
    public function previewStatsAlwaysPartitionTotalCsvRows(): void
    {
        $categoryMap = [1 => 'Keyboard'];
        $statuses = ['completed', 'pending', 'failed', '', ' Completed '];

        for ($case = 0; $case < 100; $case++) {
            $rows = [];
            for ($i = 0, $count = random_int(0, 40); $i < $count; $i++) {
                $rows[] = [
                    'status' => $statuses[random_int(0, count($statuses) - 1)],
                    'nama' => random_int(0, 5) === 0 ? '' : 'Produk ' . $case . '-' . $i,
                    'kategori_id' => random_int(0, 4) === 0 ? '999' : '1',
                    'harga_jual' => random_int(0, 4) === 0 ? '0' : (string) random_int(1, 1000000),
                ];
            }

            $stats = buildImportPreviewData($rows, $categoryMap, null)['stats'];

            $this->assertSame(count($rows), $stats['total_csv_rows']);
            $this->assertSame(
                $stats['total_csv_rows'],
                $stats['skipped_not_completed'] + $stats['valid'] + $stats['invalid']
            );
        }
    }
}
