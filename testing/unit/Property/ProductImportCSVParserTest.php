<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/import.php';

use PHPUnit\Framework\TestCase;

/**
 * Assertion tests for product import CSV parsing.
 *
 * **Validates: Requirements 1.1, 1.2, 1.3, 1.4**
 */
class ProductImportCSVParserTest extends TestCase
{
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    private function csv(string $contents): string
    {
        $file = tempnam(sys_get_temp_dir(), 'import_csv_');
        file_put_contents($file, $contents);
        $this->files[] = $file;
        return $file;
    }

    public function testParsesSemicolonDelimitedRows(): void
    {
        $parsed = parseImportCSV($this->csv("nama;kategori_id;harga_jual\nKeyboard;2;150000\n"));

        $this->assertSame(['nama', 'kategori_id', 'harga_jual'], $parsed['headers']);
        $this->assertSame('Keyboard', $parsed['rows'][0]['nama']);
        $this->assertSame('2', $parsed['rows'][0]['kategori_id']);
        $this->assertSame('150000', $parsed['rows'][0]['harga_jual']);
    }

    public function testStripsBomAndNormalizesHeaders(): void
    {
        $parsed = parseImportCSV($this->csv("\xEF\xBB\xBF Nama Item ; Harga Jual \nSKU 1;99000\n"));

        $this->assertSame(['nama_item', 'harga_jual'], $parsed['headers']);
        $this->assertSame('SKU 1', $parsed['rows'][0]['nama_item']);
    }

    public function testEmptyFileFailsAsHeaderless(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('header');

        parseImportCSV($this->csv(''));
    }

    public function testUnreadableFileFails(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('tidak dapat dibaca');

        parseImportCSV(sys_get_temp_dir() . '/missing-import-' . uniqid('', true) . '.csv');
    }
}
