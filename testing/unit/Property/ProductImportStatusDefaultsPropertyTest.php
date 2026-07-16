<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/helpers.php';
require_once __DIR__ . '/../../../config/import.php';

use PHPUnit\Framework\TestCase;

/**
 * Property 2: Product Status Always Ready.
 *
 * **Validates: Requirements 2.4**
 */
class ProductImportStatusDefaultsPropertyTest extends TestCase
{
    public function testEveryValidMappedRowHasReadyStatusRegardlessOfAcceptedCsvStatusInput(): void
    {
        foreach (['completed', 'Completed', ' completed ', "COMPLETED\t"] as $csvStatus) {
            $result = validateAndMapRow([
                'status' => $csvStatus,
                'nama' => 'Produk ' . md5($csvStatus),
                'kategori_id' => '1',
                'harga_jual' => '1000',
            ], [1 => 'Kategori']);

            $this->assertTrue($result['valid']);
            $this->assertSame('ready', $result['mapped']['status']);
        }
    }
}
