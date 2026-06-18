<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/helpers.php';

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for validateFaqInput()
 *
 * **Validates: Requirements 5.2, 5.3, 5.4, 5.5, 6.4**
 *
 * Covers:
 * - Question: required, max 500 chars
 * - Answer: required, max 5000 chars
 * - Category: must exist and be active
 * - Sort order: 0–999
 */
class ValidateFaqInputTest extends TestCase
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
                name VARCHAR(100) NOT NULL,
                is_active INTEGER NOT NULL DEFAULT 1
            )
        ");

        // Insert one active and one inactive category
        $this->pdo->exec("INSERT INTO faq_categories (id, name, is_active) VALUES (1, 'Active Cat', 1)");
        $this->pdo->exec("INSERT INTO faq_categories (id, name, is_active) VALUES (2, 'Inactive Cat', 0)");
    }

    /**
     * Helper: build a valid data array.
     */
    private function validData(): array
    {
        return [
            'question' => 'What is the return policy?',
            'answer' => 'You may return items within 30 days.',
            'faq_category_id' => 1,
            'sort_order' => 0,
        ];
    }

    /** @test */
    public function validDataReturnsNoErrors(): void
    {
        $errors = validateFaqInput($this->pdo, $this->validData());
        $this->assertSame([], $errors);
    }

    /** @test */
    public function emptyQuestionReturnsError(): void
    {
        $data = $this->validData();
        $data['question'] = '';
        $errors = validateFaqInput($this->pdo, $data);
        $this->assertContains('Pertanyaan FAQ wajib diisi', $errors);
    }

    /** @test */
    public function whitespaceOnlyQuestionReturnsError(): void
    {
        $data = $this->validData();
        $data['question'] = '   ';
        $errors = validateFaqInput($this->pdo, $data);
        $this->assertContains('Pertanyaan FAQ wajib diisi', $errors);
    }

    /** @test */
    public function questionExceeding500CharsReturnsError(): void
    {
        $data = $this->validData();
        $data['question'] = str_repeat('a', 501);
        $errors = validateFaqInput($this->pdo, $data);
        $this->assertContains('Pertanyaan maksimal 500 karakter', $errors);
    }

    /** @test */
    public function questionExactly500CharsIsValid(): void
    {
        $data = $this->validData();
        $data['question'] = str_repeat('a', 500);
        $errors = validateFaqInput($this->pdo, $data);
        $this->assertNotContains('Pertanyaan maksimal 500 karakter', $errors);
    }

    /** @test */
    public function emptyAnswerReturnsError(): void
    {
        $data = $this->validData();
        $data['answer'] = '';
        $errors = validateFaqInput($this->pdo, $data);
        $this->assertContains('Jawaban FAQ wajib diisi', $errors);
    }

    /** @test */
    public function answerExceeding5000CharsReturnsError(): void
    {
        $data = $this->validData();
        $data['answer'] = str_repeat('b', 5001);
        $errors = validateFaqInput($this->pdo, $data);
        $this->assertContains('Jawaban maksimal 5000 karakter', $errors);
    }

    /** @test */
    public function answerExactly5000CharsIsValid(): void
    {
        $data = $this->validData();
        $data['answer'] = str_repeat('b', 5000);
        $errors = validateFaqInput($this->pdo, $data);
        $this->assertNotContains('Jawaban maksimal 5000 karakter', $errors);
    }

    /** @test */
    public function missingCategoryReturnsError(): void
    {
        $data = $this->validData();
        unset($data['faq_category_id']);
        $errors = validateFaqInput($this->pdo, $data);
        $this->assertContains('Kategori FAQ wajib dipilih', $errors);
    }

    /** @test */
    public function zeroCategoryIdReturnsError(): void
    {
        $data = $this->validData();
        $data['faq_category_id'] = 0;
        $errors = validateFaqInput($this->pdo, $data);
        $this->assertContains('Kategori FAQ wajib dipilih', $errors);
    }

    /** @test */
    public function inactiveCategoryReturnsError(): void
    {
        $data = $this->validData();
        $data['faq_category_id'] = 2; // inactive
        $errors = validateFaqInput($this->pdo, $data);
        $this->assertContains('Kategori FAQ tidak valid atau tidak aktif', $errors);
    }

    /** @test */
    public function nonExistentCategoryReturnsError(): void
    {
        $data = $this->validData();
        $data['faq_category_id'] = 999;
        $errors = validateFaqInput($this->pdo, $data);
        $this->assertContains('Kategori FAQ tidak valid atau tidak aktif', $errors);
    }

    /** @test */
    public function sortOrderBelowZeroReturnsError(): void
    {
        $data = $this->validData();
        $data['sort_order'] = -1;
        $errors = validateFaqInput($this->pdo, $data);
        $this->assertContains('Urutan harus antara 0 dan 999', $errors);
    }

    /** @test */
    public function sortOrderAbove999ReturnsError(): void
    {
        $data = $this->validData();
        $data['sort_order'] = 1000;
        $errors = validateFaqInput($this->pdo, $data);
        $this->assertContains('Urutan harus antara 0 dan 999', $errors);
    }

    /** @test */
    public function sortOrderAtBoundariesIsValid(): void
    {
        // sort_order = 0
        $data = $this->validData();
        $data['sort_order'] = 0;
        $errors = validateFaqInput($this->pdo, $data);
        $this->assertNotContains('Urutan harus antara 0 dan 999', $errors);

        // sort_order = 999
        $data['sort_order'] = 999;
        $errors = validateFaqInput($this->pdo, $data);
        $this->assertNotContains('Urutan harus antara 0 dan 999', $errors);
    }

    /** @test */
    public function multipleErrorsReturnedSimultaneously(): void
    {
        $errors = validateFaqInput($this->pdo, []);
        $this->assertContains('Pertanyaan FAQ wajib diisi', $errors);
        $this->assertContains('Jawaban FAQ wajib diisi', $errors);
        $this->assertContains('Kategori FAQ wajib dipilih', $errors);
    }

    /** @test */
    public function doesNotMutateInputData(): void
    {
        $data = $this->validData();
        $original = $data;
        validateFaqInput($this->pdo, $data);
        $this->assertSame($original, $data, 'Input data must not be mutated');
    }
}
