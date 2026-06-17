<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/helpers.php';

use PHPUnit\Framework\TestCase;

/**
 * Property-Based Test for uploadImage()
 *
 * **Validates: Requirements 15.1, 15.2, 15.3, 15.4**
 *
 * Property 9: Upload Safety
 * For ANY file input that violates MIME type, extension, size, or PHP content rules,
 * the uploadImage() function MUST reject the file (return false).
 *
 * Sub-properties tested:
 * - Invalid MIME types are always rejected (Requirement 15.1)
 * - Invalid extensions are always rejected (Requirement 15.2)
 * - Files exceeding 2MB are always rejected (Requirement 15.3)
 * - Files containing PHP opening tags are always rejected (Requirement 15.4)
 */
class UploadImagePropertyTest extends TestCase
{
    /**
     * Number of random iterations per test method.
     */
    private const ITERATIONS = 100;

    /**
     * Maximum allowed file size (2MB).
     */
    private const MAX_SIZE = 2 * 1024 * 1024;

    /**
     * Temporary directory for test files.
     */
    private string $tempDir;

    /**
     * Track created temp files for cleanup.
     * @var string[]
     */
    private array $tempFiles = [];

    /**
     * Counter for unique temp file names.
     */
    private int $fileCounter = 0;

    protected function setUp(): void
    {
        $this->tempDir = __DIR__ . DIRECTORY_SEPARATOR . 'tmp_upload_' . getmypid();
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
        $this->fileCounter = 0;
    }

