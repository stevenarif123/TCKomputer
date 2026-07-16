<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/import.php';

use PHPUnit\Framework\TestCase;

/**
 * Property 5: Image Validation.
 *
 * **Validates: Requirements 3.4, 3.5**
 */
class ProductImportImageValidationPropertyTest extends TestCase
{
    private string $sourceDir;
    private string $targetDir;

    protected function setUp(): void
    {
        $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'import_image_validation_' . uniqid('', true);
        $this->sourceDir = $base . DIRECTORY_SEPARATOR . 'src';
        $this->targetDir = $base . DIRECTORY_SEPARATOR . 'dst';
        mkdir($this->sourceDir, 0777, true);
        mkdir($this->targetDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir(dirname($this->sourceDir));
    }

    public function testOnlyAllowedMimeTypesAtOrBelowTwoMbCanBeCopied(): void
    {
        foreach ($this->allowedImages() as $extension => $bytes) {
            $source = $this->writeSource('valid.' . $extension, $bytes);
            $copied = copyImportImage($source, $this->targetDir);

            $this->assertIsString($copied, $extension . ' should copy');
            $this->assertFileExists($this->targetDir . DIRECTORY_SEPARATOR . $copied);
        }

        foreach (['txt' => 'not an image', 'gif' => base64_decode('R0lGODlhAQABAAAAACw=', true)] as $extension => $bytes) {
            $this->assertFalse(copyImportImage($this->writeSource('invalid.' . $extension, $bytes), $this->targetDir));
        }

        $oversize = $this->writeSource('oversize.png', $this->allowedImages()['png'] . str_repeat('x', (2 * 1024 * 1024) + 1));
        $this->assertFalse(copyImportImage($oversize, $this->targetDir));
    }

    private function allowedImages(): array
    {
        return [
            'jpg' => base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAH/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAEFAqf/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/ASP/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/ASP/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAY/Al//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAE/IV//2gAMAwEAAgADAAAAEP/EABQRAQAAAAAAAAAAAAAAAAAAABD/2gAIAQMBAT8QH//EABQRAQAAAAAAAAAAAAAAAAAAABD/2gAIAQIBAT8QH//EABQQAQAAAAAAAAAAAAAAAAAAABD/2gAIAQEAAT8QH//Z', true),
            'png' => base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==', true),
            'webp' => base64_decode('UklGRiIAAABXRUJQVlA4IBYAAAAwAQCdASoBAAEADsD+JaQAA3AA/vuUAAA=', true),
        ];
    }

    private function writeSource(string $name, string $bytes): string
    {
        $path = $this->sourceDir . DIRECTORY_SEPARATOR . $name;
        file_put_contents($path, $bytes);
        return $path;
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
