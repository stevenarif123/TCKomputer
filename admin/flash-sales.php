<?php
/**
 * Admin Flash Sales / Promos Management
 * Allows admin to view all products currently in Flash Sale, edit their promo prices and stock,
 * remove products from Flash Sale, and configure store settings for Flash Sale (title, subtitle, status, end time).
 */

$pageTitle = "Kelola Flash Sale";

// Process POST action BEFORE including admin-header.php (which generates a new CSRF token)
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/admin-auth.php';

requireAdmin();

$pdo = getDBConnection();

// Handle POST actions (Add, Edit Promo Price/Stock, Remove, Update Settings)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($token)) {
        redirect('flash-sales', 'Permintaan tidak valid, silakan coba lagi', 'error');
    }

    $action = $_POST['action'] ?? '';

    // Action: Add / Edit a product's Flash Sale status
    if ($action === 'save_promo') {
        $productId = (int)($_POST['product_id'] ?? 0);
        $promoPrice = isset($_POST['promo_price']) && $_POST['promo_price'] !== '' ? (int)$_POST['promo_price'] : 0;
        $promoStock = isset($_POST['promo_stock']) && $_POST['promo_stock'] !== '' ? (int)$_POST['promo_stock'] : 0;
        
        if ($productId <= 0) {
            redirect('flash-sales', 'Produk tidak valid.', 'error');
        }

        // Fetch product regular selling price & stock to validate
        $stmt = $pdo->prepare("SELECT name, selling_price, stock FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();

        if (!$product) {
            redirect('flash-sales', 'Produk tidak ditemukan.', 'error');
        }

        if ($promoPrice <= 0) {
            redirect('flash-sales', 'Harga promo harus lebih dari 0.', 'error');
        }

        if ($promoPrice >= (int)$product['selling_price']) {
            redirect('flash-sales', 'Harga promo harus lebih murah dari harga regular jual (' . formatRupiah($product['selling_price']) . ').', 'error');
        }

        if ($promoStock <= 0) {
            redirect('flash-sales', 'Stok promo harus lebih dari 0.', 'error');
        }

        if ($promoStock > (int)$product['stock']) {
            redirect('flash-sales', 'Stok promo tidak boleh melebihi stok fisik produk (' . $product['stock'] . ').', 'error');
        }

        try {
            $updateStmt = $pdo->prepare("UPDATE products SET promo_price = ?, promo_stock = ?, promo_stock_initial = ?, promo_active = 1 WHERE id = ?");
            $updateStmt->execute([$promoPrice, $promoStock, $promoStock, $productId]);
            redirect('flash-sales', 'Promo Flash Sale untuk "' . $product['name'] . '" berhasil disimpan.', 'success');
        } catch (PDOException $e) {
            error_log('Error updating promo status: ' . $e->getMessage());
            redirect('flash-sales', 'Gagal menyimpan promo, silakan coba lagi.', 'error');
        }
    }

    // Action: Remove product from Flash Sale
    if ($action === 'remove_promo') {
        $productId = (int)($_POST['id'] ?? 0);
        if ($productId > 0) {
            try {
                $updateStmt = $pdo->prepare("UPDATE products SET promo_active = 0, promo_price = NULL, promo_stock = 0, promo_stock_initial = 0 WHERE id = ?");
                $updateStmt->execute([$productId]);
                redirect('flash-sales', 'Produk berhasil dihapus dari Flash Sale.', 'success');
            } catch (PDOException $e) {
                error_log('Error removing promo status: ' . $e->getMessage());
                redirect('flash-sales', 'Gagal membatalkan promo, silakan coba lagi.', 'error');
            }
        }
    }

    // Action: Update Flash Sale Settings
    if ($action === 'update_settings') {
        $endTime = trim($_POST['flash_sale_end'] ?? '');
        $datetime = !empty($endTime) ? date('Y-m-d H:i:s', strtotime($endTime)) : null;
        $title = trim($_POST['flash_sale_title'] ?? 'Flash Sale');
        $subtitle = trim($_POST['flash_sale_subtitle'] ?? 'Berakhir dalam:');
        $active = isset($_POST['flash_sale_active']) ? 1 : 0;

        try {
            $updateSettings = $pdo->prepare("UPDATE store_settings SET flash_sale_end = ?, flash_sale_title = ?, flash_sale_subtitle = ?, flash_sale_active = ? LIMIT 1");
            $updateSettings->execute([$datetime, $title, $subtitle, $active]);
            redirect('flash-sales', 'Pengaturan Flash Sale berhasil diperbarui.', 'success');
        } catch (PDOException $e) {
            error_log('Error updating flash sale settings: ' . $e->getMessage());
            redirect('flash-sales', 'Gagal memperbarui pengaturan Flash Sale.', 'error');
        }
    }
}

