<?php
/**
 * Admin FAQ Categories List
 * Displays all FAQ categories ordered by sort_order ascending.
 * Action links: Add, Edit, Delete.
 * Requirements: 8.1, 8.2, 8.3
 */

$pageTitle = "Kelola Kategori FAQ";
require_once __DIR__ . '/../includes/admin-header.php';

// Fetch all FAQ categories ordered by sort_order ASC
$stmt = $pdo->query("SELECT * FROM faq_categories ORDER BY sort_order ASC");
$categories = $stmt->fetchAll();
?>

<div class="admin-page-header">
    <h2>Kelola Kategori FAQ</h2>
    <a href="faq-category-add" class="btn btn-primary">+ Tambah Kategori</a>
</div>

<!-- FAQ Categories Table -->
<div class="table-responsive">
    <table class="admin-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Nama</th>
                <th>Deskripsi</th>
                <th>Icon</th>
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
                    <td><?= sanitizeOutput($category['description'] ?? '') ?></td>
                    <td>
                        <?php if (!empty($category['icon'])): ?>
                            <span class="material-symbols-outlined"><?= sanitizeOutput($category['icon']) ?></span>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td><?= (int) $category['sort_order'] ?></td>
                    <td><?= $category['is_active'] ? 'Ya' : 'Tidak' ?></td>
                    <td class="action-links">
                        <a href="faq-category-edit?id=<?= (int) $category['id'] ?>" class="btn btn-sm btn-secondary">Edit</a>
                        <form method="POST" action="faq-category-delete" class="inline-form" onsubmit="return confirm('Yakin ingin menghapus kategori ini?');">
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
