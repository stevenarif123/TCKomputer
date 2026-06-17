<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/helpers.php';

use PHPUnit\Framework\TestCase;

/**
 * Property-Based Test for sanitizeOutput()
 *
 * **Validates: Requirements 16.2**
 *
 * Property 17: Output Sanitization
 * For ANY arbitrary string input, sanitizeOutput() must properly escape all
 * HTML special characters using htmlspecialchars with ENT_QUOTES and UTF-8 encoding.
 */
class OutputSanitizationPropertyTest extends TestCase
{
    /**
     * Number of random iterations per test method.
     */
    private const ITERATIONS = 500;

    /**
     * Generate a random string that includes HTML special characters.
     */
    private function generateRandomString(int $maxLength = 200): string
    {
        $length = mt_rand(0, $maxLength);

        if ($length === 0) {
            return '';
        }

        $chars = '';
        for ($i = 0; $i < $length; $i++) {
            $type = mt_rand(0, 7);
            switch ($type) {
                case 0: // ASCII alphanumeric
                    $chars .= chr(mt_rand(48, 122));
                    break;
                case 1: // HTML special characters
                    $pool = '<>&"\'';
                    $chars .= $pool[mt_rand(0, strlen($pool) - 1)];
                    break;
                case 2: // Mixed HTML-like content
                    $pool = ['<script>', '</script>', '<img src="x">', '<div class=\'test\'>', '&amp;', '&lt;'];
                    $chars .= $pool[mt_rand(0, count($pool) - 1)];
                    break;
                case 3: // Regular text with spaces
                    $pool = 'abcdefghijklmnopqrstuvwxyz ABCDEFGHIJKLMNOPQRSTUVWXYZ';
                    $chars .= $pool[mt_rand(0, strlen($pool) - 1)];
                    break;
                case 4: // Digits
                    $chars .= chr(mt_rand(48, 57));
                    break;
                case 5: // Unicode characters (2-byte UTF-8)
                    $codepoint = mt_rand(0x00C0, 0x024F);
                    $chars .= mb_chr($codepoint, 'UTF-8');
                    break;
                case 6: // Common punctuation (non-HTML special)
                    $pool = '!@#$%^*()_+-=[]{}|;:,.?/~`';
                    $chars .= $pool[mt_rand(0, strlen($pool) - 1)];
                    break;
                case 7: // Newlines and tabs
                    $pool = "\n\r\t";
                    $chars .= $pool[mt_rand(0, strlen($pool) - 1)];
                    break;
            }
        }

        return $chars;
    }

    /**
     * Generate strings that specifically contain HTML special characters.
     *
     * @return array<string>
     */
    private function stringsWithHtmlChars(): array
    {
        return [
            '<script>alert("XSS")</script>',
            '<img src="x" onerror="alert(1)">',
            "It's a <b>bold</b> world & more",
            '"><script>document.cookie</script>',
            "'; DROP TABLE users; --",
            '<div class="test">Hello & goodbye</div>',
            '5 > 3 && 2 < 4',
            "Quote: \"Hello\" and 'World'",
            '&amp; &lt; &gt; &quot; &#039;',
            '<a href="https://example.com?a=1&b=2">Link</a>',
            '<<>>&&""\'\'',
            str_repeat('<>&"\'', 50),
        ];
    }

