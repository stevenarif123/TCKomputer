<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';

use PHPUnit\Framework\TestCase;

/**
 * Property-Based Test for Product Filtering
 *
 * **Validates: Requirements 1.2, 1.3, 1.4**
 *
 * Property 13: Search and Filter Correctness
 * For ANY arbitrary combination of filters (keyword search, category, status),
 * ALL returned products must satisfy ALL active filters simultaneously,
 * and only active products (is_active = 1) may appear in results.
 */
class ProductFilterPropertyTest extends TestCase
{
    /**
     * Number of random iterations per test method.
     */
    private const ITERATIONS = 200;

    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = getDBConnection();
    }

    /**
     * Generate a random search keyword from existing product data or random strings.
     */
    private function generateRandomKeyword(): string
    {
        $type = mt_rand(0, 4);

        switch ($type) {
            case 0:
                // Substring from existing product names
                $stmt = $this->pdo->query("SELECT name FROM products ORDER BY RAND() LIMIT 1");
                $row = $stmt->fetch();
                if ($row) {
                    $name = $row['name'];
                    $len = mb_strlen($name);
                    if ($len > 2) {
                        $start = mt_rand(0, max(0, $len - 3));
                        $subLen = mt_rand(2, min(8, $len - $start));
                        return mb_substr($name, $start, $subLen);
                    }
                    return $name;
                }
                return 'laptop';

            case 1:
                // Substring from existing brand
                $stmt = $this->pdo->query("SELECT brand FROM products WHERE brand IS NOT NULL AND brand != '' ORDER BY RAND() LIMIT 1");
                $row = $stmt->fetch();
                if ($row) {
                    $brand = $row['brand'];
                    $len = mb_strlen($brand);
                    if ($len > 2) {
                        $start = mt_rand(0, max(0, $len - 3));
                        $subLen = mt_rand(2, min(6, $len - $start));
                        return mb_substr($brand, $start, $subLen);
                    }
                    return $brand;
                }
                return 'Logitech';

            case 2:
                // Common tech keywords
                $keywords = ['USB', 'HDMI', 'laptop', 'mouse', 'SSD', 'kabel', 'wireless', 'gaming', 'printer', 'tinta'];
                return $keywords[array_rand($keywords)];

            case 3:
                // Random short string that likely won't match
                $chars = 'abcdefghijklmnopqrstuvwxyz';
                $len = mt_rand(3, 8);
                $str = '';
                for ($i = 0; $i < $len; $i++) {
                    $str .= $chars[mt_rand(0, strlen($chars) - 1)];
                }
                return $str;

            case 4:
                // Mixed case to test case-insensitivity
                $words = ['LAPTOP', 'Mouse', 'usb', 'SaMsUnG', 'ePsOn', 'Kabel'];
                return $words[array_rand($words)];

            default:
                return 'test';
        }
    }

    /**
     * Generate a random category ID (may or may not exist).
     */
    private function generateRandomCategoryId(): int
    {
        $type = mt_rand(0, 2);

        switch ($type) {
            case 0:
                // Valid existing category
                $stmt = $this->pdo->query("SELECT id FROM categories ORDER BY RAND() LIMIT 1");
                $row = $stmt->fetch();
                return $row ? (int)$row['id'] : 1;

            case 1:
                // Random valid-range category ID
                return mt_rand(1, 10);

            case 2:
                // Non-existent category ID
                return mt_rand(100, 999);

            default:
                return 1;
        }
    }

    /**
     * Generate a random status filter value.
     */
    private function generateRandomStatus(): string
    {
        $options = ['ready', 'po', '', 'habis', 'invalid'];
        return $options[array_rand($options)];
    }

    /**
     * Execute the same filtering logic as products.php and return results.
     *
     * @return array{products: array, filters: array}
     */
    private function executeFilter(string $search, int $categoryId, string $status): array
    {
        $where = ['p.is_active = 1'];
        $params = [];

        // Search filter - same logic as products.php
        if ($search !== '') {
            $where[] = '(p.name LIKE ? OR p.brand LIKE ? OR p.description LIKE ?)';
            $searchTerm = '%' . $search . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        // Category filter - same logic as products.php
        if ($categoryId > 0) {
            $where[] = 'p.category_id = ?';
            $params[] = $categoryId;
        }

        // Status filter - same logic as products.php (only ready or po)
        if ($status !== '' && in_array($status, ['ready', 'po'])) {
            $where[] = 'p.status = ?';
            $params[] = $status;
        }

        $whereClause = implode(' AND ', $where);

        $query = "SELECT p.* FROM products p WHERE $whereClause";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        $products = $stmt->fetchAll();

        return [
            'products' => $products,
            'filters' => [
                'search' => $search,
                'categoryId' => $categoryId,
                'status' => $status,
            ],
        ];
    }

    /**
     * Property: All returned products have is_active = 1.
     * Validates: Requirement 1.2, 1.3, 1.4 (common precondition)
     *
     * @test
     */
    public function allReturnedProductsAreActive(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $search = mt_rand(0, 1) ? $this->generateRandomKeyword() : '';
            $categoryId = mt_rand(0, 1) ? $this->generateRandomCategoryId() : 0;
            $status = mt_rand(0, 1) ? $this->generateRandomStatus() : '';

            $result = $this->executeFilter($search, $categoryId, $status);

            foreach ($result['products'] as $product) {
                $this->assertEquals(
                    1,
                    (int)$product['is_active'],
                    sprintf(
                        "Inactive product (id=%d, is_active=%d) returned with filters: search='%s', category=%d, status='%s'",
                        $product['id'],
                        $product['is_active'],
                        $result['filters']['search'],
                        $result['filters']['categoryId'],
                        $result['filters']['status']
                    )
                );
            }
        }
    }

    /**
     * Property: When keyword search is active, all returned products contain
     * the keyword in name, brand, or description (case-insensitive).
     * **Validates: Requirements 1.2**
     *
     * @test
     */
    public function searchFilterMatchesNameBrandOrDescription(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $search = $this->generateRandomKeyword();
            $categoryId = mt_rand(0, 1) ? $this->generateRandomCategoryId() : 0;
            $status = mt_rand(0, 1) ? $this->generateRandomStatus() : '';

            $result = $this->executeFilter($search, $categoryId, $status);

            $searchLower = mb_strtolower($search);

            foreach ($result['products'] as $product) {
                $nameLower = mb_strtolower($product['name'] ?? '');
                $brandLower = mb_strtolower($product['brand'] ?? '');
                $descLower = mb_strtolower($product['description'] ?? '');

                $matchesName = str_contains($nameLower, $searchLower);
                $matchesBrand = str_contains($brandLower, $searchLower);
                $matchesDesc = str_contains($descLower, $searchLower);

                $this->assertTrue(
                    $matchesName || $matchesBrand || $matchesDesc,
                    sprintf(
                        "Product (id=%d, name='%s', brand='%s') does not contain keyword '%s' in name, brand, or description. Filters: category=%d, status='%s'",
                        $product['id'],
                        $product['name'],
                        $product['brand'] ?? 'NULL',
                        $search,
                        $result['filters']['categoryId'],
                        $result['filters']['status']
                    )
                );
            }
        }
    }

    /**
     * Property: When category filter is active, all returned products belong
     * to that category.
     * **Validates: Requirements 1.3**
     *
     * @test
     */
    public function categoryFilterReturnsOnlyMatchingCategory(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $search = mt_rand(0, 1) ? $this->generateRandomKeyword() : '';
            $categoryId = $this->generateRandomCategoryId();
            $status = mt_rand(0, 1) ? $this->generateRandomStatus() : '';

            // Only test when category filter is active (categoryId > 0)
            if ($categoryId <= 0) {
                $categoryId = mt_rand(1, 7);
            }

            $result = $this->executeFilter($search, $categoryId, $status);

            foreach ($result['products'] as $product) {
                $this->assertEquals(
                    $categoryId,
                    (int)$product['category_id'],
                    sprintf(
                        "Product (id=%d, name='%s', category_id=%d) does not match category filter %d. Filters: search='%s', status='%s'",
                        $product['id'],
                        $product['name'],
                        $product['category_id'],
                        $categoryId,
                        $result['filters']['search'],
                        $result['filters']['status']
                    )
                );
            }
        }
    }

    /**
     * Property: When status filter is active (ready or po), all returned products
     * have that status.
     * **Validates: Requirements 1.4**
     *
     * @test
     */
    public function statusFilterReturnsOnlyMatchingStatus(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $search = mt_rand(0, 1) ? $this->generateRandomKeyword() : '';
            $categoryId = mt_rand(0, 1) ? $this->generateRandomCategoryId() : 0;

            // Only use valid status filter values (ready or po)
            $status = mt_rand(0, 1) ? 'ready' : 'po';

            $result = $this->executeFilter($search, $categoryId, $status);

            foreach ($result['products'] as $product) {
                $this->assertEquals(
                    $status,
                    $product['status'],
                    sprintf(
                        "Product (id=%d, name='%s', status='%s') does not match status filter '%s'. Filters: search='%s', category=%d",
                        $product['id'],
                        $product['name'],
                        $product['status'],
                        $status,
                        $result['filters']['search'],
                        $result['filters']['categoryId']
                    )
                );
            }
        }
    }

    /**
     * Property: When multiple filters are combined, ALL returned products
     * satisfy ALL filters simultaneously.
     * **Validates: Requirements 1.2, 1.3, 1.4**
     *
     * @test
     */
    public function combinedFiltersAllSatisfiedSimultaneously(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            // Always apply all three filters
            $search = $this->generateRandomKeyword();
            $categoryId = $this->generateRandomCategoryId();
            if ($categoryId <= 0) {
                $categoryId = mt_rand(1, 7);
            }
            $status = mt_rand(0, 1) ? 'ready' : 'po';

            $result = $this->executeFilter($search, $categoryId, $status);
            $searchLower = mb_strtolower($search);

            foreach ($result['products'] as $product) {
                // Property 1: Must be active
                $this->assertEquals(
                    1,
                    (int)$product['is_active'],
                    sprintf(
                        "Combined filter - inactive product (id=%d) returned. Filters: search='%s', category=%d, status='%s'",
                        $product['id'],
                        $search,
                        $categoryId,
                        $status
                    )
                );

                // Property 2: Must match search keyword
                $nameLower = mb_strtolower($product['name'] ?? '');
                $brandLower = mb_strtolower($product['brand'] ?? '');
                $descLower = mb_strtolower($product['description'] ?? '');

                $this->assertTrue(
                    str_contains($nameLower, $searchLower) ||
                    str_contains($brandLower, $searchLower) ||
                    str_contains($descLower, $searchLower),
                    sprintf(
                        "Combined filter - product (id=%d, name='%s') does not match keyword '%s'",
                        $product['id'],
                        $product['name'],
                        $search
                    )
                );

                // Property 3: Must match category
                $this->assertEquals(
                    $categoryId,
                    (int)$product['category_id'],
                    sprintf(
                        "Combined filter - product (id=%d, category_id=%d) does not match category %d",
                        $product['id'],
                        $product['category_id'],
                        $categoryId
                    )
                );

                // Property 4: Must match status
                $this->assertEquals(
                    $status,
                    $product['status'],
                    sprintf(
                        "Combined filter - product (id=%d, status='%s') does not match status '%s'",
                        $product['id'],
                        $product['status'],
                        $status
                    )
                );
            }
        }
    }

    /**
     * Property: Inactive products never appear in results regardless of
     * filter combination.
     * **Validates: Requirements 1.2, 1.3, 1.4**
     *
     * @test
     */
    public function inactiveProductsNeverAppear(): void
    {
        // Get all inactive product IDs for reference
        $stmt = $this->pdo->query("SELECT id FROM products WHERE is_active = 0");
        $inactiveIds = array_column($stmt->fetchAll(), 'id');

        // If no inactive products exist, insert a temporary one for testing
        $tempProductId = null;
        if (empty($inactiveIds)) {
            $stmt = $this->pdo->prepare(
                "INSERT INTO products (category_id, name, slug, brand, description, selling_price, stock, status, is_active, created_at)
                 VALUES (1, 'Test Inactive Product', 'test-inactive-product-pbt', 'TestBrand', 'test description for filtering', 100000, 10, 'ready', 0, NOW())"
            );
            $stmt->execute();
            $tempProductId = (int)$this->pdo->lastInsertId();
            $inactiveIds = [$tempProductId];
        }

        try {
            for ($i = 0; $i < self::ITERATIONS; $i++) {
                $search = mt_rand(0, 2) === 0 ? '' : $this->generateRandomKeyword();
                $categoryId = mt_rand(0, 1) ? $this->generateRandomCategoryId() : 0;
                $status = mt_rand(0, 1) ? $this->generateRandomStatus() : '';

                $result = $this->executeFilter($search, $categoryId, $status);

                $returnedIds = array_map(fn($p) => (int)$p['id'], $result['products']);
                $intersection = array_intersect($returnedIds, $inactiveIds);

                $this->assertEmpty(
                    $intersection,
                    sprintf(
                        "Inactive product(s) [%s] appeared in results. Filters: search='%s', category=%d, status='%s'",
                        implode(', ', $intersection),
                        $result['filters']['search'],
                        $result['filters']['categoryId'],
                        $result['filters']['status']
                    )
                );
            }
        } finally {
            // Clean up temporary product
            if ($tempProductId !== null) {
                $stmt = $this->pdo->prepare("DELETE FROM products WHERE id = ?");
                $stmt->execute([$tempProductId]);
            }
        }
    }
}
