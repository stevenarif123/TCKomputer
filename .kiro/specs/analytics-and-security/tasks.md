# Implementation Plan: Analytics & Security

## Overview

This plan implements the two-pillar Analytics & Security feature for the TC Komputer procedural PHP / PDO-MySQL storefront. It builds the new analytics computation layer (`config/analytics.php`) and security core (`config/security.php`) bottom-up: pure, property-testable helpers first, then the database-backed query/write functions that delegate arithmetic to them, then the admin-facing analytics page and dashboard enhancements, and finally the security hardening wiring (CSRF, rate limiting, safe redirects, secure session/headers, secret lock-down) into the existing endpoints.

All code is procedural PHP following existing conventions: helpers in `config/` includes, action endpoints in `actions/`, admin pages in `admin/`, integer Rupiah, `Asia/Makassar` (WITA) timezone, MySQL 5.7+ (utf8mb4), Chart.js on the frontend. Property-based tests mirror the existing `tests/Property/*` handwritten-iteration style (`private const ITERATIONS = 500;`).

## Tasks

- [ ] 1. Database migration and configuration foundation
  - [ ] 1.1 Create the analytics-and-security migration script
    - Create `migrate_analytics_security.php` that opens a PDO connection via `getDBConnection()`
    - Create `page_visits` and `rate_limit_attempts` tables using `CREATE TABLE IF NOT EXISTS` with the exact column/index definitions from the design data-model section
    - Execute statements inside try/catch; on failure stop before further statements and print an error naming the affected table and cause; never run ALTER/DROP/TRUNCATE/DELETE against pre-existing tables
    - Print, for each table, whether it was newly created or already existed
    - _Requirements: 16.1, 16.2, 16.3, 16.4, 16.5_

  - [ ] 1.2 Add visitor-salt and secure-cookie configuration to `config/db.php`
    - Read `APP_VISIT_SALT` from `.env` with a fixed app-constant fallback; add a `getVisitSalt()` accessor (in `config/db.php` or `config/analytics.php`)
    - Add `APP_VISIT_SALT` to `.env.example` with a placeholder value
    - _Requirements: 2.2, 2.6_

- [ ] 2. Analytics pure helper functions (`config/analytics.php`)
  - [ ] 2.1 Create `config/analytics.php` and implement `normalizeDateRange`
    - Implement `normalizeDateRange(?string $from, ?string $to, int $maxDays = 366, ?int $now = null): array` returning `['start_date','end_date']` valid `Y-m-d` WITA strings
    - Parse/validate inputs, swap reversed ranges, fall back to last 30 days on invalid/empty input, clamp span to `maxDays`, and clamp `end_date` to today (WITA)
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6, 5.7, 5.8_

  - [ ] 2.2 Write property test for date range normalization
    - **Property 2: Date range is always well-formed**
    - **Validates: Requirements 5.1, 5.2, 5.5, 5.7, 5.8**
    - Create `tests/Property/DateRangeNormalizationPropertyTest.php` generating garbage/reversed/future/null inputs and asserting valid `Y-m-d`, `start_date <= end_date`, span `<= maxDays`, `end_date <= today`

  - [ ] 2.3 Implement margin, AOV, and cancellation-rate helpers
    - Implement `computeMargin(int,int): int`, `computeMarginPercent(int,int): float` (0.0 when revenue==0, rounded to 2 dp), `computeAov(int,int): int` (intdiv, 0 when count==0), `computeCancellationRate(int,int): float` (in [0,1], 0.0 when total==0)
    - _Requirements: 6.3, 6.4, 6.6, 6.7, 7.1_

  - [ ] 2.4 Write property test for margin, AOV, and cancellation rate
    - **Property 3: Margin is never fabricated**
    - **Property 4: AOV and cancellation rate are total-safe**
    - **Validates: Requirements 6.3, 6.4, 6.6, 6.7**
    - Create `tests/Property/MarginPropertyTest.php` asserting `computeMargin == revenue-cost`, `computeMarginPercent` zero-only-on-zero-revenue, `computeAov` total-safe, and `computeCancellationRate` in [0,1]

  - [ ] 2.5 Implement `computeConversionRates`
    - Implement `computeConversionRates(int $visits, int $registrations, int $purchases): array` returning `registration_rate`, `purchase_rate`, `overall_rate` with zero-denominator yielding 0.0 and inputs treated as `max(0,n)`
    - _Requirements: 4.4, 4.5, 4.6, 4.7_

  - [ ] 2.6 Write property test for conversion rates
    - **Property 1: Funnel rates are bounded**
    - **Validates: Requirements 4.4, 4.5, 4.6, 4.7**
    - Create `tests/Property/ConversionRatePropertyTest.php` generating `visits >= registrations >= purchases >= 0` and asserting each rate is a float in [0,1] with zero denominators yielding exactly 0.0

  - [ ] 2.7 Implement `isLikelyBot` heuristic
    - Implement `isLikelyBot(?string $userAgent): bool` returning true for null/empty/whitespace-only and for case-insensitive known crawler substrings (bot, crawl, spider, slurp, bingpreview, headless), false otherwise; pure and deterministic
    - _Requirements: 3.1, 3.2, 3.3, 3.4_

  - [ ] 2.8 Write property test for bot heuristic
    - **Property 7: Bot heuristic is total and stable**
    - **Validates: Requirements 3.1, 3.2, 3.3, 3.4**
    - Create `tests/Property/BotHeuristicPropertyTest.php` asserting totality (bool for every input including null/empty→true), case-insensitivity, and idempotence