    protected function tearDown(): void
    {
        // Clean up temp files
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        $this->tempFiles = [];

        // Remove temp directory
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    private function removeDirectory(string $dir): void
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * Create a temporary file with given content and return its path.
     * Writes to a local test directory to avoid Windows temp path issues with finfo.
     */
    private function createTempFile(string $content): string
    {
        $this->fileCounter++;
        $path = $this->tempDir . DIRECTORY_SEPARATOR . 'file_' . $this->fileCounter . '.tmp';
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;
        return $path;
    }

    /**
     * Create minimal valid JPEG content (JPEG magic bytes).
     */
    private function createJpegContent(): string
    {
        // Minimal JPEG: SOI marker + JFIF APP0 + minimal data + EOI marker
        return "\xFF\xD8\xFF\xE0" . str_repeat("\x00", 20) . "\xFF\xD9";
    }

    /**
     * Create minimal valid PNG content (PNG magic bytes).
     */
    private function createPngContent(): string
    {
        // PNG signature
        return "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A" . str_repeat("\x00", 20);
    }

    /**
     * Create minimal valid WebP content (RIFF + WEBP magic bytes).
     */
    private function createWebpContent(): string
    {
        // RIFF header + WEBP signature
        return "RIFF" . pack('V', 20) . "WEBP" . str_repeat("\x00", 12);
    }

    /**
     * Build a $_FILES-like array for testing.
     */
    private function buildFileArray(string $tmpName, string $name, int $size, int $error = UPLOAD_ERR_OK): array
    {
        return [
            'tmp_name' => $tmpName,
            'name' => $name,
            'size' => $size,
            'error' => $error,
        ];
    }

    /**
     * Generate a random invalid MIME type content (plain text, PDF, etc.)
     */
    private function generateInvalidMimeContent(): string
    {
        $types = [
            // Plain text
            fn() => 'This is a plain text file with no image data: ' . bin2hex(random_bytes(20)),
            // PDF-like
            fn() => '%PDF-1.4 ' . bin2hex(random_bytes(50)),
            // GIF (not in allowed list)
            fn() => "GIF89a" . str_repeat("\x00", 20),
            // Random binary
            fn() => random_bytes(mt_rand(50, 200)),
            // HTML content
            fn() => '<html><body>Not an image</body></html>',
            // SVG (XML-based, not allowed)
            fn() => '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg"></svg>',
        ];

        $generator = $types[mt_rand(0, count($types) - 1)];
        return $generator();
    }

    /**
     * Generate a random invalid file extension.
     */
    private function generateInvalidExtension(): string
    {
        $invalidExts = [
            'php', 'php3', 'php5', 'phtml', 'phar',
            'exe', 'bat', 'cmd', 'sh', 'bash',
            'gif', 'svg', 'bmp', 'tiff', 'ico',
            'html', 'htm', 'js', 'css',
            'txt', 'pdf', 'doc', 'zip', 'rar',
            'py', 'rb', 'pl', 'cgi',
        ];
        return $invalidExts[mt_rand(0, count($invalidExts) - 1)];
    }

    /**
     * Generate a random valid file extension.
     */
    private function generateValidExtension(): string
    {
        $validExts = ['jpg', 'jpeg', 'png', 'webp'];
        return $validExts[mt_rand(0, count($validExts) - 1)];
    }

    /**
     * Generate random PHP content injection payloads.
     * Uses benign code patterns that still test the detection of PHP opening tags.
     */
    private function generatePhpPayload(): string
    {
        $payloads = [
            '<?php echo "test"; ?>',
            '<?php echo 1; ?>',
            '<?= "hello" ?>',
            '<?php // comment ?>',
            '<?= 42 ?>',
            '<?php echo date("Y"); ?>',
            '<?= strlen("x") ?>',
            '<?php echo PHP_EOL; ?>',
        ];
        return $payloads[mt_rand(0, count($payloads) - 1)];
    }

    /**
     * Property: Files with invalid MIME types are always rejected.
     * **Validates: Requirements 15.1**
     *
     * For ANY file whose actual content is not image/jpeg, image/png, or image/webp,
     * uploadImage() MUST return false.
     *
     * @test
     */
    public function filesWithInvalidMimeTypeAreAlwaysRejected(): void
    {
        // Edge cases: specific non-image content types
        $edgeCases = [
            'Plain text' => 'Hello, this is not an image file at all.',
            'Empty file' => '',
            'PDF header' => '%PDF-1.4 some pdf content here',
            'GIF89a' => "GIF89a\x01\x00\x01\x00\x80\x00\x00\xFF\xFF\xFF\x00\x00\x00\x21\xF9\x04\x00\x00\x00\x00\x00\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00\x3B",
            'HTML content' => '<html><body>fake image</body></html>',
            'Binary noise' => random_bytes(100),
            'JSON data' => '{"not": "an image"}',
            'Null bytes' => str_repeat("\x00", 50),
        ];

        foreach ($edgeCases as $label => $content) {
            $tmpFile = $this->createTempFile($content);
            $file = $this->buildFileArray($tmpFile, 'test.jpg', strlen($content));

            $result = uploadImage($file, $this->tempDir);
            $this->assertFalse(
                $result,
                "File with invalid MIME type should be rejected. Case: $label"
            );
        }

        // Random iterations with invalid content
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $content = $this->generateInvalidMimeContent();
            $tmpFile = $this->createTempFile($content);
            $file = $this->buildFileArray($tmpFile, 'image.' . $this->generateValidExtension(), strlen($content));

            $result = uploadImage($file, $this->tempDir);
            $this->assertFalse(
                $result,
                "File with invalid MIME content should be rejected. Iteration: $i, content starts with: " . bin2hex(substr($content, 0, 10))
            );
        }
    }

    /**
     * Property: Files with invalid extensions are always rejected.
     * **Validates: Requirements 15.2**
     *
     * For ANY file whose extension is NOT jpg, jpeg, png, or webp,
     * uploadImage() MUST return false, even if the file content is a valid image.
     *
     * @test
     */
    public function filesWithInvalidExtensionAreAlwaysRejected(): void
    {
        // Edge cases: various dangerous and invalid extensions
        $dangerousExts = ['php', 'php3', 'php5', 'phtml', 'exe', 'bat', 'sh', 'gif', 'svg', 'bmp', 'html', 'js'];

        foreach ($dangerousExts as $ext) {
            // Use real JPEG content so MIME check passes, but extension should fail
            $content = $this->createJpegContent();
            $tmpFile = $this->createTempFile($content);
            $file = $this->buildFileArray($tmpFile, "photo.$ext", strlen($content));

            $result = uploadImage($file, $this->tempDir);
            $this->assertFalse(
                $result,
                "File with invalid extension '.$ext' should be rejected"
            );
        }

        // Edge case: double extensions
        $doubleExts = ['image.php.jpg', 'photo.jpg.php', 'test.png.exe', 'file.webp.html'];
        foreach ($doubleExts as $name) {
            $content = $this->createJpegContent();
            $tmpFile = $this->createTempFile($content);
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            // Only test if the resolved extension is actually invalid
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $file = $this->buildFileArray($tmpFile, $name, strlen($content));
                $result = uploadImage($file, $this->tempDir);
                $this->assertFalse(
                    $result,
                    "File with double extension '$name' (resolved ext: $ext) should be rejected"
                );
            }
        }

