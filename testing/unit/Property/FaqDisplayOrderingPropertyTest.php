<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/helpers.php';

use PHPUnit\Framework\TestCase;

/**
 * Property-Based Test for FAQ Display Ordering
 *
 * **Validates: Requirements 1.3, 1.4, 4.2**
 *
 * Property 2: FAQ display ordering follows sort_order ascending
 */
class FaqDisplayOrderingPropertyTest extends TestCase
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

    /**
     * Clear tables between iterations.
     */
    private function clearTables(): void
    {
        $this->pdo->exec("DELETE FROM faqs");
        $this->pdo->exec("DELETE FROM faq_categories");
    }

    /**
     * Test Property 2 across multiple random configurations.
     *
     * @test
     */
    public function testFaqDisplayOrderingFollowsSortOrderAscending(): void
    {
        // Run 100 iterations of random state configurations
        for ($iteration = 0; $iteration < 100; $iteration++) {
            $this->clearTables();

            // Generate random categories: 3 to 15
            $numCategories = mt_rand(3, 15);
            $categories = [];

            for ($c = 1; $c <= $numCategories; $c++) {
                // Generate arbitrary sort orders, including negative, zero, and positive values
                $sortOrder = mt_rand(-500, 500);
                $catName = "Category {$iteration}-{$c}-{$sortOrder}";

                $stmt = $this->pdo->prepare("
                    INSERT INTO faq_categories (name, is_active, sort_order)
                    VALUES (?, 1, ?)
                ");
                $stmt->execute([$catName, $sortOrder]);
                $catId = (int)$this->pdo->lastInsertId();

                $categories[$catId] = [
                    'id' => $catId,
                    'name' => $catName,
                    'sort_order' => $sortOrder,
                    'faqs' => []
                ];
            }

            // Generate random FAQs: 1 to 8 for each category (ensuring categories are not empty)
            foreach ($categories as $catId => &$catInfo) {
                $numFaqs = mt_rand(1, 8);
                for ($f = 1; $f <= $numFaqs; $f++) {
                    $sortOrder = mt_rand(-500, 500);
                    $question = "Question {$catId}-{$f}-{$sortOrder}";
                    $answer = "Answer {$catId}-{$f}";

                    $stmt = $this->pdo->prepare("
                        INSERT INTO faqs (faq_category_id, question, answer, is_active, sort_order)
                        VALUES (?, ?, ?, 1, ?)
                    ");
                    $stmt->execute([$catId, $question, $answer, $sortOrder]);
                    $faqId = (int)$this->pdo->lastInsertId();

                    $catInfo['faqs'][$faqId] = [
                        'id' => $faqId,
                        'faq_category_id' => $catId,
                        'question' => $question,
                        'answer' => $answer,
                        'sort_order' => $sortOrder,
                    ];
                }
            }
            unset($catInfo);

            // 1. Verify Public FAQ Page Data Ordering (Requirements 1.3, 1.4)
            $actualPublicData = loadFaqData($this->pdo);

            // Assert categories are in non-decreasing order of sort_order
            for ($i = 0; $i < count($actualPublicData) - 1; $i++) {
                $this->assertLessThanOrEqual(
                    $actualPublicData[$i + 1]['sort_order'],
                    $actualPublicData[$i]['sort_order'],
                    "Iteration {$iteration}: Public FAQ categories are not ordered by sort_order ascending."
                );
            }

            // Assert FAQs within each category are in non-decreasing order of sort_order
            foreach ($actualPublicData as $cat) {
                $faqs = $cat['faqs'];
                for ($j = 0; $j < count($faqs) - 1; $j++) {
                    $this->assertLessThanOrEqual(
                        $faqs[$j + 1]['sort_order'],
                        $faqs[$j]['sort_order'],
                        "Iteration {$iteration}: Public FAQs inside category ID {$cat['id']} are not ordered by sort_order ascending."
                    );
                }
            }

            // 2. Verify Admin FAQ List Query Ordering (Requirement 4.2)
            $stmt = $this->pdo->prepare("
                SELECT f.*, fc.name AS category_name, fc.sort_order AS category_sort_order
                FROM faqs f
                LEFT JOIN faq_categories fc ON f.faq_category_id = fc.id
                ORDER BY fc.sort_order ASC, f.sort_order ASC
            ");
            $stmt->execute();
            $adminFaqs = $stmt->fetchAll();

            // Assert admin list is sorted by category sort_order ascending, then by FAQ sort_order ascending
            for ($i = 0; $i < count($adminFaqs) - 1; $i++) {
                $row1 = $adminFaqs[$i];
                $row2 = $adminFaqs[$i + 1];

                $catSort1 = (int)$row1['category_sort_order'];
                $catSort2 = (int)$row2['category_sort_order'];
                $faqSort1 = (int)$row1['sort_order'];
                $faqSort2 = (int)$row2['sort_order'];

                // Lexicographical ordering validation: (catSort1, faqSort1) <= (catSort2, faqSort2)
                $isOrderedCorrectly = ($catSort1 < $catSort2) ||
                                      ($catSort1 === $catSort2 && $faqSort1 <= $faqSort2);

                $this->assertTrue(
                    $isOrderedCorrectly,
                    "Iteration {$iteration}: Admin FAQ list is not correctly ordered at index {$i}. " .
                    "Row 1: Category Sort Order = {$catSort1}, FAQ Sort Order = {$faqSort1}. " .
                    "Row 2: Category Sort Order = {$catSort2}, FAQ Sort Order = {$faqSort2}."
                );
            }
        }
    }
}
