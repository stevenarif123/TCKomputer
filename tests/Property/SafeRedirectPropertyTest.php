<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/helpers.php';

use PHPUnit\Framework\TestCase;

/**
 * Property-Based Test for isSafeRedirectTarget() and sanitizeRedirectTarget().
 *
 * **Validates: Requirements 13.1, 13.2, 13.3**
 *
 * Property 6: Redirect targets can never leave the host.
 * sanitizeRedirectTarget always returns a relative or same-host destination.
 * Foreign hosts, protocol-relative URLs, and dangerous schemes → fallback.
 */
class SafeRedirectPropertyTest extends TestCase
{
    private const ITERATIONS = 500;

    private const ALLOWED_HOST = 'tckomputer.local';

    /** Generate a known-safe relative path. */
    private function safeRelative(): string
    {
        $paths = ['/', '/index', '/products', '/cart', '/checkout', '/product-detail?id=1'];
        return $paths[mt_rand(0, count($paths) - 1)];
    }

    /** Generate a known-safe absolute URL on the allowed host. */
    private function safeAbsolute(): string
    {
        $schemes = ['http', 'https'];
        $scheme  = $schemes[mt_rand(0, 1)];
        $paths   = ['/', '/index', '/products'];
        return $scheme . '://' . self::ALLOWED_HOST . $paths[mt_rand(0, 2)];
    }

    /** Generate a known-unsafe target. */
    private function unsafeTarget(): string
    {
        $cases = [
            null,
            '',
            '   ',
            'javascript:alert(1)',
            'data:text/html,<script>alert(1)</script>',
            'vbscript:msgbox(1)',
            'file:///etc/passwd',
            '//evil.com/path',
            'https://evil.com/path',
            'http://attacker.example.com/',
            str_repeat('a', 2049), // too long
        ];
        return $cases[mt_rand(0, count($cases) - 1)] ?? '';
    }

    /**
     * Property: Safe relative paths are accepted as-is.
     * Validates: Requirement 13.1
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function safeRelativePathsAreAccepted(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $target = $this->safeRelative();
            $this->assertTrue(
                isSafeRedirectTarget($target, self::ALLOWED_HOST),
                "Safe relative path '$target' should be accepted (iter $i)"
            );
        }
    }

    /**
     * Property: Safe absolute same-host URLs are accepted.
     * Validates: Requirement 13.1
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function safeAbsoluteSameHostUrlsAreAccepted(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $target = $this->safeAbsolute();
            $this->assertTrue(
                isSafeRedirectTarget($target, self::ALLOWED_HOST),
                "Same-host URL '$target' should be accepted (iter $i)"
            );
        }
    }

    /**
     * Property: Unsafe targets are rejected.
     * Validates: Requirement 13.2
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function unsafeTargetsAreRejected(): void
    {
        $cases = [
            null,
            '',
            '   ',
            'javascript:alert(1)',
            'data:text/html,<script>',
            'vbscript:msgbox(1)',
            'file:///etc/passwd',
            '//evil.com/path',
            'https://evil.com/path',
            'http://attacker.example.com/',
        ];

        foreach ($cases as $bad) {
            $this->assertFalse(
                isSafeRedirectTarget($bad, self::ALLOWED_HOST),
                "Unsafe target " . var_export($bad, true) . " should be rejected"
            );
        }
    }

    /**
     * Property: sanitizeRedirectTarget never returns a foreign-host URL.
     * Validates: Requirement 13.3
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function sanitizeAlwaysReturnsSameHostOrRelative(): void
    {
        $fallback = 'index';

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            // Mix of safe and unsafe targets
            $target = mt_rand(0, 1) ? $this->safeRelative() : $this->unsafeTarget();

            $result = sanitizeRedirectTarget($target, self::ALLOWED_HOST, $fallback);

            // Result must not be an absolute URL pointing to a foreign host
            if (preg_match('#^https?://#i', $result)) {
                $host = parse_url($result, PHP_URL_HOST);
                $this->assertSame(
                    strtolower(self::ALLOWED_HOST),
                    strtolower((string)$host),
                    "sanitizeRedirectTarget returned foreign host '$host' (iter $i, target=" . var_export($target, true) . ")"
                );
            }

            // Result must not start with '//' (protocol-relative)
            $this->assertStringNotContainsString(
                '//',
                substr($result, 0, 2),
                "sanitizeRedirectTarget must not return protocol-relative URL (iter $i)"
            );
        }
    }

    /**
     * Property: Dangerous schemes always collapse to fallback.
     * Validates: Requirement 13.2
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function dangerousSchemesCollapseToFallback(): void
    {
        $dangerous = [
            'javascript:alert(1)',
            'data:text/html,<h1>XSS</h1>',
            'vbscript:msgbox("pwned")',
            'file:///etc/passwd',
        ];
        $fallback = '/safe-fallback';

        foreach ($dangerous as $scheme) {
            $result = sanitizeRedirectTarget($scheme, self::ALLOWED_HOST, $fallback);
            $this->assertSame(
                $fallback,
                $result,
                "Dangerous scheme '$scheme' must produce fallback"
            );
        }
    }

    /**
     * Property: query strings and fragments are preserved for safe targets.
     * Validates: Requirements 13.4, 13.5
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function querystringAndFragmentArePreservedForSafeTargets(): void
    {
        $cases = [
            '/products?category=laptop#results',
            '/product-detail?id=42&ref=home',
            '/checkout?step=2#payment',
        ];

        foreach ($cases as $target) {
            $result = sanitizeRedirectTarget($target, self::ALLOWED_HOST);
            $this->assertSame($target, $result, "Query string/fragment should be preserved for safe target '$target'");
        }
    }
}
