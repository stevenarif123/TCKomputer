<?php
/**
 * Security Core
 *
 * Pillar B — Brute-force throttling, secure session configuration,
 * and security response headers for the TC Komputer storefront.
 *
 * Pure helpers (property-testable, no PDO):
 *   isRateLimited, retryAfterSeconds, buildRateLimitKey
 *
 * DB-backed throttling:
 *   checkRateLimit, recordAuthAttempt, clearRateLimit, pruneRateLimit
 *
 * Hardening primitives:
 *   configureSecureSession, applySecurityHeaders
 *
 * Design notes:
 * - The limiter fails OPEN on DB error (availability over security for brute-force).
 * - Buyer-friendly threshold: generous attempt count over short window,
 *   scoped per (action + identifier hash + IP) so one IP cannot lock out a different buyer.
 * - configureSecureSession() omits the Secure flag on plain HTTP so Laragon dev works.
 * - applySecurityHeaders() skips gracefully if output already started.
 */

// ═══════════════════════════════════════════════════════════════════════════════
// PURE DECISION HELPERS — no PDO, fully deterministic, property-testable
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Decide whether a request should be rate-limited.
 *
 * Returns true iff:
 *   failedCount >= maxAttempts  AND  oldestAgeSeconds < windowSeconds
 *
 * (i.e. there are enough failures AND the oldest one is still inside the window).
 *
 * @param int $failedCount       Number of failed attempts in the rolling window.
 * @param int $maxAttempts       Maximum allowed failures before throttling.
 * @param int $windowSeconds     Rolling window length in seconds.
 * @param int $oldestAgeSeconds  Age of the oldest counted attempt in seconds.
 * @pure
 */
function isRateLimited(int $failedCount, int $maxAttempts, int $windowSeconds, int $oldestAgeSeconds): bool
{
    return $failedCount >= $maxAttempts && $oldestAgeSeconds < $windowSeconds;
}

/**
 * Compute how many seconds the client should wait before retrying.
 *
 * Returns max(0, windowSeconds - oldestAttemptAgeSeconds), bounded to [0, windowSeconds].
 *
 * @param int $oldestAttemptAgeSeconds  Age of the oldest attempt in the window.
 * @param int $windowSeconds            Rolling window length.
 * @pure
 */
function retryAfterSeconds(int $oldestAttemptAgeSeconds, int $windowSeconds): int
{
    $wait = $windowSeconds - $oldestAttemptAgeSeconds;
    return max(0, min($windowSeconds, $wait));
}

/**
 * Build a rate-limit key scoped by action, hashed identifier, and client IP.
 *
 * The identifier (e.g. username/email) is hashed so it does not leak to DB logs.
 * The key uniqueness prevents one IP from locking out a different buyer.
 *
 * @param string $action      e.g. 'login', 'register'
 * @param string $identifier  Username, email, or phone (hashed before storage).
 * @param string $ip          Client IP address.
 * @pure
 */
function buildRateLimitKey(string $action, string $identifier, string $ip): string
{
    $idHash = hash('sha256', $identifier);
    return $action . ':' . substr($idHash, 0, 16) . ':' . $ip;
}

// ═══════════════════════════════════════════════════════════════════════════════
// DB-BACKED THROTTLING
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Check whether a request should be rate-limited using the rolling-window store.
 *
 * On success returns:
 *   ['allowed' => true/false, 'retry_after' => int, 'remaining' => int]
 *
 * On DB error: fails OPEN (allowed = true) and logs the error (Req 11.9).
 *
 * @param int $maxAttempts   Maximum failed attempts before throttling (default 5).
 * @param int $windowSeconds Rolling window in seconds (default 900 = 15 min).
 */
function checkRateLimit(
    PDO    $pdo,
    string $action,
    string $key,
    int    $maxAttempts   = 5,
    int    $windowSeconds = 900
): array {
    try {
        $stmt = $pdo->prepare(
            "SELECT
                COUNT(*) AS c,
                COALESCE(TIMESTAMPDIFF(SECOND, MIN(created_at), NOW()), 0) AS oldest_age
             FROM rate_limit_attempts
             WHERE rate_key = ? AND action = ?
               AND created_at > (NOW() - INTERVAL ? SECOND)"
        );
        $stmt->execute([$key, $action, $windowSeconds]);
        $row = $stmt->fetch() ?: ['c' => 0, 'oldest_age' => 0];

        $failed    = (int)$row['c'];
        $oldestAge = (int)$row['oldest_age'];
        $limited   = isRateLimited($failed, $maxAttempts, $windowSeconds, $oldestAge);

        return [
            'allowed'     => !$limited,
            'retry_after' => $limited ? retryAfterSeconds($oldestAge, $windowSeconds) : 0,
            'remaining'   => max(0, $maxAttempts - $failed),
        ];
    } catch (Throwable $e) {
        // Fail open — throttle store unavailable must not lock out users (Req 11.9)
        error_log('checkRateLimit failed (fail open): ' . $e->getMessage());
        return ['allowed' => true, 'retry_after' => 0, 'remaining' => $maxAttempts];
    }
}

