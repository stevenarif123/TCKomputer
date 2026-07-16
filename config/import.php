<?php

function parseImportCSV(string $filePath): array
{
    if (!is_readable($filePath) || ($handle = fopen($filePath, 'r')) === false) {
        throw new RuntimeException('File CSV tidak dapat dibaca.');
    }

    $headers = fgetcsv($handle, 0, ';');
    if ($headers === false) {
        fclose($handle);
        throw new RuntimeException('File CSV tidak memiliki header.');
    }

    $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
    $headers = array_map(fn($header) => str_replace(' ', '_', strtolower(trim((string) $header))), $headers);
    $rows = [];

    while (($values = fgetcsv($handle, 0, ';')) !== false) {
        $row = [];
        foreach ($headers as $index => $header) {
            $row[$header] = $values[$index] ?? '';
        }
        $rows[] = $row;
    }

    fclose($handle);
    return ['headers' => $headers, 'rows' => $rows];
}

function getDirFilesRecursive(string $dir): array
{
    $results = [];
    if (!is_dir($dir)) {
        return $results;
    }
    $files = scandir($dir) ?: [];
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        if (is_dir($path)) {
            if (str_starts_with($file, '.') || strtolower($file) === '__macosx') {
                continue;
            }
            $results = array_merge($results, getDirFilesRecursive($path));
        } else {
            if (str_starts_with($file, '.')) {
                continue;
            }
            $results[] = $path;
        }
    }
    return $results;
}

function normalizeFilenameForMatch(string $filename): string
{
    // Remove URL query string if present
    if (($pos = strpos($filename, '?')) !== false) {
        $filename = substr($filename, 0, $pos);
    }
    // Remove hash if present
    if (($pos = strpos($filename, '#')) !== false) {
        $filename = substr($filename, 0, $pos);
    }
    // Remove Shopee style suffixes like _tn, _heic, etc.
    $filename = preg_replace('/_(tn|heic)$/i', '', $filename);
    
    $filename = strtolower(basename(urldecode(trim($filename, " \t\n\r\0\x0B\"'"))));
    
    // Remove extension to compare base name
    $pathinfo = pathinfo($filename);
    $basename = $pathinfo['filename'] ?? $filename;
    
    // Remove all non-alphanumeric characters
    return preg_replace('/[^a-z0-9]/', '', $basename);
}

function matchImageFile(?string $filename, $imageFolderOrFiles): array
{
    $filename = trim((string) $filename);
    if ($filename === '' || $imageFolderOrFiles === null) {
        return ['found' => false, 'sourcePath' => ''];
    }

    $allFiles = [];
    if (is_array($imageFolderOrFiles)) {
        $allFiles = $imageFolderOrFiles;
    } elseif (is_string($imageFolderOrFiles) && is_dir($imageFolderOrFiles)) {
        $allFiles = getDirFilesRecursive($imageFolderOrFiles);
    }

    if (empty($allFiles)) {
        return ['found' => false, 'sourcePath' => ''];
    }

    $targetClean = strtolower(basename(urldecode(trim($filename, " \t\n\r\0\x0B\"'"))));

    // 1. Strict case-insensitive exact filename match
    foreach ($allFiles as $path) {
        $file = basename($path);
        if (strtolower($file) === $targetClean) {
            return ['found' => true, 'sourcePath' => $path];
        }
    }

    // 2. Extension-agnostic exact match
    $targetPathinfo = pathinfo($targetClean);
    $targetBase = $targetPathinfo['filename'] ?? $targetClean;
    foreach ($allFiles as $path) {
        $file = basename($path);
        $filePathinfo = pathinfo(strtolower($file));
        $fileBase = $filePathinfo['filename'] ?? '';
        if ($fileBase !== '' && $fileBase === $targetBase) {
            return ['found' => true, 'sourcePath' => $path];
        }
    }

    // 3. Fully normalized match (spaces, dashes, underscores ignored)
    $targetNormalized = normalizeFilenameForMatch($filename);
    if ($targetNormalized !== '') {
        foreach ($allFiles as $path) {
            $file = basename($path);
            $fileNormalized = normalizeFilenameForMatch($file);
            if ($fileNormalized !== '' && $fileNormalized === $targetNormalized) {
                return ['found' => true, 'sourcePath' => $path];
            }
        }
    }

    // 4. Substring fallback match (for target strings >= 4 chars)
    if (strlen($targetBase) >= 4) {
        foreach ($allFiles as $path) {
            $file = basename($path);
            $fileBase = pathinfo(strtolower($file), PATHINFO_FILENAME);
            if ($fileBase !== '' && (str_contains($fileBase, $targetBase) || str_contains($targetBase, $fileBase))) {
                return ['found' => true, 'sourcePath' => $path];
            }
        }
    }

    return ['found' => false, 'sourcePath' => ''];
}

function matchAdditionalImageFiles(?string $filenames, $imageFolderOrFiles): array
{
    $names = array_filter(array_map('trim', explode(',', (string) $filenames)), fn($filename) => $filename !== '');
    return array_map(fn($filename) => matchImageFile($filename, $imageFolderOrFiles), $names);
}

