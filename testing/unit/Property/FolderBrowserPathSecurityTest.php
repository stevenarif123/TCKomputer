<?php

declare(strict_types=1);

define('FOLDER_BROWSER_TEST', true);

require_once __DIR__ . '/../../../admin/browse-folders.php';

use PHPUnit\Framework\TestCase;

/**
 * Assertion tests for folder browser path security.
 *
 * **Validates: Requirements 7.1, 7.2, 7.3**
 */
class FolderBrowserPathSecurityTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'folder_browser_' . uniqid('', true);
        mkdir($this->base . DIRECTORY_SEPARATOR . 'images', 0777, true);
        file_put_contents($this->base . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'a.jpg', 'x');
        file_put_contents($this->base . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'b.WEBP', 'x');
        file_put_contents($this->base . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'note.txt', 'x');
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->base);
    }

    public function testListsValidBasePathAndFiltersImageFiles(): void
    {
        [$payload, $status] = buildFolderBrowserPayload('images', $this->base);

        $this->assertSame(200, $status);
        $this->assertSame(realpath($this->base . DIRECTORY_SEPARATOR . 'images'), $payload['path']);
        $this->assertSame(['a.jpg', 'b.WEBP'], array_column($payload['files'], 'name'));
        $this->assertSame([true, true], array_column($payload['files'], 'isImage'));
    }

    public function testRejectsDotDotPaths(): void
    {
        [$payload, $status] = buildFolderBrowserPayload('images/../outside', $this->base);

        $this->assertSame(400, $status);
        $this->assertArrayHasKey('error', $payload);
    }

    public function testRejectsResolvedPathOutsideBase(): void
    {
        $outside = sys_get_temp_dir();
        [$payload, $status] = buildFolderBrowserPayload($outside, $this->base);

        $this->assertSame(403, $status);
        $this->assertArrayHasKey('error', $payload);
    }

    public function testRejectsMissingCsrfToken(): void
    {
        $_SESSION['csrf_token'] = 'valid-token';

        $this->assertFalse(validateCSRFToken(''));
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
