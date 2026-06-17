# Requirements Document

## Introduction

This feature adds two complementary capabilities to the TC Komputer storefront (a procedural PHP / PDO-MySQL e-commerce site): a full store analytics layer with conversion-funnel visit tracking (Pillar A) and a set of security-hardening controls (Pillar B). The two pillars share this spec because they touch the same shared includes and admin shell, and because the new visit-tracking write path is itself an attack surface that must be hardened as it is introduced.

Pillar A promotes ad-hoc dashboard queries into a reusable analytics layer (`config/analytics.php`) and a dedicated admin page (`admin/analytics.php`) with a selectable date range. It computes revenue and gross profit (using `products.purchase_price` vs `selling_price`), best-selling and most-profitable products, sales by category and Toraja shipping area, daily/weekly/monthly trends, promotion effectiveness, average order value, cancellation rate, and stock health. It introduces a lightweight `page_visits` table to expose the Visits → Registrations → Purchases conversion funnel.

Pillar B closes concrete audit findings: brute-force throttling on admin and buyer authentication, CSRF validation on the buyer auth/profile actions that lack it, an open-redirect fix for referer-based redirects, secure session-cookie flags, baseline security response headers, and lock-down of dangerous root-level scripts and secrets. The design honors an explicitly accepted business decision: buyer onboarding stays low-friction (no mandatory email/OTP verification, no captcha, lenient 6-character passwords, auto-login after registration). Hardening protects the store and its data, never the buyer's convenience.

All new logic follows existing conventions: procedural helpers in `config/` includes, action endpoints in `actions/`, admin pages in `admin/`, integer Rupiah, `Asia/Makassar` (WITA) timezone, MySQL 5.7+ (utf8mb4), and Chart.js on the frontend. Computational helpers are written as pure functions over plain arrays so the existing PHPUnit property suite can target them directly.

## Glossary

- **Analytics_Engine**: The analytics computation layer (`config/analytics.php`) whose query functions gather rows and whose pure helpers perform arithmetic.
- **Visit_Tracker**: The `recordVisit` write-path function called from the shared storefront header to record page visits.
- **Analytics_Page**: The admin analytics page (`admin/analytics.php`) that presents Pillar-A metrics with date-range filtering and Chart.js charts.
- **Dashboard**: The existing admin dashboard (`admin/index.php`) that surfaces headline figures.
- **Date_Normalizer**: The pure `normalizeDateRange` function that clamps a requested date range to a safe inclusive range.
- **Rate_Limiter**: The brute-force throttling subsystem (`config/security.php`) comprising `checkRateLimit`, `recordAuthAttempt`, `clearRateLimit`, and the pure decision helpers `isRateLimited` / `retryAfterSeconds`.
- **Redirect_Guard**: The open-redirect-safe redirect subsystem (`safeRedirect` / `sanitizeRedirectTarget` / `isSafeRedirectTarget`) in `config/helpers.php`.
- **CSRF_Validator**: The existing `validateCSRFToken` helper applied to buyer auth/profile actions.
- **Session_Configurator**: The `configureSecureSession` function that sets secure session cookie parameters before session start.
- **Header_Manager**: The `applySecurityHeaders` function that emits baseline security response headers.
- **DateRange**: An in-memory structure `{start_date, end_date}` of inclusive `Y-m-d` WITA dates with `start_date <= end_date`.
- **FunnelStats**: A structure containing `visits`, `registrations`, `purchases`, and the three conversion rates.
- **Completed_Order**: An order whose `order_status` equals `'selesai'`; only completed orders count toward realized revenue and gross profit.
- **Bot**: A visitor whose user-agent matches a known crawler substring set or is empty/null, as determined by `isLikelyBot`.
- **WITA**: The `Asia/Makassar` timezone (UTC+8) used for all dates and timestamps.

## Requirements

### Requirement 1: Visit Recording

**User Story:** As a store owner, I want each storefront page load to be recorded as a visit, so that I can measure traffic feeding the conversion funnel.

#### Acceptance Criteria