function importInt(array $row, string $key, int $default = 0): int
{
    $value = trim((string)($row[$key] ?? ''));
    return $value === '' ? $default : max(0, (int)preg_replace('/[^0-9-]/', '', $value));
}

function validateAndMapRow(array $row, array $categoryMap, ?string $imageFolder = null): array
{
    if (strtolower(trim((string)($row['status'] ?? ''))) !== 'completed') {
        return ['valid' => false, 'skipped' => true, 'errors' => [], 'mapped' => []];
    }

    $name = trim((string)($row['nama'] ?? ''));
    $categoryId = importInt($row, 'kategori_id');
    $sellingPrice = importInt($row, 'harga_jual');
    $stock = importInt($row, 'stock');
    $promoPrice = importInt($row, 'promo_price');
    $errors = [];

    if ($name === '') {
        $errors[] = 'Nama produk wajib diisi.';
    } elseif (mb_strlen($name) > 255) {
        $errors[] = 'Nama produk maksimal 255 karakter.';
    }
    if ($categoryId <= 0 || !array_key_exists($categoryId, $categoryMap)) {
        $errors[] = 'Kategori tidak ditemukan atau tidak aktif.';
    }
    if ($sellingPrice <= 0) {
        $errors[] = 'Harga jual harus lebih dari 0.';
    }

    $mapped = [
        'category_id' => $categoryId,
        'name' => $name,
        'slug' => generateSlug($name),
        'sku' => trim((string)($row['nama_item'] ?? '')),
        'stock' => $stock,
        'purchase_price' => importInt($row, 'harga_beli'),
        'selling_price' => $sellingPrice,
        'promo_price' => $promoPrice > 0 ? $promoPrice : null,
        'promo_active' => $promoPrice > 0 ? 1 : 0,
        'promo_stock' => $promoPrice > 0 ? $stock : 0,
        'promo_stock_initial' => $promoPrice > 0 ? $stock : 0,
        'brand' => trim((string)($row['brand'] ?? '')),
        'model' => trim((string)($row['model'] ?? '')),
        'description' => trim((string)($row['description'] ?? '')),
        'specification' => trim((string)($row['specification'] ?? '')),
        'image' => trim((string)($row['image'] ?? '')),
        'status' => 'ready',
        'condition_type' => 'new',
        'warranty_note' => '',
        'is_featured' => 0,
        'is_active' => 1,
    ];

    return ['valid' => !$errors, 'skipped' => false, 'errors' => $errors, 'mapped' => $mapped];
}

function loadActiveCategoryMap(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT id, name FROM categories WHERE is_active = 1');
    return $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
}

function buildImportPreviewData(array $csvRows, array $categoryMap, ?string $imageFolder, array $imageWarnings = []): array
{
    $stats = [
        'total_csv_rows' => count($csvRows),
        'skipped_not_completed' => 0,
        'valid' => 0,
        'invalid' => 0,
        'images_matched' => 0,
        'images_missing' => 0,
    ];
    $rows = [];

    // Pre-scan all files in the image folder recursively once to optimize performance
    $allFiles = [];
    if ($imageFolder !== null && is_dir($imageFolder)) {
        $allFiles = getDirFilesRecursive($imageFolder);
    }

    foreach ($csvRows as $index => $csvRow) {
        $validated = validateAndMapRow($csvRow, $categoryMap, $imageFolder);
        if ($validated['skipped']) {
            $stats['skipped_not_completed']++;
            $rows[] = ['row_num' => $index + 1, 'skipped' => true, 'valid' => false, 'errors' => [], 'mapped' => []];
            continue;
        }

        $additionalImages = array_values(array_filter(array_map('trim', explode(',', (string)($csvRow['semua_gambar'] ?? ''))), fn($name) => $name !== ''));
        $mainImage = trim((string)($csvRow['image'] ?? '')) ?: ($additionalImages[0] ?? '');
        $mainMatch = matchImageFile($mainImage, $allFiles);
        $additionalMatches = array_map(fn($name) => matchImageFile($name, $allFiles), $additionalImages);

        foreach (array_merge($mainImage === '' ? [] : [$mainMatch], $additionalMatches) as $match) {
            $stats[$match['found'] ? 'images_matched' : 'images_missing']++;
        }

        if ($validated['valid'] && !$mainMatch['found']) {
            $validated['valid'] = false;
            $validated['errors'][] = 'Gambar utama wajib cocok sebelum produk bisa diimport.';
        }
        $stats[$validated['valid'] ? 'valid' : 'invalid']++;

        $rows[] = $validated + [
            'row_num' => $index + 1,
            'main_image' => $mainImage,
            'main_image_found' => $mainMatch['found'],
            'main_image_source' => $mainMatch['sourcePath'],
            'additional_images' => $additionalImages,
            'additional_images_found' => array_column($additionalMatches, 'found'),
            'additional_image_sources' => array_column($additionalMatches, 'sourcePath'),
        ];
    }

    return ['image_folder' => $imageFolder, 'image_warnings' => $imageWarnings, 'rows' => $rows, 'stats' => $stats];
}

