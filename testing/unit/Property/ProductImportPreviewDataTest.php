<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/helpers.php';
require_once __DIR__ . '/../../../config/import.php';

use PHPUnit\Framework\TestCase;

class ProductImportPreviewDataTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'preview_images_' . uniqid('', true);
        mkdir($this->dir);
        file_put_contents($this->dir . DIRECTORY_SEPARATOR . 'Main.JPG', 'x');
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->dir);
    }

    public function testBuildsSessionShapedPreviewDataAndConsistentStats(): void
    {
        $csv = tempnam(sys_get_temp_dir(), 'preview_csv_');
        file_put_contents($csv, "status;nama;kategori_id;harga_jual;image;semua_gambar\ncompleted;Valid;1;1000;main.jpg;missing.png\npending;Skipped;1;1000;;\ncompleted;;9;0;;\n");

        try {
            $parsed = parseImportCSV($csv);
            $data = buildImportPreviewData($parsed['rows'], [1 => 'Category'], $this->dir, ['warn']);
        } finally {
            unlink($csv);
        }

        $this->assertSame($this->dir, $data['image_folder']);
        $this->assertSame(['warn'], $data['image_warnings']);
        $this->assertSame(3, $data['stats']['total_csv_rows']);
        $this->assertSame(1, $data['stats']['skipped_not_completed']);
        $this->assertSame(1, $data['stats']['valid']);
        $this->assertSame(1, $data['stats']['invalid']);
        $this->assertSame(1, $data['stats']['images_matched']);
        $this->assertSame(1, $data['stats']['images_missing']);
        $this->assertSame(3, $data['stats']['skipped_not_completed'] + $data['stats']['valid'] + $data['stats']['invalid']);
        $this->assertSame('Valid', $data['rows'][0]['mapped']['name']);
        $this->assertTrue($data['rows'][0]['valid']);
        $this->assertTrue($data['rows'][0]['main_image_found']);
        $this->assertSame([false], $data['rows'][0]['additional_images_found']);
        $this->assertTrue($data['rows'][1]['skipped']);
        $this->assertFalse($data['rows'][2]['valid']);
        $this->assertNotEmpty($data['rows'][2]['errors']);
    }
}