- [ ] 3. Checkpoint - pure analytics helpers
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 4. Analytics database query functions (`config/analytics.php`)
  - [ ] 4.1 Implement `getSalesSummary`
    - Query in-range completed (`order_status = 'selesai'`) orders; return revenue, gross_profit, margin_pct, order count, AOV, cancellation_rate by delegating arithmetic to the pure helpers; return non-negative integers and the empty-range zero result
    - _Requirements: 6.1, 6.2, 6.5, 6.8, 6.9_

  - [ ] 4.2 Implement `getProfitByProduct` with limit clamping
    - Join `order_items`→`orders`→`products`, compute units sold, revenue, cost (`purchase_price * units`), gross profit and margin via helpers for in-range completed orders; order by gross profit desc, units sold desc, product id asc; clamp limit to 1–1000 with default 10 for null/out-of-range
    - _Requirements: 7.1, 7.4, 7.5, 7.6, 7.7_

  - [ ] 4.3 Implement `getSalesByCategory` and `getSalesByArea`
    - Category breakdown: revenue, profit, quantity grouped by category for in-range completed orders, with null category grouped as "Uncategorized"
    - Area breakdown: revenue and count of distinct in-range completed orders grouped by shipping area; all currency/counts as non-negative integers
    - _Requirements: 7.2, 7.3, 7.4_

  - [ ] 4.4 Implement `getSalesTrend` and `getPromotionEffectiveness`
    - Trend: bucket completed-order revenue by `day`/`week`/`month` (default to `day` for any other value), one bucket per interval spanning the range ordered ascending, empty intervals as 0
    - Promotion effectiveness: per distinct promotion in `orders.applied_promotions` among in-range completed orders, return order count, summed `discount_amount`, and summed revenue as non-negative integers
    - _Requirements: 8.1, 8.2, 8.3, 8.4_

  - [ ] 4.5 Implement `getFunnelStats`
    - Count unique non-bot visits (`COUNT(DISTINCT visitor_hash) WHERE is_bot = 0`), registrations (`users.created_at` in range), and purchases (distinct in-range orders) inclusive in WITA; merge with `computeConversionRates`; exclude `is_bot = 1` rows from all counts
    - _Requirements: 4.1, 4.2, 4.3, 3.5_

  - [ ] 4.6 Implement `getStockHealth` with threshold clamping
    - Return low-stock products (`0 < stock <= threshold`, threshold clamped to 1–100 default 5) and out-of-stock products (`stock = 0`) from the current snapshot, range-independent; return empty result when none qualify; signal failure rather than partial/stale data when product data cannot be retrieved
    - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5_

  - [ ] 4.7 Implement `pruneVisits` retention housekeeping
    - Delete `page_visits` rows older than a configured horizon constrained to 1–3650 days, defaulting to 180 when unset/out of range
    - _Requirements: 2.7_

  - [ ] 4.8 Write unit tests for analytics query functions
    - Seed an in-memory/SQLite or fixture dataset and assert summary, product/category/area breakdowns, trend bucketing, promotion effectiveness, funnel counts, and stock-health classification against known totals
    - _Requirements: 6.1, 7.1, 7.2, 7.3, 8.1, 8.2, 9.1, 9.3_

- [ ] 5. Visit recording write path (`config/analytics.php`)
  - [ ] 5.1 Implement `recordVisit`
    - Implement `recordVisit(PDO $pdo, array $context, ?int $now = null): bool`: strip query string from `page_url` (truncate 255), classify bot via `isLikelyBot`, de-duplicate per `(session, page)` via a `$_SESSION['_counted_pages']` set, hash session id and `salt+ip+ua` to SHA-256, skip when salt is empty, insert one row; wrap everything in try/catch with `error_log` and return false on error/duplicate/skip, true on insert
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7, 2.1, 2.2, 2.3, 2.4, 2.5, 2.6_

  - [ ] 5.2 Write property test for visit dedup and non-throwing behavior
    - **Property 8: Visit recording is non-throwing and idempotent per session-page**
    - **Validates: Requirements 1.1, 1.2, 1.7**
    - Create `tests/Property/VisitDedupPropertyTest.php` exercising the dedup-key path and asserting at most one insert per `(session, page_url)` and that no exception propagates

