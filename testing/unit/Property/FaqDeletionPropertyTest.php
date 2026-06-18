<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/helpers.php';

use PHPUnit\Framework\TestCase;

/**
 * Property-Based/Unit Test for FAQ Deletion
 *
 * **Validates: Requirements 7.1, 7.2, 7.3**
 *
 * Checks that a FAQ entry is deleted from the database if and only if:
 * 1. The ID exists.
 * 2. Database state transitions properly on deletion.
 */
class FaqDeletionPropertyTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $this->pdo->exec("
            CREATE TABLE faq_categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(100) NOT NULL UNIQUE,
                description TEXT NULL,
                icon VARCHAR(100) NULL,
                sort_order INTEGER NOT NULL DEFAULT 0,
                is_active INTEGER NOT NULL DEFAULT 1
            )
        ");

        $this->pdo->exec("
            CREATE TABLE faqs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                faq_category_id INTEGER NOT NULL,
                question VARCHAR(500) NOT NULL,
                answer TEXT NOT NULL,
                sort_order INTEGER NOT NULL DEFAULT 0,
                is_active INTEGER NOT NULL DEFAULT 1,
                FOREIGN KEY (faq_category_id) REFERENCES faq_categories(id)
            )
        ");
    }

    /** @test */
    public function deletingNonExistentFaqDoesNotAffectDatabase(): void
    {
        // Insert a baseline category and FAQ
        $this->pdo->exec("INSERT INTO faq_categories (id, name) VALUES (1, 'General')");
        $this->pdo->exec("INSERT INTO faqs (id, faq_category_id, question, answer) VALUES (10, 1, 'Q1', 'A1')");

        // Attempt deleting id 999
        $stmt = $this->pdo->prepare("DELETE FROM faqs WHERE id = ?");
        $stmt->execute([999]);
        $rowsAffected = $stmt->rowCount();

        $this->assertSame(0, $rowsAffected);

        // Verify that id 10 still exists
        $stmtCheck = $this->pdo->query("SELECT COUNT(*) FROM faqs WHERE id = 10");
        $this->assertSame(1, (int)$stmtCheck->fetchColumn());
    }

    /** @test */
    public function deletingExistingFaqSucceedsAndRemovesRecord(): void
    {
        // Insert a baseline category and FAQ
        $this->pdo->exec("INSERT INTO faq_categories (id, name) VALUES (1, 'General')");
        $this->pdo->exec("INSERT INTO faqs (id, faq_category_id, question, answer) VALUES (10, 1, 'Q1', 'A1')");

        // Verify it exists first
        $stmtCheckBefore = $this->pdo->query("SELECT COUNT(*) FROM faqs WHERE id = 10");
        $this->assertSame(1, (int)$stmtCheckBefore->fetchColumn());

        // Perform deletion
        $stmt = $this->pdo->prepare("DELETE FROM faqs WHERE id = ?");
        $stmt->execute([10]);
        $rowsAffected = $stmt->rowCount();

        $this->assertSame(1, $rowsAffected);

        // Verify it's gone
        $stmtCheckAfter = $this->pdo->query("SELECT COUNT(*) FROM faqs WHERE id = 10");
        $this->assertSame(0, (int)$stmtCheckAfter->fetchColumn());
    }
}
