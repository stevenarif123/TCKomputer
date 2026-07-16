<?php
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/../../config/import.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE products (id INTEGER PRIMARY KEY AUTOINCREMENT, category_id INTEGER, name TEXT, slug TEXT UNIQUE, sku TEXT, brand TEXT, model TEXT, description TEXT, specification TEXT, purchase_price INTEGER, selling_price INTEGER, promo_price INTEGER, promo_active INTEGER, promo_stock INTEGER, promo_stock_initial INTEGER, stock INTEGER, status TEXT, condition_type TEXT, warranty_note TEXT, image TEXT, is_featured INTEGER, is_active INTEGER)');
$pdo->exec('CREATE TABLE product_images (product_id INTEGER, image_path TEXT, sort_order INTEGER)');

$source = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'import-confirm.png';
file_put_contents($source, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==', true));

$sourceAdd1 = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'import-confirm-add1.png';
file_put_contents($sourceAdd1, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==', true));

$sourceAdd2 = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'import-confirm-add2.png';
file_put_contents($sourceAdd2, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==', true));

$base = [
    'category_id' => 1, 'name' => 'Valid', 'slug' => 'valid', 'sku' => '', 'brand' => '', 'model' => '',
    'description' => '', 'specification' => '', 'purchase_price' => 0, 'selling_price' => 1000,
    'promo_price' => null, 'promo_active' => 0, 'promo_stock' => 0, 'promo_stock_initial' => 0,
    'stock' => 1, 'status' => 'ready', 'condition_type' => 'new', 'warranty_note' => '',
    'image' => '', 'is_featured' => 0, 'is_active' => 1,
];

$bad = $base;
$bad['name'] = null;

$result = confirmProductImport($pdo, ['rows' => [
    ['valid' => true, 'mapped' => $base, 'main_image_found' => true, 'main_image_source' => $source, 'additional_images_found' => [true, true], 'additional_image_sources' => [$sourceAdd1, $sourceAdd2]],
    ['valid' => true, 'mapped' => $bad],
    ['valid' => false, 'mapped' => $base + ['slug' => 'ignored']],
    ['valid' => true, 'mapped' => $base, 'main_image_found' => true, 'main_image_source' => $source],
]]);

assert($result === ['imported' => 2, 'failed' => 1]);
assert((int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn() === 2);
assert((int)$pdo->query('SELECT COUNT(*) FROM product_images')->fetchColumn() === 2);
assert($pdo->query('SELECT group_concat(sort_order, ",") FROM product_images')->fetchColumn() === '0,1');

// Clean up
@unlink($source);
@unlink($sourceAdd1);
@unlink($sourceAdd2);
