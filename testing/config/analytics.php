<?php
/**
 * Analytics Engine
 *
 * Pillar A — Pure helper functions (fully deterministic, no PDO) and
 * DB-backed query functions (read-only) for the TC Komputer storefront.
 *
 * Pure helpers: normalizeDateRange, computeMargin, computeMarginPercent,
 *               computeAov, computeCancellationRate, computeConversionRates,
 *               isLikelyBot.
 *
 * DB queries:   getSalesSummary, getProfitByProduct, getSalesByCategory,
 *               getSalesByArea, getSalesTrend, getPromotionEffectiveness,
 *               getFunnelStats, getStockHealth.
 *
 * Write path:   recordVisit (non-throwing), pruneVisits (housekeeping).
 *
 * Conventions: integer Rupiah, Asia/Makassar (WITA) timezone,
 *              only order_status='selesai' counts as realized revenue.
 */

require_once __DIR__ . '/db.php'; // ensures getVisitSalt() and getDBConnection() are available

// ═══════════════════════════════════════════════════════════════════════════════
// PURE HELPER FUNCTIONS — no PDO, fully deterministic, property-testable
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Normalize and clamp a requested date range to a safe inclusive [start, end].
 *
 * Rules:
 * - Invalid / empty inputs → last 30 days ending today (WITA).
 * - If from > to → swap them.
 * - Span is capped at $maxDays (default 366).
 * - end_date is clamped to today (WITA).
 * - $now is a Unix timestamp; when null, current WITA time is used (injectable for tests).
 *
 * @param string|null $from  User-supplied start date string (untrusted).
 * @param string|null $to    User-supplied end date string (untrusted).
 * @param int         $maxDays  Maximum allowed span in days.
 * @param int|null    $now   Unix timestamp override (for testing).
 * @return array{start_date:string,end_date:string} 'Y-m-d' WITA strings.
 *
 * @pure
 */
function normalizeDateRange(?string $from, ?string $to, int $maxDays = 366, ?int $now = null): array
{
    $tz  = new DateTimeZone('Asia/Makassar');
    $today = new DateTime('now', $tz);
    if ($now !== null) {
        $today = new DateTime('@' . $now);
        $today->setTimezone($tz);
    }
    $todayStr = $today->format('Y-m-d');

    // Parse helper — returns 'Y-m-d' string or null on failure
    $parse = function (?string $s) use ($tz): ?string {
        if ($s === null || trim($s) === '') {
            return null;
        }
        $dt = DateTime::createFromFormat('Y-m-d', trim($s), $tz);
        if ($dt === false || $dt->format('Y-m-d') !== trim($s)) {
            return null;
        }
        return $dt->format('Y-m-d');
    };

    $parsedFrom = $parse($from);
    $parsedTo   = $parse($to);

    // Fall back to min(30, maxDays-1) days if either value is invalid
    if ($parsedFrom === null || $parsedTo === null) {
        $fallbackDays = min(29, $maxDays - 1);
        $start = (clone $today)->modify('-' . $fallbackDays . ' days')->format('Y-m-d');
        return ['start_date' => $start, 'end_date' => $todayStr];
    }

    // Swap reversed ranges (Req 5.3)
    if ($parsedFrom > $parsedTo) {
        [$parsedFrom, $parsedTo] = [$parsedTo, $parsedFrom];
    }

    // Clamp end_date to today (Req 5.7, 5.8)
    if ($parsedTo > $todayStr) {
        $parsedTo = $todayStr;
    }

    // If start ended up after the clamped end, pull start back to end (edge: both future)
    if ($parsedFrom > $parsedTo) {
        $parsedFrom = $parsedTo;
    }

    // Clamp span to maxDays (Req 5.5, 5.6): preserve end_date, push start forward
    $dtFrom = new DateTime($parsedFrom, $tz);
    $dtTo   = new DateTime($parsedTo,   $tz);
    $diffDays = (int)$dtFrom->diff($dtTo)->days; // inclusive span = diffDays + 1
    if ($diffDays >= $maxDays) {
        $dtFrom = (clone $dtTo)->modify('-' . ($maxDays - 1) . ' days');
        $parsedFrom = $dtFrom->format('Y-m-d');
    }

    return ['start_date' => $parsedFrom, 'end_date' => $parsedTo];
}