function uniqueProductSlug(PDO $pdo, string $name): string
{
    $base = generateSlug($name) ?: 'product';
    $base = substr($base, 0, 255);
    $exists = $pdo->prepare('SELECT 1 FROM products WHERE slug = ? LIMIT 1');
    $suffixBase = time();

    for ($count = 0; ; $count++) {
        $suffix = $count === 0 ? '' : '-' . $suffixBase . '-' . $count;
        $slug = substr($base, 0, 255 - strlen($suffix)) . $suffix;
        $exists->execute([$slug]);
        if ($exists->fetchColumn() === false) {
            return $slug;
        }
    }
}

function confirmProductImport(PDO $pdo, array $importData): array
{
    $insert = $pdo->prepare(
        'INSERT INTO products (category_id, name, slug, sku, brand, model, description, specification, purchase_price, selling_price, promo_price, promo_active, promo_stock, promo_stock_initial, stock, status, condition_type, warranty_note, image, is_featured, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $insertImage = $pdo->prepare('INSERT INTO product_images (product_id, image_path, sort_order) VALUES (?, ?, ?)');
    $targetDir = __DIR__ . '/../uploads/products/';
    $imported = 0;
    $failed = 0;

    foreach ($importData['rows'] ?? [] as $row) {
        if (empty($row['valid']) || empty($row['mapped'])) {
            continue;
        }

        $copied = [];
        $p = $row['mapped'];
        try {
            $pdo->beginTransaction();
            $p['slug'] = uniqueProductSlug($pdo, $p['name']);
            if (empty($row['main_image_found']) || empty($row['main_image_source'])) {
                throw new RuntimeException('Gambar utama wajib ada.');
            }
            $p['image'] = copyImportImage((string)$row['main_image_source'], $targetDir);
            if ($p['image'] === false) {
                throw new RuntimeException('Gagal menyalin gambar utama.');
            }
            if ($p['image'] !== '') {
                $copied[] = $p['image'];
            }

            $insert->execute([
                $p['category_id'], $p['name'], $p['slug'], $p['sku'], $p['brand'], $p['model'],
                $p['description'], $p['specification'], $p['purchase_price'], $p['selling_price'],
                $p['promo_price'] ?? 0, $p['promo_active'], $p['promo_stock'], $p['promo_stock_initial'],
                $p['stock'], $p['status'], $p['condition_type'], $p['warranty_note'], $p['image'],
                $p['is_featured'], $p['is_active'],
            ]);

            $productId = (int)$pdo->lastInsertId();
            $sortOrder = 0;
            $mainSource = realpath((string)($row['main_image_source'] ?? '')) ?: '';
            foreach ($row['additional_image_sources'] ?? [] as $index => $sourcePath) {
                $sourcePath = (string)$sourcePath;
                if (empty($row['additional_images_found'][$index]) || $sourcePath === '' || (realpath($sourcePath) ?: '') === $mainSource) {
                    continue;
                }
                $imageName = copyImportImage($sourcePath, $targetDir);
                if ($imageName === false) {
                    throw new RuntimeException('Gagal menyalin gambar tambahan.');
                }
                $copied[] = $imageName;
                $insertImage->execute([$productId, $imageName, $sortOrder++]);
            }

            $pdo->commit();
            $imported++;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            foreach ($copied as $imageName) {
                @unlink($targetDir . $imageName);
            }
            $failed++;
            error_log('Product import row failed: ' . $e->getMessage());
        }
    }

    return ['imported' => $imported, 'failed' => $failed];
}

function copyImportImage(string $sourcePath, string $targetDir): string|false
{
    if (!is_file($sourcePath) || !is_readable($sourcePath)) {
        return false;
    }

    if (filesize($sourcePath) > 2 * 1024 * 1024) {
        return false;
    }

    $imageInfo = @getimagesize($sourcePath);
    $mimeType = $imageInfo['mime'] ?? '';
    if ($mimeType === '') {
        if (class_exists('finfo')) {
            $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($sourcePath) ?: '';
        } elseif (function_exists('mime_content_type')) {
            $mimeType = mime_content_type($sourcePath) ?: '';
        }
    }

    if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        return false;
    }

    $ext = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        return false;
    }

    if (!is_dir($targetDir) && !@mkdir($targetDir, 0755, true)) {
        return false;
    }
    if (!is_writable($targetDir)) {
        @chmod($targetDir, 0755);
        if (!is_writable($targetDir)) {
            return false;
        }
    }

    $targetPath = '';
    do {
        $filename = uniqid('img_', true) . '_' . time() . '.' . $ext;
        $targetPath = rtrim($targetDir, '/\\') . DIRECTORY_SEPARATOR . $filename;
    } while (file_exists($targetPath));

    if (!@copy($sourcePath, $targetPath)) {
        if ($targetPath !== '' && file_exists($targetPath)) {
            @unlink($targetPath);
        }
        return false;
    }

    return $filename;
}