1. WHEN a storefront page is loaded by a visitor whose user-agent is not classified as a bot and whose query-stripped page URL has not yet been counted in the current session, THE Visit_Tracker SHALL insert exactly one row into the `page_visits` table.
2. WHEN the Visit_Tracker is invoked a second or subsequent time within the same session for a page URL that, after query-string removal, matches a URL already counted in that session, THE Visit_Tracker SHALL skip the insert and return false.
3. WHEN a row is inserted into the `page_visits` table, THE Visit_Tracker SHALL return true.
4. WHEN a visit is recorded, THE Visit_Tracker SHALL store the page URL with everything from the first "?" character onward removed and the remaining path truncated to a maximum of 255 characters.
5. WHEN a visit is recorded, THE Visit_Tracker SHALL store the visitor's user-agent value truncated to a maximum of 255 characters.
6. WHERE the user-agent string is empty, null, or contains (case-insensitive) any known crawler token (for example "bot", "crawl", "spider", "slurp", "bingpreview", or "headless"), THE Visit_Tracker SHALL classify the visitor as a bot and set the `is_bot` flag to 1 on the recorded row.
7. IF any database error or exception occurs during visit recording, THEN THE Visit_Tracker SHALL catch the error, write the error to the application error log, return false, and allow the page to continue rendering with no change to the visible response.

### Requirement 2: Visitor Privacy Protection

**User Story:** As a store owner, I want visitor tracking to retain no personally identifiable information, so that the store respects visitor privacy.

#### Acceptance Criteria

1. WHEN a visit is recorded, THE Visit_Tracker SHALL store the session identifier as a 64-character lowercase hexadecimal SHA-256 hash of the raw session id, and SHALL NOT store the raw session id.
2. WHEN a visit is recorded, THE Visit_Tracker SHALL store the visitor identifier as a 64-character lowercase hexadecimal SHA-256 hash computed from the configured salt concatenated with the visitor IP address and the visitor user-agent string, and SHALL NOT store the raw IP address or the raw user-agent as the visitor identifier.
3. WHEN a visit is recorded, THE Visit_Tracker SHALL store the user-agent value truncated to a maximum of 255 characters and the referrer value truncated to a maximum of 255 characters.
4. THE page_visits records SHALL contain no raw IP address and no raw session identifier in any column.
5. WHEN a visit is recorded, THE Visit_Tracker SHALL store the requested page path with any query string (the portion at and after the first `?`) removed, so that no query-string-borne personal data or tokens are retained.
6. IF the configured salt is unset or empty when a visit is recorded, THEN THE Visit_Tracker SHALL skip recording the visit rather than store an unsalted or raw visitor identifier.
7. WHERE a retention horizon is configured, THE Analytics_Engine SHALL delete page_visits rows whose creation timestamp is older than the configured horizon, where the horizon is constrained to a value between 1 and 3650 days and defaults to 180 days when unset or outside that range.

### Requirement 3: Bot Detection

**User Story:** As a store owner, I want automated crawler traffic identified, so that bot activity does not distort my analytics.

#### Acceptance Criteria

1. IF a user-agent string is null, empty, or contains only whitespace characters, THEN THE Analytics_Engine SHALL classify the visitor as a Bot.
2. WHEN a non-empty user-agent string contains any substring from the known crawler substring set (matched case-insensitively), THE Analytics_Engine SHALL classify the visitor as a Bot.
3. WHEN a non-empty user-agent string contains no substring from the known crawler substring set (matched case-insensitively), THE Analytics_Engine SHALL classify the visitor as a non-bot.
4. WHEN the same user-agent string is classified more than once, THE Analytics_Engine SHALL return an identical classification result on every invocation, with no dependence on prior calls, time, or external state.
5. WHEN funnel and visit metrics are computed, THE Analytics_Engine SHALL exclude every row whose `is_bot` flag equals 1 from the total visit count, the unique-visitor count, and the conversion-funnel counts.

### Requirement 4: Conversion Funnel

**User Story:** As a store owner, I want to see the Visits → Registrations → Purchases funnel, so that I can understand where prospective buyers drop off.