        // Random iterations with invalid extensions
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $ext = $this->generateInvalidExtension();
            $content = $this->createJpegContent();
            $tmpFile = $this->createTempFile($content);
            $file = $this->buildFileArray($tmpFile, 'upload_' . mt_rand(1, 9999) . '.' . $ext, strlen($content));

            $result = uploadImage($file, $this->tempDir);
            $this->assertFalse(
                $result,
                "File with invalid extension '.$ext' should be rejected. Iteration: $i"
            );
        }
    }

    /**
     * Property: Files exceeding 2MB are always rejected.
     * **Validates: Requirements 15.3**
     *
     * For ANY file whose reported size exceeds 2 * 1024 * 1024 bytes,
     * uploadImage() MUST return false.
     *
     * @test
     */
    public function filesExceedingSizeLimitAreAlwaysRejected(): void
    {
        // Edge cases: exact boundary and beyond
        $edgeCaseSizes = [
            'Exactly 2MB + 1 byte' => self::MAX_SIZE + 1,
            '2MB + 100 bytes' => self::MAX_SIZE + 100,
            '2MB + 1KB' => self::MAX_SIZE + 1024,
            '3MB' => 3 * 1024 * 1024,
            '5MB' => 5 * 1024 * 1024,
            '10MB' => 10 * 1024 * 1024,
        ];

        foreach ($edgeCaseSizes as $label => $size) {
            // Create a small temp file (actual content) but report large size
            $content = $this->createJpegContent();
            $tmpFile = $this->createTempFile($content);
            $file = $this->buildFileArray($tmpFile, 'photo.jpg', $size);

            $result = uploadImage($file, $this->tempDir);
            $this->assertFalse(
                $result,
                "File exceeding 2MB should be rejected. Case: $label (size: $size bytes)"
            );
        }

        // Random iterations with oversized files
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            // Random size between 2MB+1 and 50MB
            $size = self::MAX_SIZE + mt_rand(1, 48 * 1024 * 1024);
            $content = $this->createJpegContent();
            $tmpFile = $this->createTempFile($content);
            $file = $this->buildFileArray($tmpFile, 'image.' . $this->generateValidExtension(), $size);

            $result = uploadImage($file, $this->tempDir);
            $this->assertFalse(
                $result,
                "File with size $size bytes (exceeds 2MB) should be rejected. Iteration: $i"
            );
        }
    }

    /**
     * Property: Files containing PHP opening tags are always rejected.
     * **Validates: Requirements 15.4**
     *
     * For ANY file whose content contains "<?php" or "<?=" (case-insensitive),
     * uploadImage() MUST return false, even if the file is otherwise a valid image.
     *
     * @test
     */
    public function filesContainingPhpContentAreAlwaysRejected(): void
    {
        // We need actual image content that finfo recognizes as image/jpeg
        $jpegContent = $this->createRealJpegContent();

        if ($jpegContent === null) {
            $this->markTestSkipped('GD extension not available to create real JPEG content for PHP injection test');
        }

        // Use a single reusable temp file path to avoid file handle accumulation
        $tmpFile = $this->tempDir . DIRECTORY_SEPARATOR . 'php_test.tmp';

        // Edge cases: various PHP injection patterns (benign code to avoid AV detection)
        $phpPayloads = [
            '<?php echo "test"; ?>',
            '<?= "output" ?>',
            '<?php echo 1 + 2; ?>',
            '<?PHP echo strtolower("A"); ?>',
            '<?php // just a comment',
            '<?= 100 ?>',
            '<?php echo strlen("hello"); ?>',
        ];

        foreach ($phpPayloads as $payload) {
            // Embed PHP after valid JPEG content
            $content = $jpegContent . $payload;
            file_put_contents($tmpFile, $content);
            $file = $this->buildFileArray($tmpFile, 'photo.jpg', strlen($content));

            $result = uploadImage($file, $this->tempDir);
            $this->assertFalse(
                $result,
                "File containing PHP payload should be rejected. Payload: " . json_encode($payload)
            );
        }

        // Random iterations: inject random PHP payloads into image content
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $payload = $this->generatePhpPayload();

            // Randomly choose injection position: appended, or embedded within
            $position = mt_rand(0, 2);
            switch ($position) {
                case 0: // Appended after image data
                    $content = $jpegContent . $payload;
                    break;
                case 1: // Embedded in middle (after header)
                    $mid = (int)(strlen($jpegContent) / 2);
                    $content = substr($jpegContent, 0, $mid) . $payload . substr($jpegContent, $mid);
                    break;
                case 2: // Surrounded by random padding
                    $content = $jpegContent . str_repeat("\x00", mt_rand(0, 50)) . $payload . str_repeat("\x00", mt_rand(0, 50));
                    break;
            }

            file_put_contents($tmpFile, $content);
            $file = $this->buildFileArray($tmpFile, 'image.' . $this->generateValidExtension(), strlen($content));

            $result = uploadImage($file, $this->tempDir);
            $this->assertFalse(
                $result,
                "File with embedded PHP content should be rejected. Iteration: $i, Payload: " . json_encode($payload)
            );
        }

        // Cleanup the single temp file
        if (file_exists($tmpFile)) {
            unlink($tmpFile);
        }
    }

    /**
     * Create a real JPEG image using GD library that finfo will recognize.
     * Returns null if GD is not available.
     */
    private function createRealJpegContent(): ?string
    {
        if (!function_exists('imagecreatetruecolor')) {
            return null;
        }

        $img = imagecreatetruecolor(1, 1);
        $color = imagecolorallocate($img, 255, 0, 0);
        imagesetpixel($img, 0, 0, $color);

        ob_start();
        imagejpeg($img, null, 75);
        $content = ob_get_clean();

        // imagedestroy is deprecated in PHP 8.5+, use unset instead
        unset($img);

        return $content !== false ? $content : null;
    }

    /**
     * Combined property: ALL upload safety properties hold simultaneously.
     * Tests that multiple violation types are all correctly caught.
     *
     * @test
     */
    public function allUploadSafetyPropertiesRejectInvalidFiles(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            // Randomly choose which violation to introduce
            $violationType = mt_rand(0, 3);

            switch ($violationType) {
                case 0: // Invalid MIME type
                    $content = $this->generateInvalidMimeContent();
                    $tmpFile = $this->createTempFile($content);
                    $file = $this->buildFileArray($tmpFile, 'test.' . $this->generateValidExtension(), strlen($content));
                    $label = 'invalid MIME';
                    break;

                case 1: // Invalid extension
                    $content = $this->createJpegContent();
                    $tmpFile = $this->createTempFile($content);
                    $file = $this->buildFileArray($tmpFile, 'test.' . $this->generateInvalidExtension(), strlen($content));
                    $label = 'invalid extension';
                    break;

                case 2: // Oversized file
                    $content = $this->createJpegContent();
                    $tmpFile = $this->createTempFile($content);
                    $size = self::MAX_SIZE + mt_rand(1, 10 * 1024 * 1024);
                    $file = $this->buildFileArray($tmpFile, 'test.' . $this->generateValidExtension(), $size);
                    $label = 'oversized';
                    break;

                case 3: // PHP content injection
                    $jpegContent = $this->createRealJpegContent();
                    if ($jpegContent === null) {
                        // Fallback: use an invalid MIME test instead
                        $content = 'not an image ' . $this->generatePhpPayload();
                        $tmpFile = $this->createTempFile($content);
                        $file = $this->buildFileArray($tmpFile, 'test.jpg', strlen($content));
                        $label = 'PHP content (no GD fallback)';
                    } else {
                        $content = $jpegContent . $this->generatePhpPayload();
                        $tmpFile = $this->createTempFile($content);
                        $file = $this->buildFileArray($tmpFile, 'test.jpg', strlen($content));
                        $label = 'PHP content injection';
                    }
                    break;
            }

            $result = uploadImage($file, $this->tempDir);
            $this->assertFalse(
                $result,
                "Combined test: file with $label should be rejected. Iteration: $i"
            );
        }
    }
}