    /**
     * Property 1: Output never contains raw < character (always escaped to &lt;).
     *
     * @test
     */
    public function outputNeverContainsRawLessThan(): void
    {
        // Test specific HTML strings
        foreach ($this->stringsWithHtmlChars() as $input) {
            $output = sanitizeOutput($input);
            $this->assertStringNotContainsString(
                '<',
                $output,
                "Output contains raw < for input: " . json_encode($input)
            );
        }

        // Test random inputs
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $input = $this->generateRandomString();
            $output = sanitizeOutput($input);
            $this->assertStringNotContainsString(
                '<',
                $output,
                "Output contains raw < for random input: " . json_encode($input)
            );
        }
    }

    /**
     * Property 2: Output never contains raw > character (always escaped to &gt;).
     *
     * @test
     */
    public function outputNeverContainsRawGreaterThan(): void
    {
        // Test specific HTML strings
        foreach ($this->stringsWithHtmlChars() as $input) {
            $output = sanitizeOutput($input);
            $this->assertStringNotContainsString(
                '>',
                $output,
                "Output contains raw > for input: " . json_encode($input)
            );
        }

        // Test random inputs
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $input = $this->generateRandomString();
            $output = sanitizeOutput($input);
            $this->assertStringNotContainsString(
                '>',
                $output,
                "Output contains raw > for random input: " . json_encode($input)
            );
        }
    }

    /**
     * Property 3: Output never contains raw & character unless part of an entity
     * (always escaped to &amp;).
     *
     * Any & in the output must be followed by characters forming a valid HTML entity
     * (e.g., &amp; &lt; &gt; &quot; &#039; or &#digits;).
     *
     * @test
     */
    public function outputNeverContainsRawAmpersand(): void
    {
        // Test specific HTML strings
        foreach ($this->stringsWithHtmlChars() as $input) {
            $output = sanitizeOutput($input);
            // Every & in output should be part of a proper HTML entity
            // After htmlspecialchars, all & become &amp;, so no raw & should exist
            // that isn't part of &amp; &lt; &gt; &quot; &#039;
            $this->assertNoRawAmpersand($output, $input);
        }

        // Test random inputs
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $input = $this->generateRandomString();
            $output = sanitizeOutput($input);
            $this->assertNoRawAmpersand($output, $input);
        }
    }

    /**
     * Property 4: Output never contains raw " character (always escaped to &quot;).
     *
     * @test
     */
    public function outputNeverContainsRawDoubleQuote(): void
    {
        // Test specific HTML strings
        foreach ($this->stringsWithHtmlChars() as $input) {
            $output = sanitizeOutput($input);
            $this->assertStringNotContainsString(
                '"',
                $output,
                "Output contains raw \" for input: " . json_encode($input)
            );
        }

        // Test random inputs
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $input = $this->generateRandomString();
            $output = sanitizeOutput($input);
            $this->assertStringNotContainsString(
                '"',
                $output,
                "Output contains raw \" for random input: " . json_encode($input)
            );
        }
    }

    /**
     * Property 5: Output never contains raw ' character (always escaped to &#039;).
     *
     * @test
     */
    public function outputNeverContainsRawSingleQuote(): void
    {
        // Test specific HTML strings
        foreach ($this->stringsWithHtmlChars() as $input) {
            $output = sanitizeOutput($input);
            $this->assertStringNotContainsString(
                "'",
                $output,
                "Output contains raw ' for input: " . json_encode($input)
            );
        }

        // Test random inputs
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $input = $this->generateRandomString();
            $output = sanitizeOutput($input);
            $this->assertStringNotContainsString(
                "'",
                $output,
                "Output contains raw ' for random input: " . json_encode($input)
            );
        }
    }

    /**
     * Property 6: Alphanumeric characters pass through unchanged.
     *
     * @test
     */
    public function alphanumericCharactersPassThroughUnchanged(): void
    {
        // Test with purely alphanumeric strings
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $input = $this->generateAlphanumericString();
            $output = sanitizeOutput($input);
            $this->assertSame(
                $input,
                $output,
                "Alphanumeric string was modified: " . json_encode($input)
            );
        }
    }

    /**
     * Property 7: Output is always safe for HTML embedding (no unescaped HTML special chars).
     *
     * @test
     */
    public function outputIsAlwaysSafeForHtmlEmbedding(): void
    {
        $unsafeChars = ['<', '>', '"', "'"];

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $input = $this->generateRandomString();
            $output = sanitizeOutput($input);

            foreach ($unsafeChars as $char) {
                $this->assertStringNotContainsString(
                    $char,
                    $output,
                    "Output contains unsafe char '{$char}' for input: " . json_encode($input)
                );
            }

            // Also verify no raw & (must be entity-escaped)
            $this->assertNoRawAmpersand($output, $input);
        }
    }

    /**
     * Property 8: sanitizeOutput is idempotent-safe (applying it to already-sanitized
     * text doesn't break it - it just double-escapes consistently).
     *
     * @test
     */
    public function sanitizeOutputIsIdempotentSafe(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $input = $this->generateRandomString();
            $firstPass = sanitizeOutput($input);
            $secondPass = sanitizeOutput($firstPass);

            // Double-sanitized output should equal applying htmlspecialchars twice
            $expected = htmlspecialchars(htmlspecialchars($input, ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');
            $this->assertSame(
                $expected,
                $secondPass,
                "Double-sanitization is inconsistent for input: " . json_encode($input)
            );

            // The second pass should still be HTML-safe (no raw special chars)
            $this->assertStringNotContainsString('<', $secondPass);
            $this->assertStringNotContainsString('>', $secondPass);
            $this->assertStringNotContainsString('"', $secondPass);
            $this->assertStringNotContainsString("'", $secondPass);
        }
    }

    /**
     * Property 9: Empty string produces empty string.
     *
     * @test
     */
    public function emptyStringProducesEmptyString(): void
    {
        $output = sanitizeOutput('');
        $this->assertSame('', $output, "Empty input should produce empty output");
    }

    /**
     * Helper: Assert that every & in the output is part of a valid HTML entity.
     */
    private function assertNoRawAmpersand(string $output, string $originalInput): void
    {
        // After htmlspecialchars with ENT_QUOTES, all & in the output must be
        // part of: &amp; &lt; &gt; &quot; &#039;
        // We check by verifying every & is followed by a valid entity pattern
        $pos = 0;
        while (($pos = strpos($output, '&', $pos)) !== false) {
            $remaining = substr($output, $pos);
            $validEntity = (
                str_starts_with($remaining, '&amp;') ||
                str_starts_with($remaining, '&lt;') ||
                str_starts_with($remaining, '&gt;') ||
                str_starts_with($remaining, '&quot;') ||
                str_starts_with($remaining, '&#039;')
            );
            $this->assertTrue(
                $validEntity,
                "Raw & found (not part of valid entity) at position {$pos} in output for input: " . json_encode($originalInput) . " | Output: " . json_encode($output)
            );
            $pos++;
        }
    }

    /**
     * Helper: Generate a purely alphanumeric string.
     */
    private function generateAlphanumericString(int $maxLength = 100): string
    {
        $length = mt_rand(1, $maxLength);
        $chars = '';
        $pool = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

        for ($i = 0; $i < $length; $i++) {
            $chars .= $pool[mt_rand(0, strlen($pool) - 1)];
        }

        return $chars;
    }
}
