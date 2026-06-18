<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/helpers.php';

use PHPUnit\Framework\TestCase;

/**
 * Property-Based Test for output sanitization on FAQ content (Property 8)
 *
 * **Validates: Requirements 13.4, 13.5**
 *
 * Property 8: Output sanitization prevents XSS
 * Test that for any string with HTML special characters, `sanitizeOutput()` escapes
 * all such characters, and `nl2br(sanitizeOutput())` converts newlines while escaping HTML.
 */
class FaqOutputSanitizationPropertyTest extends TestCase
{
    private const ITERATIONS = 100;

    /**
     * Generate random combinations of special characters, newlines, and safe alphanumeric characters.
     */
    private function generateRandomInput(): string
    {
        $specialChars = ['<', '>', '&', '"', "'"];
        $newlines = ["\n", "\r\n", "\r"];
        $safeChars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789 ';

        $length = mt_rand(10, 100);
        $input = '';

        for ($i = 0; $i < $length; $i++) {
            $type = mt_rand(0, 2);
            if ($type === 0) {
                // Add a safe character
                $input .= $safeChars[mt_rand(0, strlen($safeChars) - 1)];
            } elseif ($type === 1) {
                // Add an HTML special character
                $input .= $specialChars[mt_rand(0, count($specialChars) - 1)];
            } else {
                // Add a newline character
                $input .= $newlines[mt_rand(0, count($newlines) - 1)];
            }
        }

        return $input;
    }

    /**
     * Test Property 8: sanitizeOutput() escapes HTML special characters to prevent XSS.
     *
     * Validates: Requirements 13.4
     *
     * @test
     */
    public function testSanitizeOutputEscapesAllHtmlSpecialCharacters(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $input = $this->generateRandomInput();
            $output = sanitizeOutput($input);

            // Output must not contain raw HTML special characters
            $this->assertStringNotContainsString('<', $output, "Output contains unescaped '<' for input: " . json_encode($input));
            $this->assertStringNotContainsString('>', $output, "Output contains unescaped '>' for input: " . json_encode($input));
            $this->assertStringNotContainsString('"', $output, "Output contains unescaped '\"' for input: " . json_encode($input));
            $this->assertStringNotContainsString("'", $output, "Output contains unescaped '\'' for input: " . json_encode($input));

            // Verify ampersands are escaped correctly (only as part of valid entities)
            $this->assertAmpersandsAreEscaped($output, $input);
        }
    }

    /**
     * Test Property 8: nl2br(sanitizeOutput()) converts newlines while escaping HTML.
     *
     * Validates: Requirements 13.5
     *
     * @test
     */
    public function testNl2brSanitizeOutputConvertsNewlinesWhileEscapingHtml(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $input = $this->generateRandomInput();
            $sanitized = sanitizeOutput($input);
            $output = nl2br($sanitized);

            // 1. Verify all HTML special characters from the original input are still fully escaped.
            // The only '<' and '>' characters allowed are inside <br> or <br /> tags.
            // We can replace "<br>" and "<br />" and "<br/>" and verify no other '<' or '>' remains.
            $cleaned = str_replace(['<br>', '<br />', '<br/>'], '', $output);
            $this->assertStringNotContainsString('<', $cleaned, "Output contains unescaped '<' outside of br tags for input: " . json_encode($input));
            $this->assertStringNotContainsString('>', $cleaned, "Output contains unescaped '>' outside of br tags for input: " . json_encode($input));
            $this->assertStringNotContainsString('"', $cleaned, "Output contains unescaped '\"' for input: " . json_encode($input));
            $this->assertStringNotContainsString("'", $cleaned, "Output contains unescaped '\'' for input: " . json_encode($input));

            // Verify ampersands are escaped correctly (only as part of valid entities or within br tags if they had any, but br tags have no ampersands)
            $this->assertAmpersandsAreEscaped($cleaned, $input);

            // 2. Verify all newlines in the sanitized output are preceded by a br tag.
            // In PHP's nl2br: \r\n, \n\r, \r, and \n are preceded by '<br />' or '<br>' or '<br/>'.
            // We find all newline sequences in the output and verify they are preceded by a br tag.
            preg_match_all('/(\r\n|\n\r|\r|\n)/', $output, $matches, PREG_OFFSET_CAPTURE);
            foreach ($matches[0] as $match) {
                $pos = $match[1];
                $before = substr($output, 0, $pos);
                $hasBr = false;
                foreach (['<br>', '<br />', '<br/>'] as $tag) {
                    if (str_ends_with($before, $tag)) {
                        $hasBr = true;
                        break;
                    }
                }
                $this->assertTrue($hasBr, "Newline sequence in output is not preceded by a <br> tag for input: " . json_encode($input) . " | Output: " . json_encode($output));
            }
        }
    }

    /**
     * Helper to verify all ampersands in the output are part of safe HTML entities.
     */
    private function assertAmpersandsAreEscaped(string $output, string $originalInput): void
    {
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
}