#### Acceptance Criteria

1. WHEN funnel statistics are requested for a DateRange, THE Analytics_Engine SHALL return the count of unique non-bot visits, the count of registrations, and the count of purchases that fall on or between the DateRange `start_date` and `end_date` inclusive in WITA, each as a non-negative integer.
2. WHEN counting registrations for a DateRange, THE Analytics_Engine SHALL count each distinct buyer account whose account-creation date falls within the DateRange exactly once.
3. WHEN counting purchases for a DateRange, THE Analytics_Engine SHALL count each order whose order date falls within the DateRange exactly once.
4. WHEN computing the registration rate, THE Analytics_Engine SHALL return registrations divided by visits when visits is greater than zero, and 0.0 otherwise.
5. WHEN computing the purchase rate, THE Analytics_Engine SHALL return purchases divided by registrations when registrations is greater than zero, and 0.0 otherwise.
6. WHEN computing the overall rate, THE Analytics_Engine SHALL return purchases divided by visits when visits is greater than zero, and 0.0 otherwise.
7. THE Analytics_Engine SHALL return each conversion rate as a float, and SHALL return a value within the inclusive interval 0.0 to 1.0 whenever registrations does not exceed visits and purchases does not exceed registrations.

### Requirement 5: Date Range Normalization

**User Story:** As a store owner, I want any date range I select to be interpreted safely, so that malformed or hostile inputs never break the analytics page.

#### Acceptance Criteria

1. WHEN a date range is requested with `from` and `to` values that both parse to valid calendar dates, THE Date_Normalizer SHALL return a DateRange whose `start_date` and `end_date` are valid calendar dates formatted as `Y-m-d` (four-digit year, two-digit month, two-digit day, zero-padded).
2. THE Date_Normalizer SHALL return a DateRange whose `start_date` is less than or equal to its `end_date`.
3. IF the parsed `from` value is later than the parsed `to` value, THEN THE Date_Normalizer SHALL return a DateRange in which the earlier value becomes `start_date` and the later value becomes `end_date`.
4. IF the requested `from` or `to` value is null, empty, or cannot be parsed as a valid calendar date, THEN THE Date_Normalizer SHALL return a DateRange covering the 30 calendar days ending today, where today is the current date in the Asia/Makassar (WITA) timezone.
5. THE Date_Normalizer SHALL return a DateRange whose inclusive span (`end_date` minus `start_date`) does not exceed the configured maximum span, which SHALL default to 366 days.
6. IF the resolved inclusive span exceeds the configured maximum span, THEN THE Date_Normalizer SHALL set `start_date` to (`end_date` minus the configured maximum span plus one day) while preserving `end_date`.
7. THE Date_Normalizer SHALL return a DateRange whose `end_date` is not later than today in the Asia/Makassar (WITA) timezone.
8. IF the resolved `end_date` is later than today in the Asia/Makassar (WITA) timezone, THEN THE Date_Normalizer SHALL set `end_date` to today in the Asia/Makassar (WITA) timezone.

### Requirement 6: Sales and Profit Summary

**User Story:** As a store owner, I want headline sales and profit figures for a date range, so that I can assess store performance at a glance.

#### Acceptance Criteria

1. WHEN a sales summary is requested for a DateRange, THE Analytics_Engine SHALL return total revenue, gross profit, margin percentage, order count, average order value, and cancellation rate.
2. WHEN computing realized revenue and gross profit, THE Analytics_Engine SHALL include only Completed_Orders whose creation date falls within the inclusive DateRange.
3. WHEN computing gross profit, THE Analytics_Engine SHALL return revenue minus the aggregated product cost of the in-range Completed_Orders, returning a negative value when aggregated product cost exceeds revenue.
4. WHEN computing margin percentage, THE Analytics_Engine SHALL return gross profit divided by revenue multiplied by 100, rounded to 2 decimal places, when revenue is greater than zero, and 0.0 when revenue is zero.
5. WHEN computing order count, THE Analytics_Engine SHALL return the count of in-range Completed_Orders.
6. WHEN computing average order value, THE Analytics_Engine SHALL return the integer division of revenue by order count when order count is greater than zero, and 0 when order count is zero.
7. WHEN computing cancellation rate, THE Analytics_Engine SHALL return cancelled count divided by total count of all in-range orders regardless of status when total count is greater than zero, and 0.0 when total count is zero, always within the inclusive interval 0.0 to 1.0.
8. THE Analytics_Engine SHALL return revenue, aggregated product cost, and average order value as non-negative integers.
9. IF no Completed_Orders exist within the inclusive DateRange, THEN THE Analytics_Engine SHALL return revenue of 0, gross profit of 0, average order value of 0, margin percentage of 0.0, and cancellation rate of 0.0.

