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

<div class="admin-grid-layout" style="display: grid; grid-template-columns: 1fr; gap: 20px;">
    
    <div style="display: grid; grid-template-columns: 1fr; gap: 20px; grid-template-areas: 'add' 'settings'; @media(min-width: 1024px) { grid-template-columns: 2fr 1fr; grid-template-areas: 'add settings'; }">
        <!-- Add Product to Flash Sale Card -->
        <div class="admin-card" style="background: white; padding: 20px; border-radius: 12px; border: 1px solid var(--outline-color, #c6c6cd); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); grid-area: add;">
            <h3 style="margin-top: 0; margin-bottom: 15px; font-weight: 800;">Atur Flash Sale Baru</h3>
            
            <?php if (empty($eligibleProducts)): ?>
                <p class="text-muted" style="margin: 0; font-size: 14px;">Semua produk aktif sudah memiliki promo aktif.</p>
            <?php else: ?>
                <form method="POST" action="flash-sales" style="display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end;">
                    <input type="hidden" name="csrf_token" value="<?= sanitizeOutput($csrfToken) ?>">
                    <input type="hidden" name="action" value="save_promo">
                    
                    <div class="form-group" style="flex: 2; min-width: 200px; margin-bottom: 0;">
                        <label for="product_id" style="font-weight: 700; font-size: 13px; display: block; margin-bottom: 5px;">Pilih Produk</label>
                        <select id="product_id" name="product_id" required style="width: 100%; padding: 8px 12px; border: 1px solid #c6c6cd; border-radius: 8px; outline: none; background: white;" onchange="updateRegularPriceHint()">
                            <option value="">-- Pilih Produk --</option>
                            <?php foreach ($eligibleProducts as $ep): ?>
                                <option value="<?= (int)$ep['id'] ?>" data-price="<?= (int)$ep['selling_price'] ?>" data-stock="<?= (int)$ep['stock'] ?>">
                                    <?= sanitizeOutput($ep['name']) ?> (Harga Jual: <?= formatRupiah($ep['selling_price']) ?>, Stok: <?= (int)$ep['stock'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group" style="flex: 1; min-width: 120px; margin-bottom: 0;">
                        <label for="promo_price" style="font-weight: 700; font-size: 13px; display: block; margin-bottom: 5px;">Harga Promo (Rp)</label>
                        <input type="number" id="promo_price" name="promo_price" required min="1" placeholder="Harga diskon baru" style="width: 100%; padding: 8px 12px; border: 1px solid #c6c6cd; border-radius: 8px; outline: none;">
                    </div>

                    <div class="form-group" style="flex: 1; min-width: 100px; margin-bottom: 0;">
                        <label for="promo_stock" style="font-weight: 700; font-size: 13px; display: block; margin-bottom: 5px;">Stok Promo</label>
                        <input type="number" id="promo_stock" name="promo_stock" required min="1" placeholder="Kuota promo" style="width: 100%; padding: 8px 12px; border: 1px solid #c6c6cd; border-radius: 8px; outline: none;">
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="padding: 10px 20px; font-weight: 700; border-radius: 8px; cursor: pointer; height: 38px;">Aktifkan Promo</button>
                </form>
            <?php endif; ?>
        </div>

        <!-- Configure Flash Sale Settings Card -->
        <div class="admin-card" style="background: white; padding: 20px; border-radius: 12px; border: 1px solid var(--outline-color, #c6c6cd); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); grid-area: settings;">
            <h3 style="margin-top: 0; margin-bottom: 15px; font-weight: 800;">Pengaturan Flash Sale</h3>
            <form method="POST" action="flash-sales" style="display: flex; flex-direction: column; gap: 12px;">
                <input type="hidden" name="csrf_token" value="<?= sanitizeOutput($csrfToken) ?>">
                <input type="hidden" name="action" value="update_settings">
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="flash_sale_title" style="font-weight: 700; font-size: 13px; display: block; margin-bottom: 5px;">Judul Flash Sale</label>
                    <input type="text" id="flash_sale_title" name="flash_sale_title" value="<?= sanitizeOutput($flashSaleTitle) ?>" required style="width: 100%; padding: 8px 12px; border: 1px solid #c6c6cd; border-radius: 8px; outline: none; background: white;">
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label for="flash_sale_subtitle" style="font-weight: 700; font-size: 13px; display: block; margin-bottom: 5px;">Label Hitung Mundur</label>
                    <input type="text" id="flash_sale_subtitle" name="flash_sale_subtitle" value="<?= sanitizeOutput($flashSaleSubtitle) ?>" required style="width: 100%; padding: 8px 12px; border: 1px solid #c6c6cd; border-radius: 8px; outline: none; background: white;">
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label for="flash_sale_end" style="font-weight: 700; font-size: 13px; display: block; margin-bottom: 5px;">Waktu Berakhir</label>
                    <input type="datetime-local" id="flash_sale_end" name="flash_sale_end" value="<?= $inputDateTime ?>" required style="width: 100%; padding: 8px 12px; border: 1px solid #c6c6cd; border-radius: 8px; outline: none; background: white;">
                </div>

                <div class="form-group form-checkbox" style="margin-bottom: 5px; display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" id="flash_sale_active" name="flash_sale_active" value="1" <?= $flashSaleActive ? 'checked' : '' ?> style="width: 16px; height: 16px; cursor: pointer;">
                    <label for="flash_sale_active" style="font-weight: 700; font-size: 13px; cursor: pointer; user-select: none;">Flash Sale Aktif secara Global</label>
                </div>
                
                <button type="submit" class="btn btn-primary" style="padding: 8px 15px; font-weight: 700; border-radius: 8px; cursor: pointer; width: 100%;">Simpan Pengaturan</button>
            </form>
        </div>
    </div>

    <!-- Active Flash Sales List -->
    <div class="admin-card" style="background: white; padding: 20px; border-radius: 12px; border: 1px solid var(--outline-color, #c6c6cd); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); margin-top: 10px;">
        <h3 style="margin-top: 0; margin-bottom: 15px; font-weight: 800;">Daftar Flash Sale Aktif</h3>
        
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nama Produk</th>
                        <th>Kategori</th>
                        <th class="text-right">Harga Jual</th>
                        <th class="text-right">Harga Promo</th>
                        <th class="text-center">Hemat (%)</th>
                        <th class="text-center">Stok Promo</th>
                        <th class="text-center">Stok Fisik</th>
                        <th class="text-center">Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($flashSales)): ?>
                        <tr>
                            <td colspan="10" class="text-center" style="padding: 20px; color: #76777d; font-style: italic;">Tidak ada produk Flash Sale yang sedang aktif saat ini.</td>
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
                            <td>
                                <strong><?= sanitizeOutput($fs['name']) ?></strong>
                            </td>
                            <td><?= sanitizeOutput($fs['category_name'] ?: '-') ?></td>
                            <td class="text-right"><?= formatRupiah($regularPrice) ?></td>
                            <td class="text-right" style="color: #ba1a1a; font-weight: bold;"><?= formatRupiah($promoPrice) ?></td>
                            <td class="text-center">
                                <span style="background: #ffdad6; color: #ba1a1a; padding: 3px 8px; border-radius: 9999px; font-size: 11px; font-weight: bold;">
                                    <?= $savePercent ?>%
                                </span>
                            </td>
                            <td class="text-center">
                                <strong><?= (int)$fs['promo_stock'] ?></strong> / <span class="text-muted"><?= (int)$fs['promo_stock_initial'] ?></span>
                            </td>
                            <td class="text-center"><?= (int)$fs['stock'] ?></td>
                            <td class="text-center">
                                <?php if ($fs['status'] === 'ready'): ?>
                                    <span style="color: #005321; font-weight: 700; font-size: 12px;">Ready</span>
                                <?php elseif ($fs['status'] === 'po'): ?>
                                    <span style="color: #b05c00; font-weight: 700; font-size: 12px;">Pre-Order</span>
                                <?php else: ?>
                                    <span style="color: #93000a; font-weight: 700; font-size: 12px;">Habis</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display: flex; gap: 8px; align-items: center;">
                                    <!-- Quick Edit price and stock form -->
                                    <form method="POST" action="flash-sales" style="display: flex; gap: 4px; align-items: center; margin: 0;">
                                        <input type="hidden" name="csrf_token" value="<?= sanitizeOutput($csrfToken) ?>">
                                        <input type="hidden" name="action" value="save_promo">
                                        <input type="hidden" name="product_id" value="<?= (int)$fs['id'] ?>">
                                        <div style="display: flex; flex-direction: column; gap: 4px;">
                                            <div style="display: flex; align-items: center; gap: 4px;">
                                                <span style="font-size: 10px; width: 60px; color: #76777d;">Harga:</span>
                                                <input type="number" name="promo_price" value="<?= $promoPrice ?>" required min="1" style="width: 100px; padding: 4px 8px; border: 1px solid #c6c6cd; border-radius: 6px; font-size: 12px;">
                                            </div>
                                            <div style="display: flex; align-items: center; gap: 4px;">
                                                <span style="font-size: 10px; width: 60px; color: #76777d;">Stok Promo:</span>
                                                <input type="number" name="promo_stock" value="<?= (int)$fs['promo_stock'] ?>" required min="1" max="<?= (int)$fs['stock'] ?>" style="width: 100px; padding: 4px 8px; border: 1px solid #c6c6cd; border-radius: 6px; font-size: 12px;">
                                            </div>
                                        </div>
                                        <button type="submit" class="btn btn-sm btn-secondary" style="padding: 8px 10px; font-size: 11px; border-radius: 6px; align-self: center; margin-left: 5px;">Simpan</button>
                                    </form>
                                    
                                    <!-- Stop Promo -->
                                    <form method="POST" action="flash-sales" onsubmit="return confirm('Batalkan promo Flash Sale untuk produk ini?');" style="margin: 0;">
                                        <input type="hidden" name="csrf_token" value="<?= sanitizeOutput($csrfToken) ?>">
                                        <input type="hidden" name="action" value="remove_promo">
                                        <input type="hidden" name="id" value="<?= (int)$fs['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" style="padding: 5px 10px; font-size: 11px; border-radius: 6px;">Batalkan</button>
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
        // Set maximum limit for promo price
        promoPriceInput.max = regularPrice - 1;
        // Suggest a starting price that is 15% off
        promoPriceInput.value = Math.round(regularPrice * 0.85);
        // Set maximum limit for promo stock
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
