<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/helpers.php';

use PHPUnit\Framework\TestCase;

/**
 * Property-Based Test for FAQ Category Input Validation
 *
 * **Validates: Requirements 9.2, 9.3, 9.4, 9.5**
 *
 * Property 6: FAQ category input validation enforces field constraints
 * Test that category validation accepts input if and only if name is 1–100 chars and unique,
 * description ≤ 500 chars, icon is valid Material Symbol name, sort_order is 0–999
 */
class FaqCategoryInputValidationPropertyTest extends TestCase
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

        // Populate with a few existing categories
        $this->pdo->exec("INSERT INTO faq_categories (id, name, sort_order, is_active) VALUES (1, 'Existing Category', 1, 1)");
        $this->pdo->exec("INSERT INTO faq_categories (id, name, sort_order, is_active) VALUES (2, 'Another Category', 2, 1)");
    }

    /**
     * Helper: build a valid category data array.
     */
    private function validCategoryData(): array
    {
        return [
            'name' => 'Valid Category Name',
            'description' => 'A valid description of the category.',
            'icon' => 'shopping_cart',
            'sort_order' => 5,
        ];
    }

    /** @test */
    public function validCategoryDataReturnsNoErrors(): void
    {
        $errors = validateFaqCategoryInput($this->pdo, $this->validCategoryData());
        $this->assertSame([], $errors);
    }

    /** @test */
    public function emptyNameReturnsError(): void
    {
        $data = $this->validCategoryData();
        $data['name'] = '';
        $errors = validateFaqCategoryInput($this->pdo, $data);
        $this->assertContains('Nama kategori wajib diisi', $errors);
    }

    /** @test */
    public function whitespaceOnlyNameReturnsError(): void
    {
        $data = $this->validCategoryData();
        $data['name'] = '   ';
        $errors = validateFaqCategoryInput($this->pdo, $data);
        $this->assertContains('Nama kategori wajib diisi', $errors);
    }

    /** @test */
    public function nameExceeding100CharsReturnsError(): void
    {
        $data = $this->validCategoryData();
        $data['name'] = str_repeat('a', 101);
        $errors = validateFaqCategoryInput($this->pdo, $data);
        $this->assertContains('Nama kategori maksimal 100 karakter', $errors);
    }

    /** @test */
    public function nameExactly100CharsIsValid(): void
    {
        $data = $this->validCategoryData();
        $data['name'] = str_repeat('a', 100);
        $errors = validateFaqCategoryInput($this->pdo, $data);
        $this->assertNotContains('Nama kategori maksimal 100 karakter', $errors);
    }

    /** @test */
    public function duplicateNameReturnsError(): void
    {
        $data = $this->validCategoryData();
        $data['name'] = 'Existing Category';
        $errors = validateFaqCategoryInput($this->pdo, $data);
        $this->assertContains('Nama kategori sudah digunakan', $errors);
    }

    /** @test */
    public function duplicateNameAllowedIfCategoryExcludesItself(): void
    {
        $data = $this->validCategoryData();
        $data['name'] = 'Existing Category';
        // When categoryId = 1 is passed, it should ignore the match on ID 1
        $errors = validateFaqCategoryInput($this->pdo, $data, 1);
        $this->assertNotContains('Nama kategori sudah digunakan', $errors);
    }

    /** @test */
    public function duplicateNameDisallowedIfExcludingDifferentCategory(): void
    {
        $data = $this->validCategoryData();
        $data['name'] = 'Existing Category';
        // When categoryId = 2 is passed, it should disallow match on ID 1
        $errors = validateFaqCategoryInput($this->pdo, $data, 2);
        $this->assertContains('Nama kategori sudah digunakan', $errors);
    }

    /** @test */
    public function descriptionExceeding500CharsReturnsError(): void
    {
        $data = $this->validCategoryData();
        $data['description'] = str_repeat('d', 501);
        $errors = validateFaqCategoryInput($this->pdo, $data);
        $this->assertContains('Deskripsi maksimal 500 karakter', $errors);
    }

    /** @test */
    public function descriptionExactly500CharsIsValid(): void
    {
        $data = $this->validCategoryData();
        $data['description'] = str_repeat('d', 500);
        $errors = validateFaqCategoryInput($this->pdo, $data);
        $this->assertNotContains('Deskripsi maksimal 500 karakter', $errors);
    }

    /** @test */
    public function descriptionEmptyOrMissingIsValid(): void
    {
        $data = $this->validCategoryData();
        $data['description'] = '';
        $errors = validateFaqCategoryInput($this->pdo, $data);
        $this->assertNotContains('Deskripsi maksimal 500 karakter', $errors);

        unset($data['description']);
        $errors = validateFaqCategoryInput($this->pdo, $data);
        $this->assertNotContains('Deskripsi maksimal 500 karakter', $errors);
    }

    /** @test */
    public function iconValidationAllowsValidFormat(): void
    {
        $validIcons = ['shopping_cart', 'local-shipping', 'payments_2', 'devices', 'handyman-tool_1'];
        foreach ($validIcons as $icon) {
            $data = $this->validCategoryData();
            $data['icon'] = $icon;
            $errors = validateFaqCategoryInput($this->pdo, $data);
            $this->assertSame([], $errors, "Icon '{$icon}' should be valid.");
        }
    }

    /** @test */
    public function iconValidationDisallowsInvalidCharacters(): void
    {
        $invalidIcons = ['shopping cart', 'payments!', 'devices/1', 'handyman@tool'];
        foreach ($invalidIcons as $icon) {
            $data = $this->validCategoryData();
            $data['icon'] = $icon;
            $errors = validateFaqCategoryInput($this->pdo, $data);
            $this->assertContains('Format ikon tidak valid', $errors, "Icon '{$icon}' should be invalid.");
        }
    }

    /** @test */
    public function iconExceeding100CharsReturnsError(): void
    {
        $data = $this->validCategoryData();
        $data['icon'] = str_repeat('a', 101);
        $errors = validateFaqCategoryInput($this->pdo, $data);
        $this->assertContains('Nama ikon maksimal 100 karakter', $errors);
    }

    /** @test */
    public function iconExactly100CharsIsValid(): void
    {
        $data = $this->validCategoryData();
        $data['icon'] = str_repeat('a', 100);
        $errors = validateFaqCategoryInput($this->pdo, $data);
        $this->assertNotContains('Nama ikon maksimal 100 karakter', $errors);
    }

    /** @test */
    public function iconEmptyOrMissingIsValid(): void
    {
        $data = $this->validCategoryData();
        $data['icon'] = '';
        $errors = validateFaqCategoryInput($this->pdo, $data);
        $this->assertSame([], $errors);

        unset($data['icon']);
        $errors = validateFaqCategoryInput($this->pdo, $data);
        $this->assertSame([], $errors);
    }

    /** @test */
    public function sortOrderBelowZeroReturnsError(): void
    {
        $data = $this->validCategoryData();
        $data['sort_order'] = -1;
        $errors = validateFaqCategoryInput($this->pdo, $data);
        $this->assertContains('Urutan harus antara 0 dan 999', $errors);
    }

    /** @test */
    public function sortOrderAbove999ReturnsError(): void
    {
        $data = $this->validCategoryData();
        $data['sort_order'] = 1000;
        $errors = validateFaqCategoryInput($this->pdo, $data);
        $this->assertContains('Urutan harus antara 0 dan 999', $errors);
    }

    /** @test */
    public function sortOrderAtBoundariesIsValid(): void
    {
        // sort_order = 0
        $data = $this->validCategoryData();
        $data['sort_order'] = 0;
        $errors = validateFaqCategoryInput($this->pdo, $data);
        $this->assertNotContains('Urutan harus antara 0 dan 999', $errors);

        // sort_order = 999
        $data['sort_order'] = 999;
        $errors = validateFaqCategoryInput($this->pdo, $data);
        $this->assertNotContains('Urutan harus antara 0 dan 999', $errors);
    }

    /** @test */
    public function doesNotMutateInputData(): void
    {
        $data = $this->validCategoryData();
        $original = $data;
        validateFaqCategoryInput($this->pdo, $data);
        $this->assertSame($original, $data, 'Input data must not be mutated');
    }

    private function generateRandomString(int $length, string $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789 '): string
    {
        if ($length <= 0) {
            return '';
        }
        $charsLength = strlen($chars);
        $str = '';
        for ($i = 0; $i < $length; $i++) {
            $str .= $chars[mt_rand(0, $charsLength - 1)];
        }
        return $str;
    }

    /**
     * Test Property 6: FAQ category input validation enforces field constraints on 100 random combinations
     *
     * **Validates: Requirements 9.2, 9.3, 9.4, 9.5**
     *
     * @test
     */
    public function testFaqCategoryInputValidationEnforcesFieldConstraints(): void
    {
        for ($iteration = 0; $iteration < 100; $iteration++) {
            // 1. Generate name
            $nameChoice = mt_rand(1, 7);
            $name = '';
            switch ($nameChoice) {
                case 1:
                    $name = '';
                    break;
                case 2:
                    $name = '   ';
                    break;
                case 3:
                    $name = 'Existing Category';
                    break;
                case 4:
                    $name = 'Another Category';
                    break;
                case 5:
                    $name = $this->generateRandomString(100);
                    break;
                case 6:
                    $name = $this->generateRandomString(101);
                    break;
                case 7:
                    $name = $this->generateRandomString(mt_rand(1, 99));
                    break;
            }

            // 2. Category ID parameter
            $idChoice = mt_rand(1, 4);
            $categoryIdParam = null;
            switch ($idChoice) {
                case 1:
                    $categoryIdParam = null;
                    break;
                case 2:
                    $categoryIdParam = 1;
                    break;
                case 3:
                    $categoryIdParam = 2;
                    break;
                case 4:
                    $categoryIdParam = 3;
                    break;
            }

            // 3. Generate description
            $descChoice = mt_rand(1, 5);
            $description = '';
            switch ($descChoice) {
                case 1:
                    $description = '';
                    break;
                case 2:
                    $description = null;
                    break;
                case 3:
                    $description = $this->generateRandomString(500);
                    break;
                case 4:
                    $description = $this->generateRandomString(501);
                    break;
                case 5:
                    $description = $this->generateRandomString(mt_rand(1, 499));
                    break;
            }

            // 4. Generate icon
            $iconChoice = mt_rand(1, 7);
            $icon = '';
            switch ($iconChoice) {
                case 1:
                    $icon = '';
                    break;
                case 2:
                    $icon = null;
                    break;
                case 3:
                    // Valid icon (only letters, numbers, _, -)
                    $icon = $this->generateRandomString(mt_rand(1, 99), 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_-');
                    break;
                case 4:
                    // Valid icon max length 100
                    $icon = $this->generateRandomString(100, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_-');
                    break;
                case 5:
                    // Too long valid-format icon
                    $icon = $this->generateRandomString(101, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_-');
                    break;
                case 6:
                    // Invalid characters in icon
                    $icon = $this->generateRandomString(mt_rand(1, 20)) . '!@#';
                    break;
                case 7:
                    // Valid icon but includes some valid chars
                    $icon = 'material-icon_123';
                    break;
            }

            // 5. Generate sort_order
            $sortChoice = mt_rand(1, 7);
            $sortOrder = 0;
            switch ($sortChoice) {
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
                    $sortOrder = mt_rand(-100, -2);
                    break;
                case 7:
                    $sortOrder = mt_rand(1001, 2000);
                    break;
            }

            $data = [];
            if ($name !== null) {
                $data['name'] = $name;
            }
            if ($description !== null) {
                $data['description'] = $description;
            }
            if ($icon !== null) {
                $data['icon'] = $icon;
            }
            if (mt_rand(1, 10) > 2) {
                $data['sort_order'] = $sortOrder;
            } else {
                $sortOrder = 0;
            }

            // Calculate expected errors
            $expectedErrors = [];

            $trimmedName = trim((string)($data['name'] ?? ''));
            if ($trimmedName === '') {
                $expectedErrors[] = 'Nama kategori wajib diisi';
            } elseif (strlen($trimmedName) > 100) {
                $expectedErrors[] = 'Nama kategori maksimal 100 karakter';
            } else {
                $isDuplicate = false;
                if ($trimmedName === 'Existing Category' && $categoryIdParam !== 1) {
                    $isDuplicate = true;
                }
                if ($trimmedName === 'Another Category' && $categoryIdParam !== 2) {
                    $isDuplicate = true;
                }
                if ($isDuplicate) {
                    $expectedErrors[] = 'Nama kategori sudah digunakan';
                }
            }

            $trimmedDesc = trim((string)($data['description'] ?? ''));
            if ($trimmedDesc !== '' && strlen($trimmedDesc) > 500) {
                $expectedErrors[] = 'Deskripsi maksimal 500 karakter';
            }

            $trimmedIcon = trim((string)($data['icon'] ?? ''));
            if ($trimmedIcon !== '') {
                if (strlen($trimmedIcon) > 100) {
                    $expectedErrors[] = 'Nama ikon maksimal 100 karakter';
                } elseif (!preg_match('/^[a-zA-Z0-9_-]+$/', $trimmedIcon)) {
                    $expectedErrors[] = 'Format ikon tidak valid';
                }
            }

            $sortVal = (int)($data['sort_order'] ?? 0);
            if ($sortVal < 0 || $sortVal > 999) {
                $expectedErrors[] = 'Urutan harus antara 0 dan 999';
            }

            // Run validation
            $actualErrors = validateFaqCategoryInput($this->pdo, $data, $categoryIdParam);

            // Assert that the exact set of errors matches
            $this->assertEquals(
                count($expectedErrors),
                count($actualErrors),
                "Iteration {$iteration}: Error count mismatch. Data: " . json_encode($data) . " (param: " . json_encode($categoryIdParam) . ")\nExpected: " . json_encode($expectedErrors) . "\nActual: " . json_encode($actualErrors)
            );

            foreach ($expectedErrors as $expectedError) {
                $this->assertContains(
                    $expectedError,
                    $actualErrors,
                    "Iteration {$iteration}: Expected error '{$expectedError}' is missing. Data: " . json_encode($data) . " (param: " . json_encode($categoryIdParam) . ")\nActual: " . json_encode($actualErrors)
                );
            }
        }
    }
}
