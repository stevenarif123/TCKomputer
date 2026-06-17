<?php
/**
 * Analytics & Security Migration Script
 *
 * Creates the page_visits and rate_limit_attempts tables.
 * Safe to run multiple times (uses CREATE TABLE IF NOT EXISTS).
 * Never alters, drops, truncates, or deletes pre-existing tables.
 *
 * Run once via Laragon/CLI, then remove or lock down via .htaccess.
 */

// Only accessible from CLI or very explicitly — block public web access here too.
if (PHP_SAPI !== 'cli' && !isset($_GET['_run'])) {
    http_response_code(403);
    echo 'Access denied. Run from CLI or append ?_run=1 for one-time web execution.';
    exit;
}

require_once __DIR__ . '/config/db.php';

$pdo = getDBConnection();

// ─── Table definitions ────────────────────────────────────────────────────────

$tables = [
    'page_visits' => "CREATE TABLE IF NOT EXISTS `page_visits` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_id`   CHAR(64) NOT NULL         COMMENT 'sha256 of PHP session id — no raw id stored',
    `visitor_hash` CHAR(64) NOT NULL         COMMENT 'sha256(APP_VISIT_SALT + ip + user_agent) — no raw PII stored',
    `page_url`     VARCHAR(255) NOT NULL      COMMENT 'request path, query string stripped',
    `referrer`     VARCHAR(255) NULL,
    `user_agent`   VARCHAR(255) NULL,
    `is_bot`       TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'heuristic bot flag',
    `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_visits_created`  (`created_at`),
    INDEX `idx_visits_session`  (`session_id`),
    INDEX `idx_visits_unique`   (`visitor_hash`, `created_at`),
    INDEX `idx_visits_bot`      (`is_bot`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Lightweight visit tracking for conversion funnel analytics'",

    'rate_limit_attempts' => "CREATE TABLE IF NOT EXISTS `rate_limit_attempts` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `action`     VARCHAR(32) NOT NULL   COMMENT 'login | register',
    `rate_key`   VARCHAR(191) NOT NULL  COMMENT 'buildRateLimitKey(action, idHash, ip)',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_rl_key_time` (`rate_key`, `created_at`),
    INDEX `idx_rl_created`  (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Rolling-window failed authentication attempts for brute-force protection'",
];

// ─── Execute migrations ───────────────────────────────────────────────────────

$allOk = true;

foreach ($tables as $tableName => $sql) {
    echo "Checking table `{$tableName}`... ";

    try {
        // Check if table already exists before creation
        $existsStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
        );
        $existsStmt->execute([$tableName]);
        $exists = (int)$existsStmt->fetchColumn() > 0;

        // CREATE TABLE IF NOT EXISTS is idempotent — safe to run always
        $pdo->exec($sql);

        if ($exists) {
            echo "OK (already existed, no changes made).\n";
        } else {
            echo "OK (newly created).\n";
        }
    } catch (PDOException $e) {
        echo "FAILED.\n";
        echo "  Error on table `{$tableName}`: " . $e->getMessage() . "\n";
        echo "  Migration stopped. Pre-existing tables were not modified.\n";
        $allOk = false;
        break; // Stop before further statements on failure (Requirement 16.5)
    }
}

if ($allOk) {
    echo "\nMigration completed successfully.\n";
    echo "IMPORTANT: Remove or deny public access to this file after first successful run.\n";
    echo "           Add to .htaccess: <Files \"migrate_analytics_security.php\">\\n  Require all denied\\n</Files>\n";
} else {
    echo "\nMigration failed. See error above.\n";
    exit(1);
}
