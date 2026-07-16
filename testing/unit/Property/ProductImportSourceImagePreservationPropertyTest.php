<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/import.php';

use PHPUnit\Framework\TestCase;

class ProductImportSourceImagePreservationPropertyTest extends TestCase
{
    private string $sourceDir;
    private string $targetDir;

    protected function setUp(): void
    {
        $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'import_preserve_' . uniqid('', true);
        $this->sourceDir = $base . DIRECTORY_SEPARATOR . 'source';
        $this->targetDir = $base . DIRECTORY_SEPARATOR . 'target';
        mkdir($this->sourceDir, 0777, true);
        mkdir($this->targetDir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach ([$this->targetDir, $this->sourceDir, dirname($this->sourceDir)] as $dir) {
            foreach (glob($dir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
                is_dir($file) ? @rmdir($file) : @unlink($file);
            }
            @rmdir($dir);
        }
    }

    public function testSuccessfulImageImportCopiesFileAndPreservesSourcePath(): void
    {
        /** Validates: Requirements 3.7 */
        $sourcePath = $this->sourceDir . DIRECTORY_SEPARATOR . 'source.png';
        $sourceBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=');
        file_put_contents($sourcePath, $sourceBytes);

        for ($i = 0; $i < 20; $i++) {
            $copiedName = copyImportImage($sourcePath, $this->targetDir);

            $this->assertIsString($copiedName);
            $this->assertFileExists($this->targetDir . DIRECTORY_SEPARATOR . $copiedName);
            $this->assertFileExists($sourcePath);
            $this->assertSame($sourceBytes, file_get_contents($sourcePath));
        }
    }
}
