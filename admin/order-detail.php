<?php
/**
 * Admin Order Detail
 * Displays full order information with status and notes update forms.
 */

$pageTitle = "Detail Pesanan";
include __DIR__ . '/../includes/admin-header.php';

// Get order ID from URL
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    redirect('orders', 'Pesanan tidak ditemukan.', 'error');
}

// Fetch order
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order) {
    redirect('orders', 'Pesanan tidak ditemukan.', 'error');
}

// Fetch order items
$stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
$stmt->execute([$id]);
$orderItems = $stmt->fetchAll();

// Fetch shipping area name and regency
$shippingAreaName = '-';
if (!empty($order['shipping_area_id'])) {
    $stmt = $pdo->prepare("SELECT area_name, regency FROM shipping_areas WHERE id = ?");
    $stmt->execute([$order['shipping_area_id']]);
    $area = $stmt->fetch();
    if ($area) {
        $shippingAreaName = $area['area_name'] . ' (' . $area['regency'] . ')';
    }
}

/**
 * Translate order status to display label.
 */
function getOrderStatusLabel(string $status): string
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
function getPaymentStatusLabel(string $status): string
{
    $labels = [
        'belum_dibayar' => 'Belum Dibayar',
        'menunggu_konfirmasi' => 'Menunggu Konfirmasi',
        'sudah_dibayar' => 'Sudah Dibayar',
        'cod' => 'COD (Bayar di Tempat)',
    ];
    return $labels[$status] ?? $status;
}

/**
 * Translate shipping option to display label.
 */
function getShippingOptionLabel(string $option): string
{
    $labels = [
        'self_pickup' => 'Ambil Sendiri di Kantor',
        'local_delivery' => 'Antar Lokal (Non-Aktif)',
        'local_courier' => 'Diantar Kurir Kantor',
    ];
    return $labels[$option] ?? $option;
}

/**
 * Translate payment method to display label.
 */
function getPaymentMethodLabel(string $method): string
{
    $labels = [
        'cod' => 'COD (Bayar di Tempat)',
        'transfer' => 'Transfer Bank',
        'pay_on_delivery' => 'Bayar Saat Diterima',
    ];
    return $labels[$method] ?? $method;
}
?>