### Requirement 7: Product, Category, and Area Breakdowns

**User Story:** As a store owner, I want sales and profit broken down by product, category, and Toraja shipping area, so that I can see what and where I sell most profitably.

#### Acceptance Criteria

1. WHEN a product breakdown is requested for a DateRange, THE Analytics_Engine SHALL return, for each product appearing in at least one in-range Completed_Order, units sold, revenue, cost, gross profit computed as revenue minus cost using `products.purchase_price` multiplied by units sold, and margin percentage computed as gross profit divided by revenue multiplied by 100 when revenue is greater than zero and 0.0 otherwise.
2. WHEN a category breakdown is requested for a DateRange, THE Analytics_Engine SHALL return revenue, profit computed as revenue minus cost, and quantity grouped by category for in-range Completed_Orders, grouping products with no category under an "Uncategorized" group.
3. WHEN an area breakdown is requested for a DateRange, THE Analytics_Engine SHALL return revenue and the count of distinct in-range Completed_Orders grouped by shipping area.
4. THE Analytics_Engine SHALL return all currency and count values in every breakdown as non-negative integers and each margin percentage as a float greater than or equal to 0.0.
5. WHEN a breakdown is returned, THE Analytics_Engine SHALL order product rows by gross profit descending, then by units sold descending, then by product identifier ascending.
6. WHEN a product breakdown is requested with a result limit that is an integer between 1 and 1000 inclusive, THE Analytics_Engine SHALL return no more rows than the requested limit.
7. IF a product breakdown is requested with a result limit that is null, less than 1, or greater than 1000, THEN THE Analytics_Engine SHALL apply a default limit of 10.

### Requirement 8: Sales Trends and Promotion Effectiveness

**User Story:** As a store owner, I want sales trends over time and promotion effectiveness, so that I can evaluate seasonality and campaign impact.

#### Acceptance Criteria

1. WHEN a sales trend is requested with a granularity of day, week, or month, THE Analytics_Engine SHALL return revenue from Completed_Orders bucketed by the requested granularity, with one bucket per interval spanned by the DateRange, ordered chronologically ascending, each revenue value expressed as a non-negative integer.
2. WHEN promotion effectiveness is requested for a DateRange, THE Analytics_Engine SHALL return, for each distinct promotion identified in `orders.applied_promotions` among Completed_Orders within the DateRange, the count of orders that applied it, the summed `orders.discount_amount` attributed to it as a non-negative integer, and the summed revenue of those orders as a non-negative integer.
3. WHEN a sales trend interval within the DateRange contains no Completed_Orders, THE Analytics_Engine SHALL return that interval with a revenue value of 0.
4. IF a sales trend is requested with a granularity other than day, week, or month, THEN THE Analytics_Engine SHALL default the granularity to day.

### Requirement 9: Stock Health

**User Story:** As a store owner, I want to see which products are low or out of stock, so that I can restock before losing sales.

#### Acceptance Criteria

