<?php
/**
 * Admin Banner List
 * Displays all banners with title, image preview, sort order, active status.
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
        redirect('banners', 'Permintaan tidak valid, silakan coba lagi', 'error');
    }

    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        // Get banner image filename before deleting
        $stmt = $pdo->prepare("SELECT image FROM banners WHERE id = ?");
        $stmt->execute([$id]);
        $banner = $stmt->fetch();

        if ($banner) {
            // Delete banner record
            $deleteStmt = $pdo->prepare("DELETE FROM banners WHERE id = ?");
            $deleteStmt->execute([$id]);

            // Delete associated image file
            if (!empty($banner['image'])) {
                deleteImage($banner['image'], __DIR__ . '/../uploads/banners');
            }

            redirect('banners', 'Banner berhasil dihapus.', 'success');
        }

        redirect('banners', 'Banner tidak ditemukan.', 'error');
    }

    redirect('banners', 'ID banner tidak valid.', 'error');
}

$pageTitle = "Kelola Banner";
require_once __DIR__ . '/../includes/admin-header.php';

// Query all banners ordered by sort_order ascending
$stmt = $pdo->query("SELECT * FROM banners ORDER BY sort_order ASC");
$banners = $stmt->fetchAll();
?>

<div class="admin-page-header">
    <h2>Kelola Banner</h2>
    <a href="banner-add" class="btn btn-primary">+ Tambah Banner</a>
</div>

<!-- Banners Table -->
<div class="table-responsive">
    <table class="admin-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Judul</th>
                <th>Gambar</th>
                <th>Urutan</th>
                <th>Aktif</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($banners)): ?>
                <tr>
                    <td colspan="6" class="text-center">Tidak ada banner ditemukan.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($banners as $index => $banner): ?>
                <tr>
                    <td><?= $index + 1 ?></td>
                    <td><?= sanitizeOutput($banner['title']) ?></td>
                    <td>
                        <?php if (!empty($banner['image'])): ?>
                            <img src="/uploads/banners/<?= sanitizeOutput($banner['image']) ?>" alt="<?= sanitizeOutput($banner['title']) ?>" class="table-thumbnail" style="max-width: 100px; max-height: 60px; object-fit: cover;">
                        <?php else: ?>
                            <span class="text-muted">Tidak ada gambar</span>
                        <?php endif; ?>
                    </td>
                    <td><?= (int) $banner['sort_order'] ?></td>
                    <td><?= $banner['is_active'] ? 'Ya' : 'Tidak' ?></td>
                    <td class="action-links">
                        <a href="banner-edit?id=<?= (int) $banner['id'] ?>" class="btn btn-sm btn-secondary">Edit</a>
                        <form method="POST" action="banners" class="inline-form" onsubmit="return confirm('Yakin ingin menghapus banner ini?');">
                            <input type="hidden" name="csrf_token" value="<?= sanitizeOutput($csrfToken) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $banner['id'] ?>">
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