<div class="admin-order-detail">
    <div class="detail-header">
        <h2>Pesanan: <?= sanitizeOutput($order['order_code']) ?></h2>
        <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
            <a href="../print-invoice?id=<?= (int)$order['id'] ?>" target="_blank" class="btn btn-primary">
                <span class="material-symbols-outlined">print</span> Cetak Invoice
            </a>
            <form action="order-delete" method="POST" style="margin:0;" onsubmit="return confirm('PERINGATAN DEMO: Anda yakin ingin menghapus pesanan ini secara permanen dari database? Aksi ini tidak dapat dibatalkan.');">
                <input type="hidden" name="csrf_token" value="<?= sanitizeOutput($_SESSION['csrf_token'] ?? '') ?>">
                <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                <button type="submit" class="btn" style="background-color: #ef4444; color: white; border: none; display: flex; align-items: center; gap: 4px;">
                    <span class="material-symbols-outlined">delete</span> Hapus Pesanan
                </button>
            </form>
            <a href="orders" class="btn btn-secondary">&laquo; Kembali</a>
        </div>
    </div>

    <!-- Order Information -->
    <div class="detail-section">
        <h3>Informasi Pesanan</h3>
        <table class="detail-table">
            <tr>
                <th>Kode Pesanan</th>
                <td><?= sanitizeOutput($order['order_code']) ?></td>
            </tr>
            <tr>
                <th>Status Pesanan</th>
                <td>
                    <span class="badge badge-order-<?= sanitizeOutput($order['order_status']) ?>">
                        <?= sanitizeOutput(getOrderStatusLabel($order['order_status'])) ?>
                    </span>
                </td>
            </tr>
            <tr>
                <th>Status Pembayaran</th>
                <td>
                    <span class="badge badge-payment-<?= sanitizeOutput($order['payment_status']) ?>">
                        <?= sanitizeOutput(getPaymentStatusLabel($order['payment_status'])) ?>
                    </span>
                </td>
            </tr>
            <tr>
                <th>Tanggal Pesanan</th>
                <td><?= sanitizeOutput(date('d/m/Y H:i', strtotime($order['created_at']))) ?></td>
            </tr>
            <tr>
                <th>Terakhir Diupdate</th>
                <td><?= sanitizeOutput(date('d/m/Y H:i', strtotime($order['updated_at']))) ?></td>
            </tr>
        </table>
    </div>

    <!-- Buyer Information -->
    <div class="detail-section">
        <h3>Informasi Pembeli</h3>
        <table class="detail-table">
            <tr>
                <th>Nama</th>
                <td><?= sanitizeOutput($order['buyer_name']) ?></td>
            </tr>
            <tr>
                <th>No. Telepon</th>
                <td><?= sanitizeOutput($order['buyer_phone']) ?></td>
            </tr>
            <tr>
                <th>Alamat</th>
                <td><?= sanitizeOutput($order['buyer_address']) ?></td>
            </tr>
        </table>
    </div>

    <!-- Shipping & Payment -->
    <div class="detail-section">
        <h3>Pengiriman & Pembayaran</h3>
        <table class="detail-table">
            <tr>
                <th>Area Pengiriman</th>
                <td><?= sanitizeOutput($shippingAreaName) ?></td>
            </tr>
            <tr>
                <th>Opsi Pengiriman</th>
                <td><?= sanitizeOutput(getShippingOptionLabel($order['shipping_option'])) ?></td>
            </tr>
            <tr>
                <th>Metode Pembayaran</th>
                <td><?= sanitizeOutput(getPaymentMethodLabel($order['payment_method'])) ?></td>
            </tr>
        </table>
    </div>

    <!-- Order Items -->
    <div class="detail-section">
        <h3>Item Pesanan</h3>
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Produk</th>
                        <th>Harga</th>
                        <th>Jumlah</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orderItems as $item): ?>
                        <tr>
                            <td><?= sanitizeOutput($item['product_name']) ?></td>
                            <td><?= formatRupiah((int)$item['product_price']) ?></td>
                            <td><?= (int)$item['quantity'] ?></td>
                            <td><?= formatRupiah((int)$item['subtotal']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="text-right"><strong>Subtotal</strong></td>
                        <td><strong><?= formatRupiah((int)$order['subtotal']) ?></strong></td>
                    </tr>
                    <tr>
                        <td colspan="3" class="text-right"><strong>Ongkos Kirim</strong></td>
                        <td><strong><?= formatRupiah((int)$order['shipping_cost']) ?></strong></td>
                    </tr>
                    <?php if (!empty($order['discount_amount']) && $order['discount_amount'] > 0): ?>
                    <tr>
                        <td colspan="3" class="text-right"><strong>Diskon / Promosi</strong></td>
                        <td><strong style="color: #10b981;">-<?= formatRupiah((int)$order['discount_amount']) ?></strong></td>
                    </tr>
                    <?php endif; ?>
                    <?php 
                    $discountAmount = (int)($order['discount_amount'] ?? 0);
                    $serviceFee = (int)$order['total'] - ((int)$order['subtotal'] + (int)$order['shipping_cost'] - $discountAmount);
                    if ($serviceFee > 0): 
                    ?>
                    <tr>
                        <td colspan="3" class="text-right"><strong>Biaya Layanan</strong></td>
                        <td><strong><?= formatRupiah($serviceFee) ?></strong></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td colspan="3" class="text-right"><strong>Total</strong></td>
                        <td><strong><?= formatRupiah((int)$order['total']) ?></strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Notes -->
    <div class="detail-section">
        <h3>Catatan</h3>
        <table class="detail-table">
            <tr>
                <th>Catatan Pembeli</th>
                <td><?= !empty($order['order_notes']) ? sanitizeOutput($order['order_notes']) : '<em>Tidak ada catatan</em>' ?></td>
            </tr>
            <tr>
                <th>Catatan Admin</th>
                <td><?= !empty($order['admin_notes']) ? sanitizeOutput($order['admin_notes']) : '<em>Tidak ada catatan</em>' ?></td>
            </tr>
        </table>
    </div>

    <!-- Status Update Form -->
    <div class="detail-section">
        <h3>Update Status Pesanan</h3>
        <form action="order-update-status" method="POST" class="admin-form">
            <input type="hidden" name="csrf_token" value="<?= sanitizeOutput($csrfToken) ?>">
            <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">

            <div class="form-group">
                <label for="order_status">Status Pesanan</label>
                <select name="order_status" id="order_status" class="form-control">
                    <option value="menunggu_konfirmasi" <?= $order['order_status'] === 'menunggu_konfirmasi' ? 'selected' : '' ?>>Menunggu Konfirmasi</option>
                    <option value="diproses" <?= $order['order_status'] === 'diproses' ? 'selected' : '' ?>>Diproses</option>
                    <option value="siap_diantar" <?= $order['order_status'] === 'siap_diantar' ? 'selected' : '' ?>>Siap Diantar</option>
                    <option value="dikirim" <?= $order['order_status'] === 'dikirim' ? 'selected' : '' ?>>Dikirim</option>
                    <option value="selesai" <?= $order['order_status'] === 'selesai' ? 'selected' : '' ?>>Selesai</option>
                    <option value="dibatalkan" <?= $order['order_status'] === 'dibatalkan' ? 'selected' : '' ?>>Dibatalkan</option>
                </select>
            </div>

            <div class="form-group">
                <label for="payment_status">Status Pembayaran</label>
                <select name="payment_status" id="payment_status" class="form-control">
                    <option value="belum_dibayar" <?= $order['payment_status'] === 'belum_dibayar' ? 'selected' : '' ?>>Belum Dibayar</option>
                    <option value="menunggu_konfirmasi" <?= $order['payment_status'] === 'menunggu_konfirmasi' ? 'selected' : '' ?>>Menunggu Konfirmasi</option>
                    <option value="sudah_dibayar" <?= $order['payment_status'] === 'sudah_dibayar' ? 'selected' : '' ?>>Sudah Dibayar</option>
                    <option value="cod" <?= $order['payment_status'] === 'cod' ? 'selected' : '' ?>>COD (Bayar di Tempat)</option>
                </select>
            </div>

            <div class="form-group">
                <label for="admin_notes">Catatan Admin</label>
                <textarea name="admin_notes" id="admin_notes" class="form-control" rows="4"><?= sanitizeOutput($order['admin_notes'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary">Update Pesanan</button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
