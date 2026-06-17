<?php
/**
 * Admin Categories List
 * Displays all categories with name, slug, product count, active status, sort order.
 * Action links: Add, Edit, Delete.
 */

$pageTitle = "Kelola Kategori";
require_once __DIR__ . '/../includes/admin-header.php';

// Query categories with product count, ordered by sort_order ascending
$stmt = $pdo->query(
    "SELECT c.*, (SELECT COUNT(*) FROM products WHERE category_id = c.id) AS product_count 
     FROM categories c 
     ORDER BY c.sort_order ASC"
);
$categories = $stmt->fetchAll();
?>

<div class="admin-page-header">
    <h2>Kelola Kategori</h2>
    <a href="category-add" class="btn btn-primary">+ Tambah Kategori</a>
</div>

<!-- Categories Table -->
<div class="table-responsive">
    <table class="admin-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Nama</th>
                <th>Slug</th>
                <th>Jumlah Produk</th>
                <th>Urutan</th>
                <th>Aktif</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($categories)): ?>
                <tr>
                    <td colspan="7" class="text-center">Tidak ada kategori ditemukan.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($categories as $index => $category): ?>
                <tr>
                    <td><?= $index + 1 ?></td>
                    <td><?= sanitizeOutput($category['name']) ?></td>
                    <td><?= sanitizeOutput($category['slug']) ?></td>
                    <td><?= (int) $category['product_count'] ?></td>
                    <td><?= (int) $category['sort_order'] ?></td>
                    <td><?= $category['is_active'] ? 'Ya' : 'Tidak' ?></td>
                    <td class="action-links">
                        <a href="category-edit?id=<?= (int) $category['id'] ?>" class="btn btn-sm btn-secondary">Edit</a>
                        <form method="POST" action="category-delete" class="inline-form" onsubmit="return confirm('Yakin ingin menghapus kategori ini?');">
                            <input type="hidden" name="csrf_token" value="<?= sanitizeOutput($csrfToken) ?>">
                            <input type="hidden" name="category_id" value="<?= (int) $category['id'] ?>">
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
