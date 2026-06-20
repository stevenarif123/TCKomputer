<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/helpers.php';

use PHPUnit\Framework\TestCase;

/**
 * Property-Based Test for parsePopularSearches().
 *
 * **Validates: Requirements 3.6**
 *
 * Property 7: Popular Search Source Integrity
 * For any comma-separated popular search source string, rendered/searchable
 * tokens are trimmed, non-empty, source-backed, and source ordered.
 */
class HomepagePopularSearchSourceIntegrityPropertyTest extends TestCase
{
    private const ITERATIONS = 500;

    /**
     * @test
     */
    public function popularSearchTokensAreTrimmedNonEmptySourceBackedAndOrdered(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $sourceTokens = $this->generateSourceTokens();
            $source = implode(',', $sourceTokens);
            $parsed = parsePopularSearches($source);

            $expected = [];
            foreach ($sourceTokens as $token) {
                $trimmed = trim($token);
                if ($trimmed !== '') {
                    $expected[] = $trimmed;
                }
            }

            $this->assertSame(
                $expected,
                $parsed,
                'Parsed popular searches must preserve trimmed, non-empty source token order for source: ' . json_encode($source)
            );

            foreach ($parsed as $index => $token) {
                $this->assertSame(trim($token), $token, 'Popular search token must be trimmed.');
                $this->assertNotSame('', $token, 'Popular search token must be non-empty.');
                $this->assertSame($expected[$index], $token, 'Popular search token must be source-backed.');
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function generateSourceTokens(): array
    {
        $count = mt_rand(0, 30);
        $tokens = [];

        for ($i = 0; $i < $count; $i++) {
            $tokens[] = $this->randomPadding() . $this->randomTokenBody() . $this->randomPadding();
        }

        return $tokens;
    }

    private function randomTokenBody(): string
    {
        if (mt_rand(0, 4) === 0) {
            return '';
        }

        $length = mt_rand(1, 40);
        $pool = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789 -_/&+.#';
        $token = '';

        for ($i = 0; $i < $length; $i++) {
            $token .= $pool[mt_rand(0, strlen($pool) - 1)];
        }

        return $token;
    }

    private function randomPadding(): string
    {
        return str_repeat(' ', mt_rand(0, 4))
            . str_repeat("\t", mt_rand(0, 2))
            . str_repeat("\n", mt_rand(0, 1));
    }
}
