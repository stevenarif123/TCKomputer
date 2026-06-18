<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/helpers.php';

use PHPUnit\Framework\TestCase;

/**
 * Property-Based Test for Safely deleting a FAQ Category
 *
 * **Validates: Requirements 11.1, 11.2**
 *
 * Property 7: Category deletion safety — categories with FAQs are undeletable
 * Test that deleteFaqCategory deletes a category if and only if zero FAQs reference it;
 * when count > 0, deletion is rejected and database state is unchanged.
 */
class FaqCategoryDeletionSafetyPropertyTest extends TestCase
{
    private const ITERATIONS = 100;
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
    public function deletingNonExistentCategoryReturnsError(): void
    {
        $result = deleteFaqCategory($this->pdo, 999);
        $this->assertFalse($result['success']);
        $this->assertSame('Kategori FAQ tidak ditemukan', $result['message']);

        // Check DB was not modified (empty categories table remains empty)
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM faq_categories");
        $this->assertSame(0, (int)$stmt->fetchColumn());
    }

    /** @test */
    public function categoryDeletionSafetyProperty(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            // Re-setup a populated baseline database state to ensure isolation testing
            $this->pdo->exec("DELETE FROM faqs");
            $this->pdo->exec("DELETE FROM faq_categories");

            // Insert a few background categories that should never be affected
            $stmtCat = $this->pdo->prepare("INSERT INTO faq_categories (id, name) VALUES (?, ?)");
            $stmtCat->execute([101, 'Background Category A']);
            $stmtCat->execute([102, 'Background Category B']);

            $stmtFaq = $this->pdo->prepare("INSERT INTO faqs (faq_category_id, question, answer) VALUES (?, ?, ?)");
            $stmtFaq->execute([101, 'BQ1', 'BA1']);

            // Now setup our target category
            $targetId = 200 + $i;
            $targetName = "Target Category " . $i;
            $stmtCat->execute([$targetId, $targetName]);

            // Associate N FAQs with the target category
            $faqCount = mt_rand(0, 15);
            for ($f = 0; $f < $faqCount; $f++) {
                $stmtFaq->execute([$targetId, "Target Q{$f}", "Target A{$f}"]);
            }

            // Snapshot the state of database before deletion attempt
            $catsBefore = $this->pdo->query("SELECT * FROM faq_categories ORDER BY id ASC")->fetchAll();
            $faqsBefore = $this->pdo->query("SELECT * FROM faqs ORDER BY id ASC")->fetchAll();

            // Attempt deletion
            $result = deleteFaqCategory($this->pdo, $targetId);

            if ($faqCount === 0) {
                // Should succeed
                $this->assertTrue($result['success'], "Deletion should succeed when count is 0 (iter $i)");
                $this->assertSame('Kategori FAQ berhasil dihapus', $result['message']);

                // Target category should be removed from database
                $stmtCheck = $this->pdo->prepare("SELECT COUNT(*) FROM faq_categories WHERE id = ?");
                $stmtCheck->execute([$targetId]);
                $this->assertSame(0, (int)$stmtCheck->fetchColumn());

                // Other categories and FAQs must remain completely unchanged
                $catsAfter = $this->pdo->query("SELECT * FROM faq_categories ORDER BY id ASC")->fetchAll();
                $faqsAfter = $this->pdo->query("SELECT * FROM faqs ORDER BY id ASC")->fetchAll();

                // Expecting exactly the categories before minus the target category
                $expectedCats = array_values(array_filter($catsBefore, fn($c) => (int)$c['id'] !== $targetId));
                $this->assertEquals($expectedCats, $catsAfter, "Other categories should not be affected (iter $i)");
                $this->assertEquals($faqsBefore, $faqsAfter, "FAQs should not be affected when empty category is deleted (iter $i)");
            } else {
                // Should fail
                $this->assertFalse($result['success'], "Deletion should fail when count is $faqCount (iter $i)");
                $this->assertSame(
                    "Kategori tidak dapat dihapus karena masih memiliki {$faqCount} FAQ",
                    $result['message']
                );

                // Database state must be completely unchanged
                $catsAfter = $this->pdo->query("SELECT * FROM faq_categories ORDER BY id ASC")->fetchAll();
                $faqsAfter = $this->pdo->query("SELECT * FROM faqs ORDER BY id ASC")->fetchAll();

                $this->assertEquals($catsBefore, $catsAfter, "Categories table should remain unchanged (iter $i)");
                $this->assertEquals($faqsBefore, $faqsAfter, "FAQs table should remain unchanged (iter $i)");
            }
        }
    }
}