// Fetch current store settings for flash sale configurations
$stmtStore = $pdo->query("SELECT flash_sale_end, flash_sale_title, flash_sale_subtitle, flash_sale_active FROM store_settings LIMIT 1");
$storeSettings = $stmtStore->fetch();
$flashSaleEnd = $storeSettings['flash_sale_end'] ?? '';
$flashSaleTitle = $storeSettings['flash_sale_title'] ?? 'Flash Sale';
$flashSaleSubtitle = $storeSettings['flash_sale_subtitle'] ?? 'Berakhir dalam:';
$flashSaleActive = isset($storeSettings['flash_sale_active']) ? (int)$storeSettings['flash_sale_active'] : 1;
$inputDateTime = !empty($flashSaleEnd) ? date('Y-m-d\TH:i', strtotime($flashSaleEnd)) : '';

// Now include the admin header
require_once __DIR__ . '/../includes/admin-header.php';

// Fetch all active Flash Sale products
$stmtActive = $pdo->query(
    "SELECT p.*, c.name AS category_name 
     FROM products p 
     LEFT JOIN categories c ON p.category_id = c.id 
     WHERE p.is_active = 1 AND p.promo_active = 1 AND p.promo_price > 0 
     ORDER BY p.name ASC"
);
$flashSales = $stmtActive->fetchAll();

// Fetch all products that are NOT currently in promo (to populate the "Add Promo" dropdown)
$stmtEligible = $pdo->query(
    "SELECT id, name, selling_price, stock 
     FROM products 
     WHERE is_active = 1 AND (promo_active = 0 OR promo_price IS NULL OR promo_price = 0) 
     ORDER BY name ASC"
);
$eligibleProducts = $stmtEligible->fetchAll();
?>