- [ ] 6. Security core (`config/security.php`)
  - [ ] 6.1 Create `config/security.php` and implement rate-limit decision helpers
    - Implement `isRateLimited(int,int,int,int): bool` (true iff `failedCount >= maxAttempts` and `oldestAgeSeconds < windowSeconds`), `retryAfterSeconds(int,int): int` (`max(0, window - oldestAge)`, bounded to window), and `buildRateLimitKey(string,string,string): string` scoping by action + hashed identifier + IP
    - _Requirements: 11.2, 11.6, 11.7, 11.8_

  - [ ] 6.2 Write property test for rate-limit decision
    - **Property 5: Rate-limit decision is a pure threshold**
    - **Validates: Requirements 11.2, 11.7, 11.8**
    - Create `tests/Property/RateLimitDecisionPropertyTest.php` asserting the threshold predicate, `retryAfterSeconds` in `[0, window]`, and that zero recorded failures is never limited

  - [ ] 6.3 Implement DB-backed throttling functions
    - Implement `checkRateLimit` (rolling-window COUNT + oldest-age, delegating to `isRateLimited`/`retryAfterSeconds`, failing open + logging on store error), `recordAuthAttempt` (insert one failed-attempt row), `clearRateLimit` (delete key attempts on success), and `pruneRateLimit` (delete rows older than 86400s default)
    - _Requirements: 11.1, 11.3, 11.4, 11.5, 11.9, 11.10_

  - [ ] 6.4 Implement `configureSecureSession` and `applySecurityHeaders`
    - `configureSecureSession()` sets HttpOnly + SameSite=Lax before `session_start()`, adds Secure only over HTTPS
    - `applySecurityHeaders()` emits `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin` and a conservative CSP allowing Tailwind/Google Fonts/Chart.js; skip + log (no fatal) when output already started
    - _Requirements: 14.1, 14.2, 14.3, 14.4, 14.5, 14.6, 14.7_

- [ ] 7. Safe redirect helper (`config/helpers.php`)
  - [ ] 7.1 Implement `isSafeRedirectTarget`, `sanitizeRedirectTarget`, and `safeRedirect`
    - Add the three functions to `config/helpers.php`: treat single-`/` relative paths and same-host absolute URLs as safe (preserving query string and fragment); reject null/empty/whitespace, `>2048` chars, protocol-relative `//host`, foreign hosts, and non-http(s) schemes by returning the fallback (or `/` when fallback is itself unsafe); `safeRedirect` wraps the existing `redirect()`
    - _Requirements: 13.1, 13.2, 13.3, 13.4, 13.5, 13.6_

  - [ ] 7.2 Write property test for safe redirect
    - **Property 6: Redirect targets can never leave the host**
    - **Validates: Requirements 13.1, 13.2, 13.3**
    - Create `tests/Property/SafeRedirectPropertyTest.php` generating arbitrary targets/hosts and asserting the result is always relative or same-host, with foreign/`//host`/dangerous schemes collapsing to fallback

- [ ] 8. Checkpoint - core libraries complete
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 9. Wire visit tracking into the storefront
  - [ ] 9.1 Call `recordVisit` from `includes/header.php`
    - Require `config/analytics.php` and call `recordVisit($pdo, [...])` after `session_start()` with session id, IP, user-agent, request URI, and referer; ensure failures never affect rendering
    - _Requirements: 1.1, 1.7_

- [ ] 10. Admin analytics page and dashboard
  - [ ] 10.1 Create `admin/analytics.php`
    - Use the admin shell (`requireAdmin()` via admin-header so unauthenticated requests redirect to login with no analytics output); normalize `from`/`to` via `normalizeDateRange`; gather all metrics from `config/analytics.php` (no inline math); pass every stored value through `sanitizeOutput()`; render a safe empty/error state for any metric the engine cannot return
    - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5, 10.6, 10.7, 10.10_

  - [ ] 10.2 Render Chart.js visualizations on the analytics page
    - JSON-encode funnel/trend/category/area datasets into Chart.js canvases following the existing dashboard pattern
    - _Requirements: 10.6, 10.7_

  - [ ] 10.3 Add the "Analitik" nav item to `includes/admin-header.php`
    - Add a sidebar link to `admin/analytics.php` in the admin shell
    - _Requirements: 10.1_

  - [ ] 10.4 Enhance the dashboard (`admin/index.php`)
    - Replace inline queries with analytics-engine calls to show gross profit, AOV, cancellation rate (in [0,1]), and a compact Visits → Registrations → Purchases funnel that excludes bot rows from every stage
    - _Requirements: 10.8, 10.9_

