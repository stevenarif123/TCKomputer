<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/analytics.php';

use PHPUnit\Framework\TestCase;

/**
 * Property-Based Test for isLikelyBot().
 *
 * **Validates: Requirements 3.1, 3.2, 3.3, 3.4**
 *
 * Property 7: Bot heuristic is total and stable.
 * Returns a bool for every input (null/empty → true).
 * Case-insensitive. Idempotent (same input → same output).
 */
class BotHeuristicPropertyTest extends TestCase
{
    private const ITERATIONS = 500;

    /**
     * Property: isLikelyBot always returns a bool (total function).
     * Validates: Requirements 3.1, 3.2, 3.3
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function alwaysReturnsBool(): void
    {
        $inputs = [
            null, '', '   ', 'Mozilla/5.0', 'Googlebot/2.1', 'Bingbot', 'normal browser',
        ];
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $ua = $inputs[mt_rand(0, count($inputs) - 1)];
            $result = isLikelyBot($ua);
            $this->assertIsBool($result, "isLikelyBot must return bool for any input (iter $i)");
        }
    }

    /**
     * Property: null and empty/whitespace user-agents are always bots (Req 3.1).
     * Validates: Requirement 3.1
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function nullAndEmptyAreAlwaysBots(): void
    {
        $empties = [null, '', '   ', "\t", "\n", "\r\n", "  \t  "];
        foreach ($empties as $empty) {
            $this->assertTrue(
                isLikelyBot($empty),
                "null/empty user-agent should always be classified as bot: " . var_export($empty, true)
            );
        }
    }

    /**
     * Property: Known crawler tokens are detected case-insensitively (Req 3.2).
     * Validates: Requirement 3.2
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function knownCrawlerTokensDetectedCaseInsensitively(): void
    {
        $tokens = ['bot', 'crawl', 'spider', 'slurp', 'bingpreview', 'headless'];
        $casings = [
            fn(string $s) => strtolower($s),
            fn(string $s) => strtoupper($s),
            fn(string $s) => ucfirst($s),
        ];

        foreach ($tokens as $token) {
            foreach ($casings as $casing) {
                $ua = 'MyAgent/' . $casing($token) . '/1.0';
                $this->assertTrue(
                    isLikelyBot($ua),
                    "Token '$token' in UA '$ua' should be classified as bot"
                );
            }
        }
    }

    /**
     * Property: Normal browser user-agents are not classified as bots (Req 3.3).
     * Validates: Requirement 3.3
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function normalBrowsersAreNotBots(): void
    {
        $browsers = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (X11; Linux x86_64; rv:109.0) Gecko/20100101 Firefox/121.0',
        ];

        foreach ($browsers as $ua) {
            $this->assertFalse(
                isLikelyBot($ua),
                "Normal browser UA should not be classified as bot: '$ua'"
            );
        }
    }

    /**
     * Property: isLikelyBot is idempotent (Req 3.4).
     * Same input always produces same output across multiple calls.
     * Validates: Requirement 3.4
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function isIdempotent(): void
    {
        $cases = [
            null, '', 'Mozilla/5.0 Chrome', 'Googlebot/2.1', 'MyCustomBot/1.0',
        ];

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $ua = $cases[mt_rand(0, count($cases) - 1)];

            $result1 = isLikelyBot($ua);
            $result2 = isLikelyBot($ua);
            $result3 = isLikelyBot($ua);

            $this->assertSame($result1, $result2, "isLikelyBot must be idempotent (1st vs 2nd call, iter $i)");
            $this->assertSame($result2, $result3, "isLikelyBot must be idempotent (2nd vs 3rd call, iter $i)");
        }
    }
}