1. WHEN stock health is requested, THE Analytics_Engine SHALL return all products whose current stock is greater than 0 and less than or equal to the configured low-stock threshold, each classified as low-stock, where the threshold is a configurable integer between 1 and 100 inclusive that defaults to 5.
2. THE Analytics_Engine SHALL compute stock health from the current product snapshot independently of any DateRange.
3. WHEN stock health is requested, THE Analytics_Engine SHALL return all products whose current stock equals 0, each classified as out-of-stock.
4. IF no product has a current stock value at or below the configured low-stock threshold and no product has a current stock value of 0, THEN THE Analytics_Engine SHALL return an empty stock-health result without error.
5. IF the stock-health computation cannot complete because the underlying product data cannot be retrieved, THEN THE Analytics_Engine SHALL return a result indicating the failure rather than partial or stale stock data.

### Requirement 10: Admin Analytics Page

**User Story:** As an administrator, I want a dedicated analytics page with a date-range filter, so that I can explore store metrics visually.

#### Acceptance Criteria

1. IF an unauthenticated visitor requests the Analytics_Page, THEN THE Analytics_Page SHALL redirect the request to the administrator login page and SHALL produce no analytics output.
2. WHEN an administrator requests the Analytics_Page with `from` and `to` query parameters, THE Analytics_Page SHALL normalize the parameters through the Date_Normalizer before querying.
3. IF the `from` or `to` parameter is missing or cannot be parsed as a valid date, THEN THE Date_Normalizer SHALL apply a default range covering the last 30 days ending on the current date.
4. WHEN the normalized `from` date is later than the normalized `to` date, THE Date_Normalizer SHALL swap the two values so that `from` is the earlier date and `to` is the later date.
5. WHEN the span between the normalized `from` and `to` dates exceeds 366 days, THE Date_Normalizer SHALL cap the span at 366 days, AND WHEN the normalized `to` date is later than the current date, THE Date_Normalizer SHALL set the `to` date to the current date.
6. WHEN the Analytics_Page renders metrics, THE Analytics_Page SHALL obtain every numeric value from the Analytics_Engine rather than computing values inline.
7. WHEN the Analytics_Page renders any stored value into HTML, THE Analytics_Page SHALL pass the value through `sanitizeOutput()`.
8. WHEN the Dashboard renders, THE Dashboard SHALL display gross profit computed as total revenue minus total cost, average order value computed as total revenue divided by total order count (and equal to 0 when the order count is 0), a cancellation rate expressed as a value within the inclusive range 0 to 1, and a compact funnel obtained from the Analytics_Engine.
9. WHEN the Dashboard renders the compact funnel, THE Dashboard SHALL display the funnel as the ordered stages Visits, Registrations, and Purchases, excluding rows identified as bot traffic from every stage count.
10. IF the Analytics_Engine cannot return one or more requested values, THEN THE Analytics_Page SHALL render a safe empty or error state for the affected metrics without producing fatal output.

### Requirement 11: Brute-Force Throttling

**User Story:** As a store owner, I want repeated failed login and registration attempts throttled, so that the store is protected from brute-force attacks without blocking legitimate buyers.

#### Acceptance Criteria

1. WHEN an authentication request is received, THE Rate_Limiter SHALL count the failed attempts for the request key recorded within the rolling window, defaulting to 900 seconds, before performing any credential verification.
2. IF the failed attempt count is at or above the configured maximum, defaulting to 5 attempts, and the oldest counted attempt is still within the rolling window, THEN THE Rate_Limiter SHALL report the request as not allowed together with an integer retry-after value in seconds bounded to the range 0 to the window length.
3. WHEN a request is reported as not allowed, THE authentication endpoint SHALL reject the request without performing credential verification and SHALL return the integer number of seconds to wait before retrying.
4. WHEN an authentication attempt fails, THE Rate_Limiter SHALL record exactly one failed attempt for the request key.
5. WHEN an authentication attempt succeeds, THE Rate_Limiter SHALL fully clear all recorded attempts for the request key.
6. THE Rate_Limiter SHALL scope the request key by action, hashed identifier, and client IP so that one client cannot lock out a different buyer.
7. THE Rate_Limiter SHALL compute the retry-after value as the rolling window length minus the age of the oldest counted attempt, bounded to be at least 0 and at most the window length, expressed as an integer number of seconds.
8. WHILE no failed attempts are recorded for a request key, THE Rate_Limiter SHALL report requests for that key as allowed with a retry-after value of 0.
9. IF the rate-limit data store is unavailable, THEN THE Rate_Limiter SHALL fail open by reporting the request as allowed, SHALL log the error, and SHALL NOT return any error detail to the client.
10. THE Rate_Limiter SHALL delete attempt records older than the configured retention window, defaulting to 86400 seconds.