/**
 * Compute gross profit: revenue - cost.
 * May be negative if cost exceeds revenue.
 *
 * @pure
 */
function computeMargin(int $revenue, int $cost): int
{
    return $revenue - $cost;
}

/**
 * Compute gross margin percentage: (revenue - cost) / revenue * 100.
 * Returns 0.0 when revenue is 0 (no division by zero).
 * Rounded to 2 decimal places.
 *
 * @pure
 */
function computeMarginPercent(int $revenue, int $cost): float
{
    if ($revenue === 0) {
        return 0.0;
    }
    return round(($revenue - $cost) / $revenue * 100, 2);
}

/**
 * Compute Average Order Value: revenue / orderCount (integer division).
 * Returns 0 when orderCount is 0 (no division by zero).
 *
 * @pure
 */
function computeAov(int $revenue, int $orderCount): int
{
    if ($orderCount <= 0) {
        return 0;
    }
    return intdiv($revenue, $orderCount);
}

/**
 * Compute cancellation rate: cancelled / total.
 * Returns 0.0 when total is 0 (no division by zero).
 * Result is in [0.0, 1.0] when cancelledCount <= totalCount.
 *
 * @pure
 */
function computeCancellationRate(int $cancelledCount, int $totalCount): float
{
    if ($totalCount <= 0) {
        return 0.0;
    }
    return $cancelledCount / $totalCount;
}

/**
 * Compute conversion funnel rates from raw counts.
 *
 * - registration_rate = registrations / visits           (0.0 if visits == 0)
 * - purchase_rate     = purchases / registrations        (0.0 if registrations == 0)
 * - overall_rate      = purchases / visits               (0.0 if visits == 0)
 *
 * All inputs are treated as max(0, n). Each rate is in [0,1] when inputs
 * are consistent (registrations <= visits, purchases <= registrations).
 *
 * @return array{registration_rate:float,purchase_rate:float,overall_rate:float}
 * @pure
 */
function computeConversionRates(int $visits, int $registrations, int $purchases): array
{
    $visits        = max(0, $visits);
    $registrations = max(0, $registrations);
    $purchases     = max(0, $purchases);

    return [
        'registration_rate' => $visits > 0        ? (float)($registrations / $visits)        : 0.0,
        'purchase_rate'     => $registrations > 0 ? (float)($purchases / $registrations)     : 0.0,
        'overall_rate'      => $visits > 0        ? (float)($purchases / $visits)            : 0.0,
    ];
}

/**
 * Heuristic bot detection from a user-agent string.
 *
 * Returns true when:
 * - $userAgent is null, empty, or contains only whitespace.
 * - $userAgent contains any known crawler substring (case-insensitive).
 *
 * Known crawler tokens: bot, crawl, spider, slurp, bingpreview, headless,
 * mediapartners, prerender, facebookexternalhit, ia_archiver, ahrefsbot,
 * semrushbot, mj12bot, dotbot, petalbot, bytespider.
 *
 * @pure
 */
function isLikelyBot(?string $userAgent): bool
{
    if ($userAgent === null || trim($userAgent) === '') {
        return true;
    }
    $crawlerTokens = [
        'bot', 'crawl', 'spider', 'slurp', 'bingpreview', 'headless',
        'mediapartners', 'prerender', 'facebookexternalhit', 'ia_archiver',
        'ahrefsbot', 'semrushbot', 'mj12bot', 'dotbot', 'petalbot', 'bytespider',
    ];
    $ua = strtolower($userAgent);
    foreach ($crawlerTokens as $token) {
        if (strpos($ua, $token) !== false) {
            return true;
        }
    }
    return false;
}