/**
 * Record one failed authentication attempt for the given key.
 *
 * @param string $action  e.g. 'login', 'register'
 * @param string $key     Rate-limit key from buildRateLimitKey().
 */
function recordAuthAttempt(PDO $pdo, string $action, string $key): void
{
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO rate_limit_attempts (action, rate_key, created_at) VALUES (?, ?, NOW())"
        );
        $stmt->execute([$action, $key]);
    } catch (Throwable $e) {
        error_log('recordAuthAttempt failed: ' . $e->getMessage());
    }
}

/**
 * Clear all recorded attempts for a key after a successful authentication.
 *
 * @param string $action  e.g. 'login', 'register'
 * @param string $key     Rate-limit key from buildRateLimitKey().
 */
function clearRateLimit(PDO $pdo, string $action, string $key): void
{
    try {
        $stmt = $pdo->prepare(
            "DELETE FROM rate_limit_attempts WHERE action = ? AND rate_key = ?"
        );
        $stmt->execute([$action, $key]);
    } catch (Throwable $e) {
        error_log('clearRateLimit failed: ' . $e->getMessage());
    }
}

/**
 * Delete attempt rows older than the retention window (housekeeping).
 *
 * @param int $olderThanSeconds Rows older than this many seconds are deleted (default 86400 = 24h).
 */
function pruneRateLimit(PDO $pdo, int $olderThanSeconds = 86400): void
{
    try {
        $stmt = $pdo->prepare(
            "DELETE FROM rate_limit_attempts WHERE created_at < (NOW() - INTERVAL ? SECOND)"
        );
        $stmt->execute([$olderThanSeconds]);
    } catch (Throwable $e) {
        error_log('pruneRateLimit failed: ' . $e->getMessage());
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// HARDENING PRIMITIVES
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Configure secure session cookie parameters.
 *
 * Must be called BEFORE session_start() and before any output.
 *
 * Sets: HttpOnly=true, SameSite=Lax.
 * Sets: Secure=true ONLY when the request is served over HTTPS (Req 14.2, 14.3).
 */
function configureSecureSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        // Session already started — cannot configure
        return;
    }

    $isHttps = (
        (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ||
        (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) ||
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    );

    session_set_cookie_params([
        'lifetime' => 0,           // session cookie (expires on browser close)
        'path'     => '/',
        'domain'   => '',          // current domain only
        'secure'   => $isHttps,    // Secure only on HTTPS
        'httponly' => true,        // HttpOnly always (Req 14.1)
        'samesite' => 'Lax',       // SameSite=Lax always (Req 14.1)
    ]);
}

/**
 * Emit baseline security response headers.
 *
 * Must be called before any response body output.
 * Skips gracefully (with error_log) if output already started (Req 14.7).
 *
 * Headers set (Req 14.4):
 *   X-Frame-Options: DENY
 *   X-Content-Type-Options: nosniff
 *   Referrer-Policy: strict-origin-when-cross-origin
 *
 * CSP (Req 14.5) allows:
 *   - Tailwind CDN (cdn.tailwindcss.com)
 *   - Google Fonts (fonts.googleapis.com, fonts.gstatic.com)
 *   - Chart.js (cdn.jsdelivr.net)
 *   - cdnjs.cloudflare.com (Cropper.js used on admin)
 *   - 'self' for scripts/styles hosted locally
 *   - 'unsafe-inline' for styles (Tailwind's JIT generates inline styles)
 */
function applySecurityHeaders(): void
{
    if (headers_sent($file, $line)) {
        error_log("applySecurityHeaders: headers already sent in {$file}:{$line}, skipping.");
        return;
    }

    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // Content Security Policy
    $csp = implode('; ', [
        "default-src 'self'",
        "script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com",
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.tailwindcss.com https://cdnjs.cloudflare.com",
        "font-src 'self' https://fonts.gstatic.com",
        "img-src 'self' data: https:",
        "connect-src 'self'",
        "frame-ancestors 'none'",
        "base-uri 'self'",
        "form-action 'self'",
    ]);
    header("Content-Security-Policy: {$csp}");

    // Start output buffering to strip HTML comments from final output
    if (!in_array('cleanHtmlComments', ob_list_handlers())) {
        ob_start('cleanHtmlComments');
    }
}

/**
 * Strip HTML comments from the output buffer before it is sent to the browser.
 * This keeps comments in source code but prevents them from leaking to inspect element.
 *
 * @param string $buffer The HTML output buffer.
 * @return string The cleaned HTML output.
 */
function cleanHtmlComments(string $buffer): string
{
    return preg_replace('/<!--(?!\[if)[\s\S]*?-->/', '', $buffer);
}

