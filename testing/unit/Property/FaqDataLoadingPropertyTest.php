<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/helpers.php';

use PHPUnit\Framework\TestCase;

/**
 * Property-Based Test for FAQ Data Loading
 *
 * **Validates: Requirements 1.2, 1.5**
 *
 * Property 1: Public FAQ data loading returns only active entries under active non-empty categories
 */
class FaqDataLoadingPropertyTest extends TestCase
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
     * Test Property 1 across multiple random configurations.
     *
     * @test
     */
    public function testLoadFaqDataFiltersActiveAndNonEmptyCategoriesAndActiveFaqs(): void
    {
        // Run 100 iterations of random state configurations
        for ($iteration = 0; $iteration < 100; $iteration++) {
            $this->clearTables();

            // Generate random categories: 2 to 10
            $numCategories = mt_rand(2, 10);
            $categories = [];
            
            for ($c = 1; $c <= $numCategories; $c++) {
                $isCatActive = mt_rand(0, 1);
                $catName = "Category {$iteration}-{$c}";
                
                $stmt = $this->pdo->prepare("
                    INSERT INTO faq_categories (name, is_active, sort_order)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$catName, $isCatActive, $c]);
                $catId = (int)$this->pdo->lastInsertId();
                
                $categories[$catId] = [
                    'id' => $catId,
                    'name' => $catName,
                    'is_active' => $isCatActive,
                    'faqs' => []
                ];
            }

            // Generate random FAQs: 0 to 5 for each category
            foreach ($categories as $catId => &$catInfo) {
                $numFaqs = mt_rand(0, 5);
                for ($f = 1; $f <= $numFaqs; $f++) {
                    $isFaqActive = mt_rand(0, 1);
                    $question = "Question {$catId}-{$f}";
                    $answer = "Answer {$catId}-{$f}";
                    
                    $stmt = $this->pdo->prepare("
                        INSERT INTO faqs (faq_category_id, question, answer, is_active, sort_order)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$catId, $question, $answer, $isFaqActive, $f]);
                    $faqId = (int)$this->pdo->lastInsertId();
                    
                    $faqInfo = [
                        'id' => $faqId,
                        'faq_category_id' => $catId,
                        'question' => $question,
                        'answer' => $answer,
                        'is_active' => $isFaqActive,
                    ];
                    
                    $catInfo['faqs'][$faqId] = $faqInfo;
                }
            }
            unset($catInfo);

            // Execute loadFaqData
            $actualData = loadFaqData($this->pdo);

            // Calculate expected categories and their expected FAQs
            $expectedData = [];
            foreach ($categories as $catId => $catInfo) {
                if ($catInfo['is_active'] === 0) {
                    // Category is inactive, should not be loaded at all
                    continue;
                }

                $activeFaqs = [];
                foreach ($catInfo['faqs'] as $faqId => $faqInfo) {
                    if ($faqInfo['is_active'] === 1) {
                        $activeFaqs[] = $faqInfo;
                    }
                }

                if (empty($activeFaqs)) {
                    // Category has no active FAQs, should not be loaded
                    continue;
                }

                $expectedData[$catId] = [
                    'id' => $catId,
                    'name' => $catInfo['name'],
                    'is_active' => $catInfo['is_active'],
                    'faqs' => $activeFaqs
                ];
            }

            // Assert the properties:
            // 1. Count of loaded categories matches expected count
            $this->assertCount(
                count($expectedData),
                $actualData,
                "Iteration {$iteration}: The number of categories loaded does not match the expected count."
            );

            // Map actual categories by ID for easy validation
            $actualById = [];
            foreach ($actualData as $actualCat) {
                $actualById[$actualCat['id']] = $actualCat;
            }

            foreach ($expectedData as $catId => $expectedCat) {
                // 2. The category must exist in actual output
                $this->assertArrayHasKey(
                    $catId,
                    $actualById,
                    "Iteration {$iteration}: Expected category ID {$catId} is missing in actual output."
                );

                $actualCat = $actualById[$catId];

                // 3. Category properties match
                $this->assertEquals(
                    1,
                    (int)$actualCat['is_active'],
                    "Iteration {$iteration}: Loaded category ID {$catId} is inactive."
                );

                // 4. Expected active FAQs match exactly
                $expectedFaqIds = array_column($expectedCat['faqs'], 'id');
                $actualFaqIds = array_column($actualCat['faqs'], 'id');
                sort($expectedFaqIds);
                sort($actualFaqIds);

                $this->assertEquals(
                    $expectedFaqIds,
                    $actualFaqIds,
                    "Iteration {$iteration}: FAQs for category ID {$catId} do not match the expected active FAQs."
                );

                // 5. Ensure all FAQs inside the loaded category are indeed active
                foreach ($actualCat['faqs'] as $faq) {
                    $this->assertEquals(
                        1,
                        (int)$faq['is_active'],
                        "Iteration {$iteration}: Loaded FAQ ID {$faq['id']} is inactive."
                    );
                    $this->assertEquals(
                        $catId,
                        (int)$faq['faq_category_id'],
                        "Iteration {$iteration}: Loaded FAQ ID {$faq['id']} belongs to category ID {$faq['faq_category_id']} instead of {$catId}."
                    );
                }
            }

            // 6. Ensure no inactive categories or empty categories appear in actual output
            foreach ($actualData as $actualCat) {
                $this->assertEquals(
                    1,
                    (int)$actualCat['is_active'],
                    "Iteration {$iteration}: Loaded category {$actualCat['name']} (ID: {$actualCat['id']}) must be active."
                );
                $this->assertNotEmpty(
                    $actualCat['faqs'],
                    "Iteration {$iteration}: Loaded category {$actualCat['name']} (ID: {$actualCat['id']}) must not be empty."
                );
            }
        }
    }
}
