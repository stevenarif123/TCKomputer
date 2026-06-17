<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/helpers.php';

use PHPUnit\Framework\TestCase;

/**
 * Property-Based Test for generateSlug()
 *
 * **Validates: Requirements 17.1, 17.2, 17.3, 17.4, 17.5**
 *
 * Property 10: Slug Generation Correctness
 * For ANY arbitrary string input, the generated slug must satisfy ALL of:
 * - Output is entirely lowercase (no uppercase characters)
 * - Output contains only characters matching [a-z0-9-]
 * - Output contains no consecutive hyphens (--)
 * - Output has no leading hyphen
 * - Output has no trailing hyphen
 * - Output length is at most 255 characters
 */
class GenerateSlugPropertyTest extends TestCase
{
    /**
     * Number of random iterations per test method.
     */
    private const ITERATIONS = 500;

    /**
     * Generate a random unicode string of variable length.
     */
    private function generateRandomString(int $maxLength = 300): string
    {
        $length = mt_rand(0, $maxLength);

        if ($length === 0) {
            return '';
        }

        $chars = '';
        for ($i = 0; $i < $length; $i++) {
            $type = mt_rand(0, 6);
            switch ($type) {
                case 0: // ASCII lowercase
                    $chars .= chr(mt_rand(97, 122));
                    break;
                case 1: // ASCII uppercase
                    $chars .= chr(mt_rand(65, 90));
                    break;
                case 2: // Digits
                    $chars .= chr(mt_rand(48, 57));
                    break;
                case 3: // Spaces and common punctuation
                    $pool = " \t\n!@#\$%^&*()_+-=[]{}|;':\",./<>?";
                    $chars .= $pool[mt_rand(0, strlen($pool) - 1)];
                    break;
                case 4: // Unicode characters (2-byte UTF-8)
                    $codepoint = mt_rand(0x00C0, 0x024F); // Latin Extended
                    $chars .= mb_chr($codepoint, 'UTF-8');
                    break;
                case 5: // Hyphens and underscores (edge cases)
                    $pool = "---___...   ";
                    $chars .= $pool[mt_rand(0, strlen($pool) - 1)];
                    break;
                case 6: // CJK characters (3-byte UTF-8)
                    $codepoint = mt_rand(0x4E00, 0x4FFF);
                    $chars .= mb_chr($codepoint, 'UTF-8');
                    break;
            }
        }

        return $chars;
    }

    /**
     * Generate specific edge-case strings for thorough testing.
     *
     * @return array<string>
     */
    private function edgeCaseStrings(): array
    {
        return [
            '',                                      // Empty string
            '   ',                                   // Only spaces
            '---',                                   // Only hyphens
            '!!!@@@###',                             // Only symbols
            'UPPERCASE',                             // All uppercase
            'lowercase',                             // All lowercase
            'MiXeD CaSe',                            // Mixed case
            'hello world',                           // Simple space
            'hello---world',                         // Multiple hyphens
            '-leading',                              // Leading hyphen
            'trailing-',                             // Trailing hyphen
            '-both-',                                // Both leading and trailing
            '  spaced  out  ',                       // Multiple spaces
            "tab\there",                             // Tab character
            "new\nline",                             // Newline
            str_repeat('a', 300),                    // Very long single-word
            str_repeat('ab ', 150),                  // Very long multi-word
            'Héllo Wörld',                           // Accented characters
            '日本語テスト',                             // Japanese
            '1234567890',                            // Only digits
            'a',                                     // Single char
            '-',                                     // Single hyphen
            'a-b-c-d-e',                             // Already valid slug
            'Product Name (2024) - NEW!',            // Typical product name
            'Laptop   Asus   ROG---Strix',           // Multiple separators
            str_repeat('hello-world ', 30),          // Long with word boundaries
        ];
    }