### Requirement 12: CSRF Protection on Buyer Actions

**User Story:** As a store owner, I want buyer authentication and profile actions protected against cross-site request forgery, so that buyer accounts cannot be manipulated by forged requests.

#### Acceptance Criteria

1. WHEN a POST request to the buyer login, registration, or profile-update action is received, THE CSRF_Validator SHALL compare the submitted CSRF token against the token stored in the current session before performing any database read or write.
2. IF the submitted CSRF token field is absent or contains an empty value, THEN THE action SHALL return a JSON response with success set to false and an error message indicating an invalid or missing security token, and SHALL terminate before performing any database read or write.
3. IF the submitted CSRF token does not exactly match the session token, THEN THE action SHALL return a JSON response with success set to false and an error message indicating an invalid security token, SHALL leave all buyer account data unchanged, and SHALL terminate processing.
4. WHEN the submitted CSRF token exactly matches the session token, THE action SHALL proceed with its normal processing.
5. WHEN no CSRF token exists in the current session, THE CSRF_Validator SHALL treat validation as failed regardless of the submitted token value.

### Requirement 13: Open-Redirect-Safe Redirects

**User Story:** As a store owner, I want referer-based redirects confined to the store's own host, so that the store cannot be used to redirect buyers to malicious sites.

#### Acceptance Criteria

1. WHEN a redirect target is a relative path beginning with a single `/`, or an absolute URL whose host (compared case-insensitively, including any explicit port) equals the configured allowed host, THE Redirect_Guard SHALL treat the target as safe and redirect to it.
2. IF a redirect target is null, empty, consists only of whitespace, exceeds 2048 characters, is a protocol-relative URL beginning with `//`, is an absolute URL whose host does not equal the configured allowed host, or uses any scheme other than `http` or `https` (including but not limited to `javascript:`, `data:`, `vbscript:`, or `file:`), THEN THE Redirect_Guard SHALL redirect to the configured fallback path and SHALL NOT redirect to the requested target.
3. THE Redirect_Guard SHALL produce a destination that resolves to either a relative path or an absolute URL whose host equals the configured allowed host, with no other host reachable through the produced destination.
4. WHEN a target classified as safe under criterion 1 includes a query string, THE Redirect_Guard SHALL preserve the query string unchanged in the destination.
5. WHEN a target classified as safe under criterion 1 includes a fragment identifier beginning with `#`, THE Redirect_Guard SHALL preserve the fragment identifier unchanged in the destination.
6. IF the configured fallback path is null, empty, or itself fails the safety checks defined in criterion 1, THEN THE Redirect_Guard SHALL redirect to the site root path `/`.

### Requirement 14: Secure Session and Response Headers

**User Story:** As a store owner, I want secure session cookies and baseline security headers, so that the store is protected against session theft, clickjacking, and MIME sniffing.

#### Acceptance Criteria

