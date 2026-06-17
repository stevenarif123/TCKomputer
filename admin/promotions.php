<?php
/**
 * Admin Promotions List
 * Displays all active and inactive promotions.
 * Action links: Add, Edit, Delete.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/admin-auth.php';

requireAdmin();
$pdo = getDBConnection();

// Handle delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($token)) {
        redirect('promotions', 'Permintaan tidak valid, silakan coba lagi', 'error');
    }

    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        $deleteStmt = $pdo->prepare("DELETE FROM promotions WHERE id = ?");
        $deleteStmt->execute([$id]);
        redirect('promotions', 'Promosi berhasil dihapus.', 'success');
    }

    redirect('promotions', 'ID promosi tidak valid.', 'error');
}

$pageTitle = "Kelola Promosi";
require_once __DIR__ . '/../includes/admin-header.php';

// Query all promotions ordered by newest
$stmt = $pdo->query("SELECT p.*, c.name as category_name, prod.name as product_name 
                     FROM promotions p 
                     LEFT JOIN categories c ON p.target_category_id = c.id 
                     LEFT JOIN products prod ON p.free_item_id = prod.id 
                     ORDER BY p.id DESC");
$promotions = $stmt->fetchAll();
?>

<div class="admin-page-header">
    <h2>Kelola Promosi / Diskon</h2>
    <a href="promotion-add" class="btn btn-primary">+ Tambah Promosi</a>
</div>

<div class="table-responsive">
    <table class="admin-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Nama Promosi</th>
                <th>Tipe</th>
                <th>Nilai Diskon</th>
                <th>Min. Belanja</th>
                <th>Periode</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($promotions)): ?>
                <tr>
                    <td colspan="8" class="text-center">Tidak ada promosi ditemukan.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($promotions as $index => $promo): ?>
                <tr>
                    <td><?= $index + 1 ?></td>
                    <td>
                        <strong><?= sanitizeOutput($promo['name']) ?></strong>
                        <div style="font-size: 11px; color: #76777d; margin-top: 4px;">
                            <?php if ($promo['promo_type'] === 'category_discount' && $promo['category_name']): ?>
                                Kategori: <?= sanitizeOutput($promo['category_name']) ?>
                            <?php elseif ($promo['promo_type'] === 'free_item' && $promo['product_name']): ?>
                                Item: <?= sanitizeOutput($promo['product_name']) ?>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <?php
                            $types = [
                                'free_shipping' => 'Gratis Ongkir',
                                'category_discount' => 'Diskon Kategori',
                                'free_item' => 'Gratis Item',
                                'cart_discount' => 'Diskon Keranjang'
                            ];
                            echo $types[$promo['promo_type']] ?? $promo['promo_type'];
                        ?>
                    </td>
                    <td>
                        <?php if ($promo['promo_type'] === 'free_item'): ?>
                            <span class="text-muted">Gratis Barang</span>
                        <?php elseif ($promo['discount_type'] === 'percentage'): ?>
                            <?= (int)$promo['discount_value'] ?>%
                        <?php else: ?>
                            <?= formatRupiah((int)$promo['discount_value']) ?>
                        <?php endif; ?>
                    </td>
                    <td><?= $promo['min_spend'] > 0 ? formatRupiah((int)$promo['min_spend']) : '-' ?></td>
                    <td style="font-size: 12px;">
                        <?= date('d M Y', strtotime($promo['start_date'])) ?> -<br>
                        <?= date('d M Y', strtotime($promo['end_date'])) ?>
                    </td>
                    <td>
                        <?php 
                        $now = time();
                        $start = strtotime($promo['start_date']);
                        $end = strtotime($promo['end_date']);
                        if (!$promo['is_active']) {
                            echo '<span style="color: #dc2626; font-weight: bold;">Tidak Aktif</span>';
                        } elseif ($now < $start) {
                            echo '<span style="color: #ea580c; font-weight: bold;">Akan Datang</span>';
                        } elseif ($now > $end) {
                            echo '<span style="color: #76777d; font-weight: bold;">Kedaluwarsa</span>';
                        } else {
                            echo '<span style="color: #16a34a; font-weight: bold;">Aktif</span>';
                        }
                        ?>
                    </td>
                    <td class="action-links">
                        <a href="promotion-edit?id=<?= (int) $promo['id'] ?>" class="btn btn-sm btn-secondary">Edit</a>
                        <form method="POST" action="promotions" class="inline-form" onsubmit="return confirm('Yakin ingin menghapus promosi ini?');">
                            <input type="hidden" name="csrf_token" value="<?= sanitizeOutput($csrfToken) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $promo['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