    /**
     * Property: Output is entirely lowercase (no uppercase characters present).
     * Validates: Requirement 17.1
     *
     * @test
     */
    public function slugOutputIsAlwaysLowercase(): void
    {
        // Test edge cases
        foreach ($this->edgeCaseStrings() as $input) {
            $slug = generateSlug($input);
            $this->assertSame(
                strtolower($slug),
                $slug,
                "Slug contains uppercase characters for input: " . json_encode($input)
            );
        }

        // Test random inputs
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $input = $this->generateRandomString();
            $slug = generateSlug($input);
            $this->assertSame(
                strtolower($slug),
                $slug,
                "Slug contains uppercase characters for random input: " . json_encode($input)
            );
        }
    }

    /**
     * Property: Output contains only valid slug characters [a-z0-9-].
     * Validates: Requirement 17.2
     *
     * @test
     */
    public function slugOutputContainsOnlyValidCharacters(): void
    {
        // Test edge cases
        foreach ($this->edgeCaseStrings() as $input) {
            $slug = generateSlug($input);
            if ($slug !== '') {
                $this->assertMatchesRegularExpression(
                    '/^[a-z0-9\-]+$/',
                    $slug,
                    "Slug contains invalid characters for input: " . json_encode($input)
                );
            }
        }

        // Test random inputs
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $input = $this->generateRandomString();
            $slug = generateSlug($input);
            if ($slug !== '') {
                $this->assertMatchesRegularExpression(
                    '/^[a-z0-9\-]+$/',
                    $slug,
                    "Slug contains invalid characters for random input: " . json_encode($input)
                );
            }
        }
    }

    /**
     * Property: Output contains no consecutive hyphens (--).
     * Validates: Requirement 17.3
     *
     * @test
     */
    public function slugOutputHasNoConsecutiveHyphens(): void
    {
        // Test edge cases
        foreach ($this->edgeCaseStrings() as $input) {
            $slug = generateSlug($input);
            $this->assertStringNotContainsString(
                '--',
                $slug,
                "Slug contains consecutive hyphens for input: " . json_encode($input)
            );
        }

        // Test random inputs
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $input = $this->generateRandomString();
            $slug = generateSlug($input);
            $this->assertStringNotContainsString(
                '--',
                $slug,
                "Slug contains consecutive hyphens for random input: " . json_encode($input)
            );
        }
    }

    /**
     * Property: Output has no leading hyphen.
     * Validates: Requirement 17.4
     *
     * @test
     */
    public function slugOutputHasNoLeadingHyphen(): void
    {
        // Test edge cases
        foreach ($this->edgeCaseStrings() as $input) {
            $slug = generateSlug($input);
            if ($slug !== '') {
                $this->assertNotEquals(
                    '-',
                    $slug[0],
                    "Slug has leading hyphen for input: " . json_encode($input)
                );
            }
        }

        // Test random inputs
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $input = $this->generateRandomString();
            $slug = generateSlug($input);
            if ($slug !== '') {
                $this->assertNotEquals(
                    '-',
                    $slug[0],
                    "Slug has leading hyphen for random input: " . json_encode($input)
                );
            }
        }
    }

    /**
     * Property: Output has no trailing hyphen.
     * Validates: Requirement 17.4
     *
     * @test
     */
    public function slugOutputHasNoTrailingHyphen(): void
    {
        // Test edge cases
        foreach ($this->edgeCaseStrings() as $input) {
            $slug = generateSlug($input);
            if ($slug !== '') {
                $this->assertNotEquals(
                    '-',
                    $slug[strlen($slug) - 1],
                    "Slug has trailing hyphen for input: " . json_encode($input)
                );
            }
        }

        // Test random inputs
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $input = $this->generateRandomString();
            $slug = generateSlug($input);
            if ($slug !== '') {
                $this->assertNotEquals(
                    '-',
                    $slug[strlen($slug) - 1],
                    "Slug has trailing hyphen for random input: " . json_encode($input)
                );
            }
        }
    }

    /**
     * Property: Output length is at most 255 characters.
     * Validates: Requirement 17.5
     *
     * @test
     */
    public function slugOutputIsAtMost255Characters(): void
    {
        // Test edge cases
        foreach ($this->edgeCaseStrings() as $input) {
            $slug = generateSlug($input);
            $this->assertLessThanOrEqual(
                255,
                strlen($slug),
                "Slug exceeds 255 characters for input: " . json_encode($input)
            );
        }

        // Test random inputs
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $input = $this->generateRandomString(500); // Generate potentially long strings
            $slug = generateSlug($input);
            $this->assertLessThanOrEqual(
                255,
                strlen($slug),
                "Slug exceeds 255 characters for random input of length " . strlen($input)
            );
        }
    }

    /**
     * Combined property: ALL properties hold simultaneously for every input.
     * This catches any interactions between properties that individual tests might miss.
     *
     * @test
     */
    public function allSlugPropertiesHoldSimultaneously(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $input = $this->generateRandomString(500);
            $slug = generateSlug($input);

            // Property 1: Lowercase
            $this->assertSame(
                strtolower($slug),
                $slug,
                "Combined check - uppercase found for input: " . json_encode($input)
            );

            // Property 2: Valid characters only
            if ($slug !== '') {
                $this->assertMatchesRegularExpression(
                    '/^[a-z0-9\-]+$/',
                    $slug,
                    "Combined check - invalid chars for input: " . json_encode($input)
                );
            }

            // Property 3: No consecutive hyphens
            $this->assertStringNotContainsString(
                '--',
                $slug,
                "Combined check - consecutive hyphens for input: " . json_encode($input)
            );

            // Property 4: No leading hyphen
            if ($slug !== '') {
                $this->assertNotEquals(
                    '-',
                    $slug[0],
                    "Combined check - leading hyphen for input: " . json_encode($input)
                );
            }

            // Property 5: No trailing hyphen
            if ($slug !== '') {
                $this->assertNotEquals(
                    '-',
                    $slug[strlen($slug) - 1],
                    "Combined check - trailing hyphen for input: " . json_encode($input)
                );
            }

            // Property 6: Max 255 characters
            $this->assertLessThanOrEqual(
                255,
                strlen($slug),
                "Combined check - exceeds 255 chars for input: " . json_encode($input)
            );
        }
    }
}