// ═══════════════════════════════════════════════════════════════════════════════
// DB-BACKED QUERY FUNCTIONS — read-only, delegate arithmetic to pure helpers
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Internal helper: execute a scalar query and return the value.
 *
 * @internal
 */
function _analyticsScalar(PDO $pdo, string $sql, array $params = []): mixed
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

/**
 * Headline sales KPIs for a date range.
 *
 * Returns:
 * - revenue         (int)   — sum of subtotals from completed orders
 * - cost            (int)   — sum of purchase_price * quantity from completed orders
 * - gross_profit    (int)   — revenue - cost (may be negative)
 * - margin_pct      (float) — gross profit % of revenue (0.0 when revenue == 0)
 * - order_count     (int)   — count of completed orders in range
 * - aov             (int)   — average order value (0 when order_count == 0)
 * - cancelled_count (int)   — count of cancelled orders in range
 * - total_count     (int)   — count of all orders regardless of status in range
 * - cancellation_rate (float) — in [0,1]
 *
 * @param array{start_date:string,end_date:string} $range
 */
function getSalesSummary(PDO $pdo, array $range): array
{
    $start = $range['start_date'] . ' 00:00:00';
    $end   = $range['end_date']   . ' 23:59:59';

    // Revenue + cost from completed orders
    $stmt = $pdo->prepare(
        "SELECT
            COALESCE(SUM(oi.subtotal), 0)                      AS revenue,
            COALESCE(SUM(oi.quantity * p.purchase_price), 0)   AS cost,
            COUNT(DISTINCT o.id)                               AS order_count
         FROM orders o
         JOIN order_items oi ON oi.order_id = o.id
         JOIN products   p  ON p.id = oi.product_id
         WHERE o.order_status = 'selesai'
           AND o.created_at BETWEEN ? AND ?"
    );
    $stmt->execute([$start, $end]);
    $row = $stmt->fetch() ?: ['revenue' => 0, 'cost' => 0, 'order_count' => 0];

    $revenue    = max(0, (int)$row['revenue']);
    $cost       = max(0, (int)$row['cost']);
    $orderCount = max(0, (int)$row['order_count']);

    // Cancelled + total order counts (all statuses)
    $stmt = $pdo->prepare(
        "SELECT
            COUNT(*) AS total_count,
            SUM(CASE WHEN order_status = 'dibatalkan' THEN 1 ELSE 0 END) AS cancelled_count
         FROM orders
         WHERE created_at BETWEEN ? AND ?"
    );
    $stmt->execute([$start, $end]);
    $statusRow = $stmt->fetch() ?: ['total_count' => 0, 'cancelled_count' => 0];

    $totalCount     = max(0, (int)$statusRow['total_count']);
    $cancelledCount = max(0, (int)$statusRow['cancelled_count']);

    $grossProfit = computeMargin($revenue, $cost);
    $marginPct   = computeMarginPercent($revenue, $cost);
    $aov         = computeAov($revenue, $orderCount);
    $cancRate    = computeCancellationRate($cancelledCount, $totalCount);

    return [
        'revenue'           => $revenue,
        'cost'              => $cost,
        'gross_profit'      => $grossProfit,
        'margin_pct'        => $marginPct,
        'order_count'       => $orderCount,
        'aov'               => $aov,
        'cancelled_count'   => $cancelledCount,
        'total_count'       => $totalCount,
        'cancellation_rate' => $cancRate,
    ];
}

/**
 * Best-selling and most-profitable products for a date range.
 *
 * Ordered by gross_profit DESC, units_sold DESC, product_id ASC.
 * Limit clamped to [1, 1000]; null or out-of-range defaults to 10.
 *
 * Each row contains:
 * - product_id, product_name, units_sold, revenue, cost, gross_profit, margin_pct
 *
 * @param array{start_date:string,end_date:string} $range
 * @param int|null $limit
 */
