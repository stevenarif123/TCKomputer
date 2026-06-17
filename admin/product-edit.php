<?php
/**
 * Admin Product Edit Page
 * Pre-populates form with existing product data, handles updates including image replacement.
 * Validates CSRF token on POST submission.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/admin-auth.php';

// Protect admin page - redirect to login if not authenticated
requireAdmin();

$pdo = getDBConnection();

// Get product ID from URL
$productId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($productId <= 0) {
    redirect('products', 'Produk tidak ditemukan', 'error');
}

// Fetch product from database
try {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
} catch (PDOException $e) {
    error_log('Error fetching product: ' . $e->getMessage());
    redirect('products', 'Terjadi kesalahan, silakan coba lagi', 'error');
}

if (!$product) {
    redirect('products', 'Produk tidak ditemukan', 'error');
}

// Get categories for dropdown
$categories = $pdo->query(
    "SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name"
)->fetchAll();

// Handle form submission
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    $token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($token)) {
        redirect('product-edit?id=' . $productId, 'Permintaan tidak valid, silakan coba lagi', 'error');
    }

    // Collect form data
    $name = trim($_POST['name'] ?? '');
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $specification = trim($_POST['specification'] ?? '');
    $brand = trim($_POST['brand'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $sku = trim($_POST['sku'] ?? '');
    $purchasePrice = (int) ($_POST['purchase_price'] ?? 0);
    $sellingPrice = (int) ($_POST['selling_price'] ?? 0);
    $promoPrice = isset($_POST['promo_price']) && $_POST['promo_price'] !== '' ? (int) $_POST['promo_price'] : null;
    $promoActive = isset($_POST['promo_active']) ? 1 : 0;
    $promoStock = isset($_POST['promo_stock']) && $_POST['promo_stock'] !== '' ? (int) $_POST['promo_stock'] : 0;
    $stock = (int) ($_POST['stock'] ?? 0);
    $status = $_POST['status'] ?? 'ready';
    $conditionType = $_POST['condition_type'] ?? 'new';
    $warrantyNote = trim($_POST['warranty_note'] ?? '');
    $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    // Validation
    if (empty($name)) {
        $errors[] = 'Nama produk wajib diisi';
    } elseif (strlen($name) > 255) {
        $errors[] = 'Nama produk maksimal 255 karakter';
    }
    if ($sellingPrice <= 0) {
        $errors[] = 'Harga jual harus lebih dari 0';
    }
    if ($promoActive) {
        if ($promoPrice === null || $promoPrice <= 0) {
            $errors[] = 'Harga promo wajib diisi dan harus lebih dari 0 jika promo aktif';
        } elseif ($promoPrice >= $sellingPrice) {
            $errors[] = 'Harga promo harus lebih kecil dari harga jual regular';
        }
        if ($promoStock <= 0) {
            $errors[] = 'Stok promo wajib diisi dan harus lebih dari 0 jika promo aktif';
        } elseif ($promoStock > $stock) {
            $errors[] = 'Stok promo tidak boleh melebihi stok fisik produk (' . $stock . ')';
        }
    }
    if ($categoryId <= 0) {
        $errors[] = 'Kategori wajib dipilih';
    }
    if (!in_array($status, ['ready', 'po', 'habis'])) {
        $errors[] = 'Status tidak valid';
    }
    if (!in_array($conditionType, ['new', 'used'])) {
        $errors[] = 'Kondisi tidak valid';
    }

    // Handle image upload
    $imageName = $product['image']; // Keep existing image by default
    if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $uploadDir = __DIR__ . '/../uploads/products';
        $uploadError = '';
        $newImage = uploadImage($_FILES['image'], $uploadDir, $uploadError);
        if ($newImage === false) {
            $errors[] = 'Upload gambar gagal: ' . ($uploadError ?: 'format tidak didukung atau ukuran melebihi 2MB');
        } else {
            // Delete old image if exists
            if (!empty($product['image'])) {
                deleteImage($product['image'], $uploadDir);
            }
            $imageName = $newImage;
        }
    }

    if (empty($errors)) {
        // Generate slug from name (only if name changed)
        $slug = $product['slug'];
        if ($name !== $product['name']) {
            $slug = generateSlug($name);

            // Ensure unique slug (excluding current product)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE slug = ? AND id != ?");
            $stmt->execute([$slug, $productId]);
            if ($stmt->fetchColumn() > 0) {
                $slug .= '-' . time();
            }
        }

        try {
            $promoStockInitial = isset($product['promo_stock_initial']) ? (int)$product['promo_stock_initial'] : 0;
            // If promo stock value changed, or if it was not active previously, reset initial stock
            if (empty($product['promo_active']) || (int)$promoStock !== (int)$product['promo_stock']) {
                $promoStockInitial = $promoStock;
            }

            $stmt = $pdo->prepare(
                "UPDATE products SET 
                    category_id = ?, name = ?, slug = ?, sku = ?, brand = ?, model = ?,
                    description = ?, specification = ?, purchase_price = ?, selling_price = ?,
                    promo_price = ?, promo_active = ?, promo_stock = ?, promo_stock_initial = ?,
                    stock = ?, status = ?, condition_type = ?, warranty_note = ?,
                    image = ?, is_featured = ?, is_active = ?, updated_at = NOW()
                WHERE id = ?"
            );
            $stmt->execute([
                $categoryId, $name, $slug, $sku, $brand, $model,
                $description, $specification, $purchasePrice, $sellingPrice,
                $promoPrice, $promoActive, $promoStock, $promoStockInitial,
                $stock, $status, $conditionType, $warrantyNote,
                $imageName, $isFeatured, $isActive, $productId
            ]);

            redirect('products', 'Produk berhasil diperbarui', 'success');
        } catch (PDOException $e) {
            error_log('Error updating product: ' . $e->getMessage());
            $errors[] = 'Gagal memperbarui produk, silakan coba lagi';
        }
    }

    // Update product array with submitted data for form re-population on error
    $product['name'] = $name;
    $product['category_id'] = $categoryId;
    $product['description'] = $description;
    $product['specification'] = $specification;
    $product['brand'] = $brand;
    $product['model'] = $model;
    $product['sku'] = $sku;
    $product['purchase_price'] = $purchasePrice;
    $product['selling_price'] = $sellingPrice;
    $product['promo_price'] = $promoPrice;
    $product['promo_active'] = $promoActive;
    $product['promo_stock'] = $promoStock;
    $product['stock'] = $stock;
    $product['status'] = $status;
    $product['condition_type'] = $conditionType;
    $product['warranty_note'] = $warrantyNote;
    $product['is_featured'] = $isFeatured;
    $product['is_active'] = $isActive;
}

$pageTitle = "Edit Produk";
require_once __DIR__ . '/../includes/admin-header.php';
?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?= sanitizeOutput($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="POST" action="product-edit?id=<?= (int) $productId ?>" enctype="multipart/form-data" class="admin-form">
    <input type="hidden" name="csrf_token" value="<?= sanitizeOutput($csrfToken) ?>">

    <div class="form-group">
        <label for="name">Nama Produk <span class="required">*</span></label>
        <input type="text" id="name" name="name" required maxlength="255"
               value="<?= sanitizeOutput($product['name'] ?? '') ?>">
    </div>

    <div class="form-group">
        <label for="category_id">Kategori <span class="required">*</span></label>
        <select id="category_id" name="category_id" required>
            <option value="">-- Pilih Kategori --</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?= (int) $category['id'] ?>"
                    <?= ((int) $product['category_id'] === (int) $category['id']) ? 'selected' : '' ?>>
                    <?= sanitizeOutput($category['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label for="sku">SKU</label>
        <input type="text" id="sku" name="sku" maxlength="100"
               value="<?= sanitizeOutput($product['sku'] ?? '') ?>">
    </div>

    <div class="form-group">
        <label for="brand">Brand</label>
        <input type="text" id="brand" name="brand" maxlength="100"
               value="<?= sanitizeOutput($product['brand'] ?? '') ?>">
    </div>

    <div class="form-group">
        <label for="model">Model</label>
        <input type="text" id="model" name="model" maxlength="100"
               value="<?= sanitizeOutput($product['model'] ?? '') ?>">
    </div>

    <div class="form-group">
        <label for="description">Deskripsi</label>
        <textarea id="description" name="description" rows="4"><?= sanitizeOutput($product['description'] ?? '') ?></textarea>
    </div>

    <div class="form-group">
        <label for="specification">Spesifikasi</label>
        <textarea id="specification" name="specification" rows="4"><?= sanitizeOutput($product['specification'] ?? '') ?></textarea>
    </div>

    <div class="form-group">
        <label for="purchase_price">Harga Beli (Rp)</label>
        <input type="number" id="purchase_price" name="purchase_price" min="0"
               value="<?= (int) ($product['purchase_price'] ?? 0) ?>">
    </div>

    <div class="form-group">
        <label for="selling_price">Harga Jual (Rp) <span class="required">*</span></label>
        <input type="number" id="selling_price" name="selling_price" required min="1"
               value="<?= (int) ($product['selling_price'] ?? 0) ?>">
    </div>

    <div class="form-group">
        <label for="promo_price">Harga Promo / Flash Sale (Rp)</label>
        <input type="number" id="promo_price" name="promo_price" min="0"
               value="<?= isset($product['promo_price']) && $product['promo_price'] !== null ? (int)$product['promo_price'] : '' ?>"
               placeholder="Masukkan harga diskon jika ada">
    </div>

    <div class="form-group form-checkbox">
        <label>
            <input type="checkbox" name="promo_active" value="1"
                <?= !empty($product['promo_active']) ? 'checked' : '' ?>>
            Promo Aktif (Masukkan ke Flash Sale)
        </label>
    </div>

    <div class="form-group">
        <label for="promo_stock">Stok Promo (Flash Sale)</label>
        <input type="number" id="promo_stock" name="promo_stock" min="0"
               value="<?= (int) ($product['promo_stock'] ?? 0) ?>"
               placeholder="Kuota barang promo">
        <small class="form-help">Jumlah stok yang dialokasikan untuk Flash Sale. Tidak boleh melebihi stok fisik.</small>
    </div>

    <div class="form-group">
        <label for="stock">Stok</label>
        <input type="number" id="stock" name="stock" min="0"
               value="<?= (int) ($product['stock'] ?? 0) ?>">
    </div>

    <div class="form-group">
        <label for="status">Status <span class="required">*</span></label>
        <select id="status" name="status" required>
            <option value="ready" <?= ($product['status'] ?? '') === 'ready' ? 'selected' : '' ?>>Ready</option>
            <option value="po" <?= ($product['status'] ?? '') === 'po' ? 'selected' : '' ?>>Pre-Order</option>
            <option value="habis" <?= ($product['status'] ?? '') === 'habis' ? 'selected' : '' ?>>Habis</option>
        </select>
    </div>

    <div class="form-group">
        <label for="condition_type">Kondisi <span class="required">*</span></label>
        <select id="condition_type" name="condition_type" required>
            <option value="new" <?= ($product['condition_type'] ?? '') === 'new' ? 'selected' : '' ?>>Baru</option>
            <option value="used" <?= ($product['condition_type'] ?? '') === 'used' ? 'selected' : '' ?>>Bekas</option>
        </select>
    </div>

    <div class="form-group">
        <label for="warranty_note">Catatan Garansi</label>
        <input type="text" id="warranty_note" name="warranty_note" maxlength="255"
               value="<?= sanitizeOutput($product['warranty_note'] ?? '') ?>">
    </div>

    <div class="form-group">
        <label for="image">Gambar Produk</label>
        <?php if (!empty($product['image'])): ?>
            <div class="current-image">
                <img src="/uploads/products/<?= sanitizeOutput($product['image']) ?>" 
                     alt="Current product image" class="image-thumbnail">
                <p class="image-note">Gambar saat ini. Upload gambar baru untuk mengganti.</p>
            </div>
        <?php endif; ?>
        <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp"
               data-crop="true" data-aspect-ratio="1" data-width="800" data-height="800">
        <small class="form-help">Format: JPG, PNG, WebP. Maksimal 2MB. Rekomendasi ukuran: 800 x 800 piksel (rasio 1:1) dengan latar belakang putih atau transparan.</small>
    </div>

    <div class="form-group">
        <label class="checkbox-label">
            <input type="checkbox" name="is_featured" value="1"
                   <?= !empty($product['is_featured']) ? 'checked' : '' ?>>
            Produk Unggulan
        </label>
    </div>

    <div class="form-group">
        <label class="checkbox-label">
            <input type="checkbox" name="is_active" value="1"
                   <?= !empty($product['is_active']) ? 'checked' : '' ?>>
            Aktif
        </label>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        <a href="products" class="btn btn-secondary">Batal</a>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
