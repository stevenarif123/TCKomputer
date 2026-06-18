<?php
/**
 * Admin FAQ List
 * Displays all FAQ entries with category name, question (truncated), sort order, active status.
 * Action links: Add, Edit, Delete.
 * Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 13.1, 13.2, 13.4
 */

$pageTitle = "Kelola FAQ";
require_once __DIR__ . '/../includes/admin-header.php';

// Fetch all FAQ entries with category name via LEFT JOIN, ordered by category sort_order then FAQ sort_order
// Note: $pdo is initialized in includes/admin-header.php
$query = "SELECT f.*, fc.name AS category_name 
          FROM faqs f 
          LEFT JOIN faq_categories fc ON f.faq_category_id = fc.id 
          ORDER BY fc.sort_order ASC, f.sort_order ASC";
$stmt = $pdo->query($query);
$faqs = $stmt->fetchAll();
?>

<div class="admin-page-header">
    <h2>Kelola FAQ</h2>
    <div class="header-actions">
        <a href="faq-categories" class="btn btn-secondary">Kelola Kategori FAQ</a>
        <a href="faq-add" class="btn btn-primary">+ Tambah FAQ</a>
    </div>
</div>

<!-- FAQ Table -->
<div class="table-responsive">
    <table class="admin-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Pertanyaan</th>
                <th>Kategori</th>
                <th>Urutan</th>
                <th>Aktif</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($faqs)): ?>
                <tr>
                    <td colspan="6" class="text-center">Tidak ada FAQ ditemukan.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($faqs as $index => $faq): ?>
                <tr>
                    <td><?= $index + 1 ?></td>
                    <td><?= sanitizeOutput(truncateText($faq['question'], 80)) ?></td>
                    <td><?= $faq['category_name'] ? sanitizeOutput($faq['category_name']) : '<span class="text-muted">Tidak ada kategori</span>' ?></td>
                    <td><?= (int) $faq['sort_order'] ?></td>
                    <td><?= $faq['is_active'] ? 'Ya' : 'Tidak' ?></td>
                    <td class="action-links">
                        <a href="faq-edit?id=<?= (int) $faq['id'] ?>" class="btn btn-sm btn-secondary">Edit</a>
                        <form method="POST" action="faq-delete" class="inline-form" onsubmit="return confirm('Yakin ingin menghapus FAQ ini?');">
                            <input type="hidden" name="csrf_token" value="<?= sanitizeOutput($csrfToken) ?>">
                            <input type="hidden" name="faq_id" value="<?= (int) $faq['id'] ?>">
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
