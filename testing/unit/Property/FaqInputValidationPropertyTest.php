<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/helpers.php';

use PHPUnit\Framework\TestCase;

/**
 * Property-Based Test for FAQ Input Validation
 *
 * **Validates: Requirements 5.2, 5.3, 5.4, 5.5, 6.4**
 *
 * Property 5: FAQ input validation enforces field constraints
 */
class FaqInputValidationPropertyTest extends TestCase
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

        $this->pdo->exec("INSERT INTO faq_categories (id, name, is_active) VALUES (1, 'Active Category', 1)");
        $this->pdo->exec("INSERT INTO faq_categories (id, name, is_active) VALUES (2, 'Inactive Category', 0)");
    }

    private function generateRandomString(int $length): string
    {
        if ($length <= 0) {
            return '';
        }
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789 ';
        $charsLength = strlen($chars);
        $str = '';
        for ($i = 0; $i < $length; $i++) {
            $str .= $chars[mt_rand(0, $charsLength - 1)];
        }
        return $str;
    }

    /**
     * Test Property 5: FAQ input validation enforces field constraints on 100 random combinations
     *
     * @test
     */
    public function testFaqInputValidationEnforcesFieldConstraints(): void
    {
        for ($iteration = 0; $iteration < 100; $iteration++) {
            // 1. Generate Question
            $qChoice = mt_rand(1, 7);
            $question = '';
            switch ($qChoice) {
                case 1:
                    $question = '';
                    break;
                case 2:
                    $question = '   ';
                    break;
                case 3:
                    $question = 'a';
                    break;
                case 4:
                    $question = $this->generateRandomString(500);
                    break;
                case 5:
                    $question = $this->generateRandomString(501);
                    break;
                case 6:
                    $question = $this->generateRandomString(mt_rand(2, 499));
                    break;
                case 7:
                    $question = $this->generateRandomString(mt_rand(502, 1000));
                    break;
            }

            // 2. Generate Answer
            $aChoice = mt_rand(1, 7);
            $answer = '';
            switch ($aChoice) {
                case 1:
                    $answer = '';
                    break;
                case 2:
                    $answer = '   ';
                    break;
                case 3:
                    $answer = 'b';
                    break;
                case 4:
                    $answer = $this->generateRandomString(5000);
                    break;
                case 5:
                    $answer = $this->generateRandomString(5001);
                    break;
                case 6:
                    $answer = $this->generateRandomString(mt_rand(2, 4999));
                    break;
                case 7:
                    $answer = $this->generateRandomString(mt_rand(5002, 10000));
                    break;
            }

            // 3. Generate Category ID
            $cChoice = mt_rand(1, 5);
            $categoryId = 1;
            switch ($cChoice) {
                case 1:
                    $categoryId = 0;
                    break;
                case 2:
                    $categoryId = mt_rand(-100, -1);
                    break;
                case 3:
                    $categoryId = 1; // valid and active
                    break;
                case 4:
                    $categoryId = 2; // valid and inactive
                    break;
                case 5:
                    $categoryId = mt_rand(3, 500); // non-existent
                    break;
            }

            // 4. Generate Sort Order
            $sChoice = mt_rand(1, 8);
            $sortOrder = 0;
            switch ($sChoice) {
                case 1:
                    $sortOrder = 0;
                    break;
                case 2:
                    $sortOrder = 999;
                    break;
                case 3:
                    $sortOrder = -1;
                    break;
                case 4:
                    $sortOrder = 1000;
                    break;
                case 5:
                    $sortOrder = mt_rand(1, 998);
                    break;
                case 6:
                    $sortOrder = mt_rand(-500, -2);
                    break;
                case 7:
                    $sortOrder = mt_rand(1001, 5000);
                    break;
                case 8:
                    $sortOrder = mt_rand(0, 999);
                    break;
            }

            $data = [
                'question' => $question,
                'answer' => $answer,
                'faq_category_id' => $categoryId,
                'sort_order' => $sortOrder,
            ];

            // Calculate expected errors
            $expectedErrors = [];

            $trimmedQ = trim((string)$question);
            if ($trimmedQ === '') {
                $expectedErrors[] = 'Pertanyaan FAQ wajib diisi';
            } elseif (strlen($trimmedQ) > 500) {
                $expectedErrors[] = 'Pertanyaan maksimal 500 karakter';
            }

            $trimmedA = trim((string)$answer);
            if ($trimmedA === '') {
                $expectedErrors[] = 'Jawaban FAQ wajib diisi';
            } elseif (strlen($trimmedA) > 5000) {
                $expectedErrors[] = 'Jawaban maksimal 5000 karakter';
            }

            if ($categoryId <= 0) {
                $expectedErrors[] = 'Kategori FAQ wajib dipilih';
            } elseif ($categoryId !== 1) {
                $expectedErrors[] = 'Kategori FAQ tidak valid atau tidak aktif';
            }

            if ($sortOrder < 0 || $sortOrder > 999) {
                $expectedErrors[] = 'Urutan harus antara 0 dan 999';
            }

            // Run validation
            $actualErrors = validateFaqInput($this->pdo, $data);

            // Assert that the exact set of errors matches
            $this->assertEquals(
                count($expectedErrors),
                count($actualErrors),
                "Iteration {$iteration}: Error count mismatch. Data: " . json_encode($data) . "\nExpected: " . json_encode($expectedErrors) . "\nActual: " . json_encode($actualErrors)
            );

            foreach ($expectedErrors as $expectedError) {
                $this->assertContains(
                    $expectedError,
                    $actualErrors,
                    "Iteration {$iteration}: Expected error '{$expectedError}' is missing. Data: " . json_encode($data) . "\nActual: " . json_encode($actualErrors)
                );
            }
        }
    }
}