function getProfitByProduct(PDO $pdo, array $range, ?int $limit = 10): array
{
    $start = $range['start_date'] . ' 00:00:00';
    $end   = $range['end_date']   . ' 23:59:59';

    // Clamp limit (Req 7.6, 7.7)
    if ($limit === null || $limit < 1 || $limit > 1000) {
        $limit = 10;
    }

    $stmt = $pdo->prepare(
        "SELECT
            oi.product_id,
            oi.product_name,
            SUM(oi.quantity)                                   AS units_sold,
            SUM(oi.subtotal)                                   AS revenue,
            SUM(oi.quantity * p.purchase_price)                AS cost,
            SUM(oi.subtotal - oi.quantity * p.purchase_price)  AS gross_profit
         FROM order_items oi
         JOIN orders   o ON o.id = oi.order_id
         JOIN products p ON p.id = oi.product_id
         WHERE o.order_status = 'selesai'
           AND o.created_at BETWEEN ? AND ?
         GROUP BY oi.product_id, oi.product_name
         ORDER BY gross_profit DESC, units_sold DESC, oi.product_id ASC
         LIMIT " . (int)$limit
    );
    $stmt->execute([$start, $end]);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$r) {
        $r['units_sold']   = max(0, (int)$r['units_sold']);
        $r['revenue']      = max(0, (int)$r['revenue']);
        $r['cost']         = max(0, (int)$r['cost']);
        $r['gross_profit'] = computeMargin($r['revenue'], $r['cost']);
        $r['margin_pct']   = computeMarginPercent($r['revenue'], $r['cost']);
    }
    unset($r);

    return $rows;
}

/**
 * Revenue, profit, and quantity grouped by category for a date range.
 *
 * Products with no category are grouped as "Tanpa Kategori".
 *
 * @param array{start_date:string,end_date:string} $range
 */
function getSalesByCategory(PDO $pdo, array $range): array
{
    $start = $range['start_date'] . ' 00:00:00';
    $end   = $range['end_date']   . ' 23:59:59';

    $stmt = $pdo->prepare(
        "SELECT
            COALESCE(c.name, 'Tanpa Kategori')                 AS category_name,
            SUM(oi.quantity)                                   AS quantity,
            SUM(oi.subtotal)                                   AS revenue,
            SUM(oi.quantity * p.purchase_price)                AS cost
         FROM order_items oi
         JOIN orders    o ON o.id = oi.order_id
         JOIN products  p ON p.id = oi.product_id
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE o.order_status = 'selesai'
           AND o.created_at BETWEEN ? AND ?
         GROUP BY c.id, c.name
         ORDER BY revenue DESC"
    );
    $stmt->execute([$start, $end]);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$r) {
        $r['quantity'] = max(0, (int)$r['quantity']);
        $r['revenue']  = max(0, (int)$r['revenue']);
        $r['cost']     = max(0, (int)$r['cost']);
        $r['profit']   = computeMargin($r['revenue'], $r['cost']);
    }
    unset($r);

    return $rows;
}

/**
 * Revenue and order count grouped by shipping area for a date range.
 *
 * @param array{start_date:string,end_date:string} $range
 */
function getSalesByArea(PDO $pdo, array $range): array
{
    $start = $range['start_date'] . ' 00:00:00';
    $end   = $range['end_date']   . ' 23:59:59';

    $stmt = $pdo->prepare(
        "SELECT
            COALESCE(sa.area_name, 'Area Tidak Diketahui')     AS area_name,
            COALESCE(sa.regency, '')                           AS regency,
            COUNT(DISTINCT o.id)                               AS order_count,
            COALESCE(SUM(oi.subtotal), 0)                      AS revenue
         FROM orders o
         JOIN order_items oi  ON oi.order_id = o.id
         LEFT JOIN shipping_areas sa ON sa.id = o.shipping_area_id
         WHERE o.order_status = 'selesai'
           AND o.created_at BETWEEN ? AND ?
         GROUP BY sa.id, sa.area_name, sa.regency
         ORDER BY revenue DESC"
    );
    $stmt->execute([$start, $end]);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$r) {
        $r['order_count'] = max(0, (int)$r['order_count']);
        $r['revenue']     = max(0, (int)$r['revenue']);
    }
    unset($r);

    return $rows;
}

