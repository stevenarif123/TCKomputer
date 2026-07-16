<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/helpers.php';
require_once __DIR__ . '/../../../config/import.php';

use PHPUnit\Framework\TestCase;

class ProductImportConfirmTest extends TestCase
{
    private array $createdUploads = [];
    private string $sourceImage;
    private string $additionalImage;

    protected function setUp(): void
    {
        $this->sourceImage = tempnam(sys_get_temp_dir(), 'import_img_') . '.png';
        file_put_contents($this->sourceImage, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='));
        
        $this->additionalImage = tempnam(sys_get_temp_dir(), 'import_img_add_') . '.png';
        file_put_contents($this->additionalImage, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='));
    }

    protected function tearDown(): void
    {
        @unlink($this->sourceImage);
        @unlink($this->additionalImage);
        foreach ($this->createdUploads as $file) {
            @unlink(__DIR__ . '/../../../uploads/products/' . $file);
        }
    }

    public function testConfirmImportsValidRowsContinuesAfterFailuresCreatesImagesAndClearsSession(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE products (id INTEGER PRIMARY KEY AUTOINCREMENT, category_id INTEGER, name TEXT, slug TEXT UNIQUE, sku TEXT, brand TEXT, model TEXT, description TEXT, specification TEXT, purchase_price INTEGER, selling_price INTEGER, promo_price INTEGER, promo_active INTEGER, promo_stock INTEGER, promo_stock_initial INTEGER, stock INTEGER, status TEXT, condition_type TEXT, warranty_note TEXT, image TEXT, is_featured INTEGER, is_active INTEGER)');
        $pdo->exec('CREATE TABLE product_images (id INTEGER PRIMARY KEY AUTOINCREMENT, product_id INTEGER, image_path TEXT, sort_order INTEGER)');

        $_SESSION['import_data'] = ['rows' => [
            $this->row('First valid', $this->sourceImage, [$this->additionalImage]),
            ['valid' => false, 'mapped' => []],
            $this->row('Broken image row', sys_get_temp_dir() . '/missing-import-image.png'),
            $this->row('Later valid', $this->sourceImage),
        ]];

        $summary = confirmProductImport($pdo, $_SESSION['import_data']);
        unset($_SESSION['import_data']);

        $this->assertSame(['imported' => 2, 'failed' => 1], $summary);
        $this->assertFalse(isset($_SESSION['import_data']));
        $this->assertSame(['First valid', 'Later valid'], $pdo->query('SELECT name FROM products ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
        $images = $pdo->query('SELECT image_path, sort_order FROM product_images')->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(1, $images);
        $this->assertSame(0, (int)$images[0]['sort_order']);
        $this->createdUploads = array_merge(
            $pdo->query("SELECT image FROM products WHERE image != ''")->fetchAll(PDO::FETCH_COLUMN),
            array_column($images, 'image_path')
        );
        foreach ($this->createdUploads as $file) {
            $this->assertFileExists(__DIR__ . '/../../../uploads/products/' . $file);
        }
    }

    private function row(string $name, string $mainSource, array $additionalSources = []): array
    {
        return [
            'valid' => true,
            'mapped' => [
                'category_id' => 1, 'name' => $name, 'slug' => generateSlug($name), 'sku' => '', 'brand' => '', 'model' => '',
                'description' => '', 'specification' => '', 'purchase_price' => 0, 'selling_price' => 1000, 'promo_price' => null,
                'promo_active' => 0, 'promo_stock' => 0, 'promo_stock_initial' => 0, 'stock' => 1, 'status' => 'ready',
                'condition_type' => 'new', 'warranty_note' => '', 'image' => '', 'is_featured' => 0, 'is_active' => 1,
            ],
            'main_image_found' => $mainSource !== '',
            'main_image_source' => $mainSource,
            'additional_images_found' => array_fill(0, count($additionalSources), true),
            'additional_image_sources' => $additionalSources,
        ];
    }
}