- [ ] 11. Authentication endpoint hardening
  - [ ] 11.1 Add rate limiting to admin login
    - In `admin/login.php` / `config/admin-auth.php`, wrap the existing `adminLogin()` call with `checkRateLimit` (reject without credential verification when not allowed, returning retry-after), `recordAuthAttempt` on failure, and `clearRateLimit` on success; preserve existing `session_regenerate_id(true)`
    - _Requirements: 11.1, 11.3, 11.4, 11.5_

  - [ ] 11.2 Add CSRF validation and rate limiting to buyer auth/profile actions
    - In `actions/profile-login.php`, `actions/profile-register.php`, and `actions/profile-update.php`, call `validateCSRFToken()` before any DB read/write (JSON `{success:false}` + exit on missing/empty/mismatched token or absent session token); add generous-threshold rate limiting on login/register; keep the existing lenient validation and auto-login behavior unchanged
    - _Requirements: 12.1, 12.2, 12.3, 12.4, 12.5, 11.1, 11.3, 11.4, 11.5, 17.1, 17.2, 17.3, 17.4, 17.5_

  - [ ] 11.3 Replace raw referer redirects with `safeRedirect`
    - Update `actions/cart-add.php` (and any other action redirecting to `$_SERVER['HTTP_REFERER']`) to use `safeRedirect(... , 'index', ...)`
    - _Requirements: 13.1, 13.2_

  - [ ] 11.4 Ensure CSRF tokens are emitted in buyer auth/profile forms
    - Verify the buyer login/register/profile modal forms include a `csrf_token` field (via `generateCSRFToken`) so the new validation does not break the existing fetch flow
    - _Requirements: 12.1, 12.4_

  - [ ] 11.5 Write property test for CSRF parity on buyer endpoints
    - **Property 9: CSRF parity (regression guard)**
    - **Validates: Requirements 12.1, 12.3, 12.5**
    - Create `tests/Property/BuyerCSRFParityPropertyTest.php` reusing the `CSRFTokenPropertyTest` invariants: matching token accepted, any non-matching/empty token rejected, and validation fails when no session token exists

- [ ] 12. Wire secure session and headers into bootstrap
  - [ ] 12.1 Call `configureSecureSession` and `applySecurityHeaders` at bootstrap
    - Invoke `configureSecureSession()` before `session_start()` and `applySecurityHeaders()` before any output in the shared bootstrap (`config/db.php` / `includes/header.php` / `includes/admin-header.php`)
    - _Requirements: 14.1, 14.2, 14.3, 14.4, 14.5, 14.6_

- [ ] 13. Secret and tooling exposure lock-down
  - [ ] 13.1 Harden `.htaccess` to deny dangerous scripts and secrets
    - Add deny rules for `debug.php`, `debug_finfo_test.php`, `migrate_*.php`, `seed_*.php`, `clean_db_settings_prod.php`, `restore_env.php`, `.env`, and `database.sql` so public HTTP requests receive an access-denied response and source is never returned
    - Document an Nginx-equivalent deny snippet and the one-time-then-remove handling for `migrate_analytics_security.php`
    - _Requirements: 15.1, 15.2, 15.3, 15.4, 15.5, 15.6_

- [ ] 14. Final checkpoint
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional test sub-tasks and can be skipped for a faster MVP, but they encode the design's correctness properties and are recommended.
- Each task references specific granular requirement clauses for traceability.
- Property tests follow the existing handwritten-iteration PHPUnit style in `tests/Property/*` (`private const ITERATIONS = 500;`).
- Property tests target pure functions and run without a database; query-function tests use seeded fixtures.
- Checkpoints provide incremental validation at natural boundaries (pure helpers, core libraries, full wiring).

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1", "1.2", "2.1", "2.3", "2.5", "2.7", "6.1", "7.1"] },
    { "id": 1, "tasks": ["2.2", "2.4", "2.6", "2.8", "6.2", "7.2", "4.1", "4.2", "4.3", "4.4", "4.5", "4.6", "4.7", "5.1", "6.3", "6.4"] },
    { "id": 2, "tasks": ["4.8", "5.2", "9.1", "10.1", "10.3", "11.1", "11.3", "11.4", "12.1", "13.1"] },
    { "id": 3, "tasks": ["10.2", "10.4", "11.2"] },
    { "id": 4, "tasks": ["11.5"] }
  ]
}
```