/**
 * Time-bucketed sales trend for a date range.
 *
 * $granularity: 'day' | 'week' | 'month' (defaults to 'day' for any other value).
 * Returns one bucket per interval, ordered chronologically.
 * Empty intervals have revenue = 0.
 *
 * @param array{start_date:string,end_date:string} $range
 * @param string $granularity 'day' | 'week' | 'month'
 */
function getSalesTrend(PDO $pdo, array $range, string $granularity = 'day'): array
{
    $start = $range['start_date'] . ' 00:00:00';
    $end   = $range['end_date']   . ' 23:59:59';

    // Validate granularity (Req 8.4)
    if (!in_array($granularity, ['day', 'week', 'month'], true)) {
        $granularity = 'day';
    }

    // Build the date format and grouping expression for MySQL
    switch ($granularity) {
        case 'week':
            $dateFn  = "DATE_FORMAT(o.created_at, '%x-W%v')"; // ISO week
            $labelFn = "CONCAT(YEAR(o.created_at), '-W', LPAD(WEEK(o.created_at,3), 2, '0'))";
            break;
        case 'month':
            $dateFn  = "DATE_FORMAT(o.created_at, '%Y-%m')";
            $labelFn = $dateFn;
            break;
        default: // day
            $dateFn  = "DATE(o.created_at)";
            $labelFn = $dateFn;
            break;
    }

    $stmt = $pdo->prepare(
        "SELECT
            {$labelFn}             AS bucket,
            COALESCE(SUM(oi.subtotal), 0) AS revenue
         FROM orders o
         JOIN order_items oi ON oi.order_id = o.id
         WHERE o.order_status = 'selesai'
           AND o.created_at BETWEEN ? AND ?
         GROUP BY bucket
         ORDER BY bucket ASC"
    );
    $stmt->execute([$start, $end]);
    $rows = $stmt->fetchAll();

    // Build a complete set of buckets with revenue=0 for empty intervals (Req 8.3)
    $bucketMap = [];
    foreach ($rows as $r) {
        $bucketMap[$r['bucket']] = max(0, (int)$r['revenue']);
    }

    $allBuckets = _generateBuckets($range['start_date'], $range['end_date'], $granularity);
    $result = [];
    foreach ($allBuckets as $bucket) {
        $result[] = [
            'bucket'  => $bucket,
            'revenue' => $bucketMap[$bucket] ?? 0,
        ];
    }
    return $result;
}

/**
 * Internal: generate all bucket labels between start and end dates.
 *
 * @internal
 */
function _generateBuckets(string $startDate, string $endDate, string $granularity): array
{
    $tz      = new DateTimeZone('Asia/Makassar');
    $current = new DateTime($startDate, $tz);
    $end     = new DateTime($endDate, $tz);
    $buckets = [];

    while ($current <= $end) {
        switch ($granularity) {
            case 'week':
                $buckets[] = $current->format('Y') . '-W' . str_pad($current->format('W'), 2, '0', STR_PAD_LEFT);
                $current->modify('+1 week');
                break;
            case 'month':
                $buckets[] = $current->format('Y-m');
                $current->modify('+1 month');
                break;
            default: // day
                $buckets[] = $current->format('Y-m-d');
                $current->modify('+1 day');
                break;
        }
    }
    return array_unique($buckets);
}

/**
 * Promotion effectiveness for a date range.
 *
 * For each distinct promotion in orders.applied_promotions among completed orders,
 * returns: promotion_name, order_count, total_discount, total_revenue.
 *
 * @param array{start_date:string,end_date:string} $range
 */