1. WHEN session cookie parameters are configured, THE Session_Configurator SHALL set the `HttpOnly` flag and the `SameSite=Lax` attribute, and this configuration SHALL occur before `session_start()` is called and before any response body output.
2. WHERE the request is served over HTTPS, THE Session_Configurator SHALL set the `Secure` flag on the session cookie.
3. WHERE the request is served over plain HTTP, THE Session_Configurator SHALL omit the `Secure` flag while still setting the `HttpOnly` flag and the `SameSite=Lax` attribute, so local development is not broken.
4. WHEN a response is produced, THE Header_Manager SHALL emit each of the headers `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, and `Referrer-Policy: strict-origin-when-cross-origin` exactly once with those exact values.
5. WHEN a response is produced, THE Header_Manager SHALL emit a Content-Security-Policy header exactly once that permits the Tailwind CDN, Google Fonts, and Chart.js as script, style, and font sources as applicable, and rejects all other external script, style, and font origins.
6. THE Header_Manager SHALL emit all security headers before any response body output.
7. IF the security headers cannot be sent because response body output has already started, THEN THE Header_Manager SHALL skip emitting the headers, log the occurrence, and return without raising a fatal error.

### Requirement 15: Secret and Tooling Exposure Lock-Down

**User Story:** As a store owner, I want dangerous root-level scripts and secrets locked down, so that operational tooling and credentials are not exposed publicly.

#### Acceptance Criteria

1. WHEN the production deployment process completes, THE deployment SHALL ensure each of the root-level scripts `debug.php`, `debug_finfo_test.php`, the `migrate_*.php` scripts, the `seed_*.php` scripts, `clean_db_settings_prod.php`, and `restore_env.php` is either absent from the publicly served document root or configured to deny all public HTTP requests with an access-denied response.
2. WHEN any of the locked-down root-level scripts listed in criterion 1 is requested over public HTTP, THE deployment SHALL return an access-denied response and SHALL NOT execute the script or return its source contents.
3. WHEN the files `.env` and `database.sql` are requested over public HTTP, THE deployment SHALL deny the request with an access-denied response and SHALL NOT return any portion of their contents.
4. WHEN the analytics-and-security migration script has completed a successful run exactly once, THE deployment SHALL remove the migration script from the publicly served document root or configure it to deny all public HTTP requests with an access-denied response.
5. IF a subsequent public HTTP request targets the analytics-and-security migration script after its first successful run, THEN THE deployment SHALL return an access-denied response and SHALL NOT re-execute the migration.
6. IF the lock-down action (removal or access restriction) for any file in criteria 1 through 4 cannot be applied during deployment, THEN THE deployment SHALL halt the deployment process and SHALL surface an error indication identifying each file that remains exposed.

### Requirement 16: Database Migration

**User Story:** As an administrator, I want the new tables created without disturbing existing data, so that I can deploy analytics and security tracking safely against the live database.

#### Acceptance Criteria

1. WHEN the migration script runs, THE migration SHALL create the `page_visits` and `rate_limit_attempts` tables using `CREATE TABLE IF NOT EXISTS`.
2. THE migration SHALL NOT execute any `ALTER`, `DROP`, `TRUNCATE`, or `DELETE` statement against any pre-existing table.
3. WHEN the migration script runs more than once, THE migration SHALL leave every pre-existing table's column definition and row count unchanged.
4. WHEN the migration completes, THE migration SHALL output, for each of `page_visits` and `rate_limit_attempts`, a status message indicating whether the table was newly created or already existed.
5. IF creating either table fails (for example, a database connection failure or SQL execution error), THEN THE migration SHALL stop before processing further statements, report an error message identifying the affected table and the failure cause, and SHALL leave all pre-existing tables unchanged.

### Requirement 17: Preserved Low-Friction Buyer Onboarding

**User Story:** As a store owner, I want buyer onboarding to remain low-friction, so that the security hardening does not reduce customer acquisition.

#### Acceptance Criteria

1. WHEN a buyer submits the registration form with a valid name, identifier, and password, THE buyer registration flow SHALL create the account and SHALL NOT require any email-verification or one-time-passcode (OTP) confirmation step before the account is usable.
2. WHEN a buyer submits the registration, login, or checkout flow, THE flow SHALL process the submission without presenting or requiring any captcha or equivalent human-verification challenge.
3. WHEN a buyer submits a password of at least 6 and at most 255 characters, THE buyer password policy SHALL accept the password without requiring any uppercase letter, lowercase letter, digit, or special character.
4. IF a buyer submits a password shorter than 6 characters, THEN THE buyer registration flow SHALL reject the submission, return a failure response with an error indicating the 6-character minimum, and SHALL NOT create the account.
5. WHEN a buyer completes registration successfully, THE registration flow SHALL establish an authenticated buyer session for that buyer without requiring a separate login step.
