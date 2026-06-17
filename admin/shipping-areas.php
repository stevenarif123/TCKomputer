<?php
/**
 * Admin Shipping Areas List
 * Displays all shipping areas with name, cost, active status.
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
        redirect('shipping-areas', 'Permintaan tidak valid, silakan coba lagi', 'error');
    }

    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        // Check if shipping area is used in any order
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE shipping_area_id = ?");
        $checkStmt->execute([$id]);
        $orderCount = (int) $checkStmt->fetchColumn();

        if ($orderCount > 0) {
            redirect('shipping-areas', 'Area pengiriman tidak dapat dihapus karena sudah digunakan dalam pesanan.', 'error');
        }

        $deleteStmt = $pdo->prepare("DELETE FROM shipping_areas WHERE id = ?");
        $deleteStmt->execute([$id]);
        redirect('shipping-areas', 'Area pengiriman berhasil dihapus.', 'success');
    }

    redirect('shipping-areas', 'ID area pengiriman tidak valid.', 'error');
}

$pageTitle = "Kelola Area Pengiriman";
require_once __DIR__ . '/../includes/admin-header.php';

// Query all shipping areas ordered by regency and name
$stmt = $pdo->query("SELECT * FROM shipping_areas ORDER BY regency ASC, area_name ASC");
$shippingAreas = $stmt->fetchAll();
?>

<div class="admin-page-header">
    <h2>Kelola Area Pengiriman</h2>
    <a href="shipping-area-add" class="btn btn-primary">+ Tambah Area</a>
</div>

<!-- Shipping Areas Table -->
<div class="table-responsive">
    <table class="admin-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Kabupaten/Kota</th>
                <th>Nama Area</th>
                <th>Biaya</th>
                <th>Aktif</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($shippingAreas)): ?>
                <tr>
                    <td colspan="6" class="text-center">Tidak ada area pengiriman ditemukan.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($shippingAreas as $index => $area): ?>
                <tr>
                    <td><?= $index + 1 ?></td>
                    <td><?= sanitizeOutput($area['regency']) ?></td>
                    <td><?= sanitizeOutput($area['area_name']) ?></td>
                    <td><?= formatRupiah((int) $area['cost']) ?></td>
                    <td><?= $area['is_active'] ? 'Ya' : 'Tidak' ?></td>
                    <td class="action-links">
                        <a href="shipping-area-edit?id=<?= (int) $area['id'] ?>" class="btn btn-sm btn-secondary">Edit</a>
                        <form method="POST" action="shipping-areas" class="inline-form" onsubmit="return confirm('Yakin ingin menghapus area pengiriman ini?');">
                            <input type="hidden" name="csrf_token" value="<?= sanitizeOutput($csrfToken) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $area['id'] ?>">
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
