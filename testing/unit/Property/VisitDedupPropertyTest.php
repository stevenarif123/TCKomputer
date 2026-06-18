<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/analytics.php';

use PHPUnit\Framework\TestCase;

/**
 * Property-Based Test for recordVisit() deduplication logic.
 *
 * **Validates: Requirements 1.1, 1.2, 1.7**
 *
 * Property 8: Visit recording is non-throwing and idempotent per session-page.
 * - recordVisit never propagates an exception.
 * - For a fixed session, calling it twice with the same stripped page_url
 *   results in at most one insert (second call returns false).
 *
 * Note: Tests target the pure dedup-key logic via the session array,
 * using an in-memory PDO stub for the DB write path.
 */
class VisitDedupPropertyTest extends TestCase
{
    private const ITERATIONS = 200;

    /** Create an in-memory SQLite PDO with the page_visits schema. */
    private function makePdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        // Define SQLite-equivalent NOW() function
        $pdo->sqliteCreateFunction('NOW', function() {
            return date('Y-m-d H:i:s');
        });

        $pdo->exec("
            CREATE TABLE page_visits (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                session_id   TEXT NOT NULL,
                visitor_hash TEXT NOT NULL,
                page_url     TEXT NOT NULL,
                referrer     TEXT,
                user_agent   TEXT,
                is_bot       INTEGER NOT NULL DEFAULT 0,
                created_at   TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");

        return $pdo;
    }

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
    }

    /**
     * Property: recordVisit never throws even with a bad PDO or bad context.
     * Validates: Requirement 1.7
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function recordVisitNeverThrows(): void
    {
        // Prevent error_log output to stderr/stdout from corrupting PHPUnit process serialization
        $oldErrorLog = ini_get('error_log');
        ini_set('error_log', tempnam(sys_get_temp_dir(), 'php_err'));

        // Bad PDO that will fail on exec
        $badPdo = new PDO('sqlite::memory:');
        // page_visits table does NOT exist → INSERT will fail
        
        // Define SQLite-equivalent NOW() function
        $badPdo->sqliteCreateFunction('NOW', function() {
            return date('Y-m-d H:i:s');
        });

        $context = [
            'session_id' => session_id(),
            'ip'         => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 Chrome/120',
            'page_url'   => '/test-page',
            'referrer'   => null,
        ];

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            try {
                $result = recordVisit($badPdo, $context);
                // Should return false, not throw
                $this->assertIsBool($result, "recordVisit must return bool even on error (iter $i)");
            } catch (Throwable $e) {
                ini_set('error_log', $oldErrorLog);
                $this->fail("recordVisit must not throw: " . $e->getMessage() . " (iter $i)");
            }
        }
        ini_set('error_log', $oldErrorLog);
    }

    /**
     * Property: First call for a page inserts exactly one row; second call returns false.
     * Validates: Requirements 1.1, 1.2
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function firstCallInsertsSecondCallIsNoop(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            // Fresh session state and fresh DB for each iteration
            $_SESSION = [];
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
            }
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }

            $pdo  = $this->makePdo();
            $page = '/page-' . mt_rand(1, 99999);

            $context = [
                'session_id' => session_id(),
                'ip'         => '127.0.0.1',
                'user_agent' => 'Mozilla/5.0 Chrome/120',
                'page_url'   => $page . '?query=remove-me',
                'referrer'   => null,
            ];

            // First call should succeed
            $first = recordVisit($pdo, $context);
            $this->assertTrue($first, "First recordVisit call for '$page' should return true (iter $i)");

            // Second call for same page must be a no-op
            $second = recordVisit($pdo, $context);
            $this->assertFalse($second, "Second recordVisit call for '$page' must return false (iter $i)");

            // DB must have exactly one row
            $count = (int)$pdo->query("SELECT COUNT(*) FROM page_visits")->fetchColumn();
            $this->assertSame(1, $count, "DB must have exactly 1 row after two calls for same page (iter $i)");
        }
    }

    /**
     * Property: Query string is stripped from stored page_url.
     * Validates: Requirements 1.4, 2.5
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function queryStringIsStrippedFromStoredUrl(): void
    {
        $pdo = $this->makePdo();

        $context = [
            'session_id' => session_id(),
            'ip'         => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 Chrome/120',
            'page_url'   => '/products?category=laptop&token=abc123secret',
            'referrer'   => null,
        ];

        recordVisit($pdo, $context);

        $row = $pdo->query("SELECT page_url FROM page_visits LIMIT 1")->fetch();
        $this->assertNotNull($row, "Expected a row in page_visits");
        $this->assertSame('/products', $row['page_url'], "Query string must be stripped from stored page_url");
    }

    /**
     * Property: Bot visits insert a row with is_bot=1 (bots recorded but flagged).
     * Validates: Requirement 1.6
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function botVisitsFlaggedAsBot(): void
    {
        $pdo = $this->makePdo();

        $context = [
            'session_id' => session_id(),
            'ip'         => '66.249.66.1',
            'user_agent' => 'Googlebot/2.1 (+http://www.google.com/bot.html)',
            'page_url'   => '/index',
            'referrer'   => null,
        ];

        $result = recordVisit($pdo, $context);
        $this->assertTrue($result, "Bot visit should still be recorded (flagged)");

        $row = $pdo->query("SELECT is_bot FROM page_visits LIMIT 1")->fetch();
        $this->assertNotNull($row);
        $this->assertSame(1, (int)$row['is_bot'], "Bot visit must have is_bot=1");
    }

    /**
     * Property: Different pages in same session each get their own row.
     * Validates: Requirement 1.1
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function differentPagesGetSeparateRows(): void
    {
        $pdo = $this->makePdo();
        $pages = ['/index', '/products', '/cart', '/checkout'];

        foreach ($pages as $page) {
            $context = [
                'session_id' => session_id(),
                'ip'         => '127.0.0.1',
                'user_agent' => 'Mozilla/5.0 Chrome/120',
                'page_url'   => $page,
                'referrer'   => null,
            ];
            $result = recordVisit($pdo, $context);
            $this->assertTrue($result, "First visit to '$page' should be recorded");
        }

        $count = (int)$pdo->query("SELECT COUNT(*) FROM page_visits")->fetchColumn();
        $this->assertSame(count($pages), $count, "Each unique page should produce exactly one row");
    }
}
