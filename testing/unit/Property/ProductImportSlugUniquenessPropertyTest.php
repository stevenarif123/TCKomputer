<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/helpers.php';
require_once __DIR__ . '/../../../config/import.php';

use PHPUnit\Framework\TestCase;

/**
 * Property-Based Test for product import slug uniqueness.
 *
 * **Validates: Requirements 5.2**
 */
class ProductImportSlugUniquenessPropertyTest extends TestCase
{
    /**
     * Property 4: Slug Uniqueness
     *
     * **Validates: Requirements 5.2**
     *
     * @test
     */
    public function generatedImportedSlugsStayUniqueAcrossExistingAndSameBatchNames(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE products (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, slug TEXT UNIQUE)');

        $names = [
            'USB Charger 20W',
            'USB Charger 20W',
            ' usb charger 20w ',
            'Mouse Wireless',
            'Mouse Wireless!',
            '!!!',
            '',
        ];

        $pdo->prepare('INSERT INTO products (name, slug) VALUES (?, ?)')->execute(['Existing', generateSlug($names[0])]);
        $insert = $pdo->prepare('INSERT INTO products (name, slug) VALUES (?, ?)');
        $slugs = [];

        foreach ($names as $name) {
            $slug = uniqueProductSlug($pdo, $name);
            $insert->execute([$name, $slug]);
            $slugs[] = $slug;
        }

        $this->assertSame(count($slugs), count(array_unique($slugs)));
        $this->assertSame(count($names) + 1, (int) $pdo->query('SELECT COUNT(DISTINCT slug) FROM products')->fetchColumn());
    }
}