function getPromotionEffectiveness(PDO $pdo, array $range): array
{
    $start = $range['start_date'] . ' 00:00:00';
    $end   = $range['end_date']   . ' 23:59:59';

    $stmt = $pdo->prepare(
        "SELECT
            COALESCE(applied_promotions, 'Tanpa Promosi')  AS promotion_name,
            COUNT(*)                                       AS order_count,
            COALESCE(SUM(discount_amount), 0)              AS total_discount,
            COALESCE(SUM(total), 0)                        AS total_revenue
         FROM orders
         WHERE order_status = 'selesai'
           AND created_at BETWEEN ? AND ?
         GROUP BY applied_promotions
         ORDER BY total_revenue DESC"
    );
    $stmt->execute([$start, $end]);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$r) {
        $r['order_count']    = max(0, (int)$r['order_count']);
        $r['total_discount'] = max(0, (int)$r['total_discount']);
        $r['total_revenue']  = max(0, (int)$r['total_revenue']);
    }
    unset($r);

    return $rows;
}

/**
 * Conversion funnel stats for a date range.
 *
 * Returns:
 * - visits            (int)   — unique non-bot visitors (COUNT DISTINCT visitor_hash)
 * - registrations     (int)   — new user accounts created in range
 * - purchases         (int)   — orders placed in range (any status)
 * - registration_rate (float) — registrations / visits
 * - purchase_rate     (float) — purchases / registrations
 * - overall_rate      (float) — purchases / visits
 *
 * Bot rows (is_bot = 1) are excluded from all visit counts (Req 3.5, 4.1).
 *
 * @param array{start_date:string,end_date:string} $range
 */
function getFunnelStats(PDO $pdo, array $range): array
{
    $start = $range['start_date'] . ' 00:00:00';
    $end   = $range['end_date']   . ' 23:59:59';

    // Unique non-bot visits
    $visitsVal = _analyticsScalar(
        $pdo,
        "SELECT COUNT(DISTINCT visitor_hash) FROM page_visits
         WHERE is_bot = 0 AND created_at BETWEEN ? AND ?",
        [$start, $end]
    );
    $visits = max(0, (int)$visitsVal);

    // Registrations
    $regVal = _analyticsScalar(
        $pdo,
        "SELECT COUNT(*) FROM users WHERE created_at BETWEEN ? AND ?",
        [$start, $end]
    );
    $registrations = max(0, (int)$regVal);

    // Purchases (all orders placed, any status = "made a purchase attempt")
    $purchVal = _analyticsScalar(
        $pdo,
        "SELECT COUNT(*) FROM orders WHERE created_at BETWEEN ? AND ?",
        [$start, $end]
    );
    $purchases = max(0, (int)$purchVal);

    $rates = computeConversionRates($visits, $registrations, $purchases);

    return array_merge(
        ['visits' => $visits, 'registrations' => $registrations, 'purchases' => $purchases],
        $rates
    );
}

/**
 * Stock health: low-stock and out-of-stock products.
 *
 * - out_of_stock: products with stock == 0
 * - low_stock:    products with 0 < stock <= threshold
 * - threshold clamped to [1, 100], defaults to 5.
 *
 * Range-independent (current snapshot only).
 * Returns ['low_stock' => [...], 'out_of_stock' => [...]] always.
 * If product data cannot be retrieved, returns ['error' => 'message'].
 */
