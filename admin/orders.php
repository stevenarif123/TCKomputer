<?php
/**
 * Admin Orders List
 * Displays paginated list of all orders sorted by creation date descending.
 */

$pageTitle = "Kelola Pesanan";
include __DIR__ . '/../includes/admin-header.php';

// Current page for pagination
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;

// Query orders sorted by creation date descending
$query = "SELECT * FROM orders ORDER BY created_at DESC";
$result = paginate($pdo, $query, [], 15, $currentPage);

$orders = $result['data'];
$totalPages = $result['pages'];
$currentPage = $result['current_page'];

/**
 * Translate order status to display label.
 */
function getAdminOrderStatusLabel(string $status): string
{
    $labels = [
        'menunggu_konfirmasi' => 'Menunggu Konfirmasi',
        'diproses' => 'Diproses',
        'siap_diantar' => 'Siap Diantar',
        'dikirim' => 'Dikirim',
        'selesai' => 'Selesai',
        'dibatalkan' => 'Dibatalkan',
    ];
    return $labels[$status] ?? $status;
}

/**
 * Translate payment status to display label.
 */
function getAdminPaymentStatusLabel(string $status): string
{
    $labels = [
        'belum_dibayar' => 'Belum Dibayar',
        'menunggu_konfirmasi' => 'Menunggu Konfirmasi',
        'sudah_dibayar' => 'Sudah Dibayar',
        'cod' => 'COD (Bayar di Tempat)',
    ];
    return $labels[$status] ?? $status;
}
?>

<div class="admin-orders">
    <div class="admin-page-header">
        <h2>Daftar Pesanan</h2>
        <a href="export-orders" class="btn btn-outline">
            <span class="material-symbols-outlined">download</span> Ekspor ke CSV
        </a>
    </div>

    <?php if (empty($orders)): ?>
        <p class="empty-message">Belum ada pesanan.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Kode Pesanan</th>
                        <th>Nama Pembeli</th>
                        <th>Total</th>
                        <th>Status Pembayaran</th>
                        <th>Status Pesanan</th>
                        <th>Tanggal</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><?= sanitizeOutput($order['order_code']) ?></td>
                            <td><?= sanitizeOutput($order['buyer_name']) ?></td>
                            <td><?= formatRupiah((int)$order['total']) ?></td>
                            <td>
                                <span class="badge badge-payment-<?= sanitizeOutput($order['payment_status']) ?>">
                                    <?= sanitizeOutput(getAdminPaymentStatusLabel($order['payment_status'])) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-order-<?= sanitizeOutput($order['order_status']) ?>">
                                    <?= sanitizeOutput(getAdminOrderStatusLabel($order['order_status'])) ?>
                                </span>
                            </td>
                            <td><?= sanitizeOutput(date('d/m/Y H:i', strtotime($order['created_at']))) ?></td>
                            <td>
                                <a href="order-detail?id=<?= (int)$order['id'] ?>" class="btn btn-sm btn-info">Detail</a>
                                <a href="../print-invoice?id=<?= (int)$order['id'] ?>" target="_blank" class="btn btn-sm btn-secondary" style="background-color: #4b5563; color: white;">Cetak</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($currentPage > 1): ?>
                    <a href="?page=<?= $currentPage - 1 ?>" class="pagination-link">&laquo; Sebelumnya</a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <?php if ($i === $currentPage): ?>
                        <span class="pagination-link active"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?page=<?= $i ?>" class="pagination-link"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($currentPage < $totalPages): ?>
                    <a href="?page=<?= $currentPage + 1 ?>" class="pagination-link">Selanjutnya &raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
