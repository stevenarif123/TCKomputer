<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/import.php';

use PHPUnit\Framework\TestCase;

class ImportImageMatchingTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'import_images_' . uniqid('', true);
        mkdir($this->dir);
        file_put_contents($this->dir . DIRECTORY_SEPARATOR . 'Main.JPG', 'x');
        file_put_contents($this->dir . DIRECTORY_SEPARATOR . 'extra.webp', 'x');
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->dir);
    }

    public function testMatchesImageFilenameCaseInsensitively(): void
    {
        $match = matchImageFile('main.jpg', $this->dir);

        $this->assertTrue($match['found']);
        $this->assertSame($this->dir . DIRECTORY_SEPARATOR . 'Main.JPG', $match['sourcePath']);
    }

    public function testEmptyFilenameIsNotFound(): void
    {
        $this->assertSame(['found' => false, 'sourcePath' => ''], matchImageFile('', $this->dir));
    }

    public function testMatchesCommaSeparatedAdditionalImagesIndividually(): void
    {
        $matches = matchAdditionalImageFiles('MAIN.jpg, missing.png, EXTRA.WEBP', $this->dir);

        $this->assertSame([true, false, true], array_column($matches, 'found'));
    }
}