function getStockHealth(PDO $pdo, int $lowStockThreshold = 5): array
{
    // Clamp threshold (Req 9.1)
    if ($lowStockThreshold < 1 || $lowStockThreshold > 100) {
        $lowStockThreshold = 5;
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT id, name, stock, status,
                CASE
                    WHEN stock = 0 THEN 'out_of_stock'
                    WHEN stock <= ? THEN 'low_stock'
                    ELSE 'ok'
                END AS health_status
             FROM products
             WHERE is_active = 1 AND (stock = 0 OR stock <= ?)
             ORDER BY stock ASC, name ASC"
        );
        $stmt->execute([$lowStockThreshold, $lowStockThreshold]);
        $rows = $stmt->fetchAll();

        $lowStock    = [];
        $outOfStock  = [];
        foreach ($rows as $r) {
            $r['stock'] = (int)$r['stock'];
            if ($r['health_status'] === 'out_of_stock') {
                $outOfStock[] = $r;
            } else {
                $lowStock[] = $r;
            }
        }

        return ['low_stock' => $lowStock, 'out_of_stock' => $outOfStock];
    } catch (Throwable $e) {
        error_log('getStockHealth failed: ' . $e->getMessage());
        return ['error' => 'Gagal mengambil data stok produk.', 'low_stock' => [], 'out_of_stock' => []];
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// VISIT RECORDING — write path, non-throwing
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Record a storefront page visit (best-effort, never throws).
 *
 * - Skips if visitor is classified as a bot (sets is_bot=1 on recorded row).
 * - Deduplicates per (session, stripped page URL) via $_SESSION set.
 * - Skips when the configured salt is empty (Req 2.6).
 * - Hashes session id and ip+ua with SHA-256 (no raw PII stored).
 * - Strips query string from page_url (no tokens stored).
 * - Returns true iff a row was inserted; false on skip/error.
 *
 * @param PDO        $pdo     Database connection.
 * @param array      $context {session_id, ip, user_agent, page_url, referrer}.
 * @param int|null   $now     Unix timestamp override for testing.
 */
function recordVisit(PDO $pdo, array $context, ?int $now = null): bool
{
    try {
        $ua   = (string)($context['user_agent'] ?? '');
        $rawPage = (string)($context['page_url'] ?? '/');

        // Strip query string from page URL (Req 1.4, 2.5)
        $qPos = strpos($rawPage, '?');
        $page = ($qPos !== false) ? substr($rawPage, 0, $qPos) : $rawPage;
        $page = mb_substr($page, 0, 255);

        // Per-session dedup (Req 1.2)
        if (!isset($_SESSION['_counted_pages'])) {
            $_SESSION['_counted_pages'] = [];
        }
        $dedupKey = hash('sha256', $page); // keyed by stripped URL
        if (isset($_SESSION['_counted_pages'][$dedupKey])) {
            return false; // already counted this page this session
        }

        // Salt check (Req 2.6) — skip if salt unset/empty
        $salt = getVisitSalt();
        if ($salt === '') {
            return false;
        }

        // Build hashed identifiers — never store raw PII (Req 2.1, 2.2)
        $rawSid      = (string)($context['session_id'] ?? session_id());
        $sessionHash = hash('sha256', $rawSid);
        $visitorHash = hash('sha256', $salt . ($context['ip'] ?? '') . $ua);

        // Bot classification (Req 1.6, 3.1-3.4)
        $isBot = isLikelyBot($ua) ? 1 : 0;

        // Referrer truncated (Req 2.3)
        $referrer = isset($context['referrer']) && $context['referrer'] !== null
            ? mb_substr((string)$context['referrer'], 0, 255)
            : null;

        // Single prepared INSERT
        $stmt = $pdo->prepare(
            "INSERT INTO page_visits
                (session_id, visitor_hash, page_url, referrer, user_agent, is_bot, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([
            $sessionHash,
            $visitorHash,
            $page,
            $referrer,
            mb_substr($ua, 0, 255),
            $isBot,
        ]);

        // Mark this page as counted in the session
        $_SESSION['_counted_pages'][$dedupKey] = true;
        return true;

    } catch (Throwable $e) {
        // Tracking must never break page rendering (Req 1.7)
        error_log('recordVisit failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Delete page_visits rows older than the configured retention horizon.
 *
 * Horizon clamped to [1, 3650] days; defaults to 180 when null/out-of-range.
 *
 * @param int|null $retentionDays Retention horizon in days.
 */
function pruneVisits(PDO $pdo, ?int $retentionDays = null): int
{
    // Clamp to [1, 3650] (Req 2.7)
    if ($retentionDays === null || $retentionDays < 1 || $retentionDays > 3650) {
        $retentionDays = 180;
    }

    try {
        $stmt = $pdo->prepare(
            "DELETE FROM page_visits
             WHERE created_at < (NOW() - INTERVAL ? DAY)"
        );
        $stmt->execute([$retentionDays]);
        return (int)$stmt->rowCount();
    } catch (Throwable $e) {
        error_log('pruneVisits failed: ' . $e->getMessage());
        return 0;
    }
}