<div class="flash-sales-page" style="display:flex; flex-direction:column; gap:24px;">

    <div class="grid-2col-asym">
        <!-- Add Product to Flash Sale Card -->
        <div class="admin-card">
            <h3 class="card-section-title">
                <span class="material-symbols-outlined" style="color:var(--admin-warning);">bolt</span>
                Atur Flash Sale Baru
            </h3>

            <?php if (empty($eligibleProducts)): ?>
                <p style="margin:0; font-size:0.875rem; color:var(--admin-text-muted);">Semua produk aktif sudah memiliki promo aktif.</p>
            <?php else: ?>
                <form method="POST" action="flash-sales">
                    <input type="hidden" name="csrf_token" value="<?= sanitizeOutput($csrfToken) ?>">
                    <input type="hidden" name="action" value="save_promo">

                    <div class="form-group">
                        <label for="product_id">Pilih Produk</label>
                        <select id="product_id" name="product_id" required onchange="updateRegularPriceHint()">
                            <option value="">-- Pilih Produk --</option>
                            <?php foreach ($eligibleProducts as $ep): ?>
                                <option value="<?= (int)$ep['id'] ?>" data-price="<?= (int)$ep['selling_price'] ?>" data-stock="<?= (int)$ep['stock'] ?>">
                                    <?= sanitizeOutput($ep['name']) ?> (Harga: <?= formatRupiah($ep['selling_price']) ?>, Stok: <?= (int)$ep['stock'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="promo_price">Harga Promo (Rp)</label>
                            <input type="number" id="promo_price" name="promo_price" required min="1" placeholder="Harga diskon baru">
                        </div>
                        <div class="form-group">
                            <label for="promo_stock">Stok Promo</label>
                            <input type="number" id="promo_stock" name="promo_stock" required min="1" placeholder="Kuota promo">
                        </div>
                    </div>

                    <div class="form-actions" style="padding-top:16px; margin-top:0; border-top:none;">
                        <button type="submit" class="btn btn-primary">Aktifkan Promo</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>

        <!-- Configure Flash Sale Settings Card -->
        <div class="admin-card">
            <h3 class="card-section-title">
                <span class="material-symbols-outlined" style="color:var(--admin-info);">settings</span>
                Pengaturan Flash Sale
            </h3>
            <form method="POST" action="flash-sales" style="display:flex; flex-direction:column; gap:16px;">
                <input type="hidden" name="csrf_token" value="<?= sanitizeOutput($csrfToken) ?>">
                <input type="hidden" name="action" value="update_settings">

                <div class="form-group" style="margin-bottom:0;">
                    <label for="flash_sale_title">Judul Flash Sale</label>
                    <input type="text" id="flash_sale_title" name="flash_sale_title" value="<?= sanitizeOutput($flashSaleTitle) ?>" required>
                </div>

                <div class="form-group" style="margin-bottom:0;">
                    <label for="flash_sale_subtitle">Label Hitung Mundur</label>
                    <input type="text" id="flash_sale_subtitle" name="flash_sale_subtitle" value="<?= sanitizeOutput($flashSaleSubtitle) ?>" required>
                </div>

                <div class="form-group" style="margin-bottom:0;">
                    <label for="flash_sale_end">Waktu Berakhir</label>
                    <input type="datetime-local" id="flash_sale_end" name="flash_sale_end" value="<?= $inputDateTime ?>" required>
                </div>

                <div class="toggle-wrap">
                    <label class="toggle-switch">
                        <input type="checkbox" id="flash_sale_active" name="flash_sale_active" value="1" <?= $flashSaleActive ? 'checked' : '' ?>>
                        <span class="toggle-slider"></span>
                    </label>
                    <label for="flash_sale_active">Flash Sale Aktif secara Global</label>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Simpan Pengaturan</button>
            </form>
        </div>
    </div>

    <!-- Active Flash Sales List -->
    <div class="admin-card">
        <h3 class="card-section-title">
            <span class="material-symbols-outlined" style="color:var(--admin-primary);">list_alt</span>
            Daftar Flash Sale Aktif
        </h3>

        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nama Produk</th>
                        <th>Kategori</th>
                        <th style="text-align:right;">Harga Jual</th>
                        <th style="text-align:right;">Harga Promo</th>
                        <th style="text-align:center;">Hemat</th>
                        <th style="text-align:center;">Stok Promo</th>
                        <th style="text-align:center;">Stok Fisik</th>
                        <th style="text-align:center;">Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($flashSales)): ?>
                        <tr>
                            <td colspan="10" class="empty-message" style="padding:32px;">Tidak ada produk Flash Sale yang sedang aktif.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($flashSales as $index => $fs): ?>
                        <?php
                        $regularPrice = (int)$fs['selling_price'];
                        $promoPrice = (int)$fs['promo_price'];
                        $savePercent = $regularPrice > 0 ? round((1 - $promoPrice / $regularPrice) * 100) : 0;
                        ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><strong><?= sanitizeOutput($fs['name']) ?></strong></td>
                            <td><?= sanitizeOutput($fs['category_name'] ?: '-') ?></td>
                            <td style="text-align:right;"><?= formatRupiah($regularPrice) ?></td>
                            <td style="text-align:right; color:var(--admin-danger); font-weight:700;"><?= formatRupiah($promoPrice) ?></td>
                            <td style="text-align:center;">
                                <span class="badge badge-habis"><?= $savePercent ?>%</span>
                            </td>
                            <td style="text-align:center;">
                                <strong><?= (int)$fs['promo_stock'] ?></strong> / <span style="color:var(--admin-text-muted);"><?= (int)$fs['promo_stock_initial'] ?></span>
                            </td>
                            <td style="text-align:center;"><?= (int)$fs['stock'] ?></td>
                            <td style="text-align:center;">
                                <?php
                                $statusMap = ['ready' => ['label' => 'Ready', 'color' => 'var(--admin-success)'], 'po' => ['label' => 'Pre-Order', 'color' => 'var(--admin-warning)'], 'habis' => ['label' => 'Habis', 'color' => 'var(--admin-danger)']];
                                $s = $statusMap[$fs['status']] ?? ['label' => $fs['status'], 'color' => 'var(--admin-text-muted)'];
                                ?>
                                <span style="color:<?= $s['color'] ?>; font-weight:700; font-size:12px;"><?= $s['label'] ?></span>
                            </td>
                            <td>
                                <div class="action-links" style="flex-wrap:nowrap; gap:8px;">
                                    <form method="POST" action="flash-sales" style="display:flex; gap:4px; align-items:center; margin:0;">
                                        <input type="hidden" name="csrf_token" value="<?= sanitizeOutput($csrfToken) ?>">
                                        <input type="hidden" name="action" value="save_promo">
                                        <input type="hidden" name="product_id" value="<?= (int)$fs['id'] ?>">
                                        <input type="number" name="promo_price" value="<?= $promoPrice ?>" required min="1"
                                               style="width:90px; padding:4px 8px; border:1px solid var(--admin-border); border-radius:6px; font-size:12px; background:var(--admin-card-bg); color:var(--admin-text);">
                                        <input type="number" name="promo_stock" value="<?= (int)$fs['promo_stock'] ?>" required min="1" max="<?= (int)$fs['stock'] ?>"
                                               style="width:70px; padding:4px 8px; border:1px solid var(--admin-border); border-radius:6px; font-size:12px; background:var(--admin-card-bg); color:var(--admin-text);">
                                        <button type="submit" class="btn btn-sm btn-secondary">Simpan</button>
                                    </form>
                                    <form method="POST" action="flash-sales" onsubmit="return confirm('Batalkan promo Flash Sale untuk produk ini?');" style="margin:0;">
                                        <input type="hidden" name="csrf_token" value="<?= sanitizeOutput($csrfToken) ?>">
                                        <input type="hidden" name="action" value="remove_promo">
                                        <input type="hidden" name="id" value="<?= (int)$fs['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Batalkan</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
function updateRegularPriceHint() {
    const select = document.getElementById('product_id');
    const selectedOption = select.options[select.selectedIndex];
    const promoPriceInput = document.getElementById('promo_price');
    const promoStockInput = document.getElementById('promo_stock');
    if (selectedOption && selectedOption.value) {
        const regularPrice = parseInt(selectedOption.getAttribute('data-price')) || 0;
        const stock = parseInt(selectedOption.getAttribute('data-stock')) || 0;
        promoPriceInput.max = regularPrice - 1;
        promoPriceInput.value = Math.round(regularPrice * 0.85);
        promoStockInput.max = stock;
        promoStockInput.value = stock;
    } else {
        promoPriceInput.removeAttribute('max');
        promoPriceInput.value = '';
        promoStockInput.removeAttribute('max');
        promoStockInput.value = '';
    }
}
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
