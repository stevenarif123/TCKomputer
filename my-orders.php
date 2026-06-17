<?php
/**
 * My Orders Page
 * Displays a list of all orders for the current customer (logged-in or session-based).
 */

require_once __DIR__ . '/includes/header.php';

$profile = $_SESSION['customer_profile'] ?? null;
$sessionOrders = $_SESSION['my_orders'] ?? [];

$orders = [];
if ($profile) {
    // Logged in user: fetch all orders by phone number
    try {
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE buyer_phone = ? ORDER BY created_at DESC");
        $stmt->execute([$profile['phone']]);
        $orders = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Error fetching user orders: ' . $e->getMessage());
    }
} elseif (!empty($sessionOrders)) {
    // Guest user: fetch only orders in current session
    try {
        $inClause = implode(',', array_fill(0, count($sessionOrders), '?'));
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_code IN ($inClause) ORDER BY created_at DESC");
        $stmt->execute($sessionOrders);
        $orders = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Error fetching session orders: ' . $e->getMessage());
    }
}

// Fetch the first item details for each order to show in the UI list
if (!empty($orders)) {
    foreach ($orders as &$order) {
        try {
            $stmtItems = $pdo->prepare(
                "SELECT oi.product_name, oi.quantity, p.image, p.slug
                 FROM order_items oi
                 LEFT JOIN products p ON oi.product_id = p.id
                 WHERE oi.order_id = ?
                 ORDER BY oi.id ASC"
            );
            $stmtItems->execute([$order['id']]);
            $items = $stmtItems->fetchAll();
            $order['items'] = $items;
            $order['total_items'] = count($items);
        } catch (PDOException $e) {
            error_log('Error fetching order items: ' . $e->getMessage());
            $order['items'] = [];
            $order['total_items'] = 0;
        }
    }
    unset($order);
}

/**
 * Translate order status to badge styling classes.
 */
function getOrderStatusBadgeClass(string $status): string
{
    $classes = [
        'menunggu_konfirmasi' => 'bg-amber-50 text-amber-800 border-amber-200/50',
        'diproses' => 'bg-blue-50 text-blue-800 border-blue-200/50',
        'siap_diantar' => 'bg-indigo-50 text-indigo-800 border-indigo-200/50',
        'dikirim' => 'bg-purple-50 text-purple-800 border-purple-200/50',
        'selesai' => 'bg-emerald-50 text-emerald-800 border-emerald-200/50',
        'dibatalkan' => 'bg-rose-50 text-rose-800 border-rose-200/50',
    ];
    return $classes[$status] ?? 'bg-slate-50 text-slate-800 border-slate-200/50';
}

/**
 * Translate payment status to badge styling classes.
 */
function getPaymentStatusBadgeClass(string $status): string
{
    $classes = [
        'belum_dibayar' => 'bg-rose-50 text-rose-800 border-rose-200/50',
        'menunggu_konfirmasi' => 'bg-amber-50 text-amber-800 border-amber-200/50',
        'sudah_dibayar' => 'bg-emerald-50 text-emerald-800 border-emerald-200/50',
        'cod' => 'bg-orange-50 text-orange-800 border-orange-200/50',
    ];
    return $classes[$status] ?? 'bg-slate-50 text-slate-800 border-slate-200/50';
}

/**
 * Get readable order status label.
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
 * Get readable payment status label.
 */
function getPaymentStatusLabel(string $status): string
{
    $labels = [
        'belum_dibayar' => 'Belum Dibayar',
        'menunggu_konfirmasi' => 'Menunggu Konfirmasi',
        'sudah_dibayar' => 'Lunas',
        'cod' => 'COD (Bayar di Tempat)',
    ];
    return $labels[$status] ?? $status;
}
?>

<div class="max-w-max-width mx-auto px-4 md:px-margin-desktop py-3 md:py-lg space-y-3 md:space-y-lg animate-fade-in-up flex-grow">
    <!-- Breadcrumbs -->
    <nav class="flex items-center gap-xs text-body-sm text-on-surface-variant mb-4">
        <a class="hover:text-secondary transition-colors" href="index">Beranda</a>
        <span class="material-symbols-outlined text-[16px] text-outline-variant">chevron_right</span>
        <span class="text-on-surface font-semibold">Pesanan Saya</span>
    </nav>

    <!-- Header Section -->
    <section class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 <?= empty($orders) ? 'border-b border-outline-variant/30 pb-6' : 'pb-2' ?>">
        <div>
            <h1 class="text-headline-lg font-black text-on-background tracking-tight">Pesanan Saya</h1>
            <p class="text-body-sm text-on-surface-variant mt-1">Daftar transaksi dan riwayat belanja Anda di <?= sanitizeOutput($storeName) ?>.</p>
        </div>
        <?php if (!$profile): ?>
            <div class="bg-amber-50 border border-amber-200/50 text-amber-950 p-4 rounded-lg text-xs leading-relaxed max-w-sm">
                <span class="font-bold block mb-1">💡 Tips Belanja</span>
                Daftar akun atau masuk untuk menyimpan riwayat pesanan Anda secara permanen.
            </div>
        <?php endif; ?>
    </section>

    <?php if (!empty($orders)): ?>
    <!-- Shopee-style Tab Navigation -->
    <div class="flex overflow-x-auto gap-2 border-b border-outline-variant/30 mb-6 scrollbar-hide -mx-4 px-4 md:mx-0 md:px-0" id="order-tabs">
        <button class="order-tab active px-4 py-3 font-bold text-sm text-secondary border-b-2 border-secondary whitespace-nowrap transition-colors" data-tab="semua">Semua</button>
        <button class="order-tab px-4 py-3 font-semibold text-sm text-on-surface-variant hover:text-secondary border-b-2 border-transparent whitespace-nowrap transition-colors" data-tab="belum_bayar">Belum Bayar</button>
        <button class="order-tab px-4 py-3 font-semibold text-sm text-on-surface-variant hover:text-secondary border-b-2 border-transparent whitespace-nowrap transition-colors" data-tab="diproses">Dikemas</button>
        <button class="order-tab px-4 py-3 font-semibold text-sm text-on-surface-variant hover:text-secondary border-b-2 border-transparent whitespace-nowrap transition-colors" data-tab="dikirim">Dikirim</button>
        <button class="order-tab px-4 py-3 font-semibold text-sm text-on-surface-variant hover:text-secondary border-b-2 border-transparent whitespace-nowrap transition-colors" data-tab="selesai">Selesai</button>
        <button class="order-tab px-4 py-3 font-semibold text-sm text-on-surface-variant hover:text-secondary border-b-2 border-transparent whitespace-nowrap transition-colors" data-tab="dibatalkan">Dibatalkan</button>
    </div>
    <?php endif; ?>

    <!-- Orders Content -->
    <?php if (empty($orders)): ?>
        <!-- Empty State -->
        <section class="max-w-md mx-auto text-center py-8 md:py-12 space-y-md">
            <div class="w-20 h-20 bg-secondary/5 rounded-full flex items-center justify-center mx-auto border border-secondary/15">
                <span class="material-symbols-outlined text-4xl text-secondary" style="font-variation-settings: 'FILL' 0;">receipt_long</span>
            </div>
            <div>
                <h3 class="text-headline-md font-bold text-on-background">Belum Ada Pesanan</h3>
                <p class="text-body-sm text-on-surface-variant mt-1">Anda belum melakukan transaksi pemesanan produk di toko kami.</p>
            </div>
            <a href="products" class="inline-flex items-center gap-2 bg-secondary hover:bg-secondary-container text-white px-6 py-3 rounded-lg font-bold text-label-md transition-colors">
                <span class="material-symbols-outlined text-sm">shopping_bag</span>
                Mulai Belanja
            </a>
        </section>
    <?php else: ?>
        <!-- Orders Card List -->
        <div class="space-y-sm" id="orders-container">
            <!-- Filtered Empty State -->
            <div id="empty-state-filtered" style="display: none;" class="text-center py-6 md:py-12 space-y-md">
                <div class="w-20 h-20 bg-surface-container rounded-full flex items-center justify-center mx-auto border border-outline-variant/30">
                    <span class="material-symbols-outlined text-4xl text-on-surface-variant" style="font-variation-settings: 'FILL' 0;">inbox</span>
                </div>
                <div>
                    <h3 class="text-body-lg font-bold text-on-background">Tidak ada pesanan</h3>
                    <p class="text-body-sm text-on-surface-variant mt-1">Tidak ada pesanan di kategori ini.</p>
                </div>
            </div>

            <?php foreach ($orders as $order): ?>
                <?php 
                $firstItem = !empty($order['items']) ? $order['items'][0] : null;
                $itemImg = $firstItem && !empty($firstItem['image']) ? 'uploads/products/' . $firstItem['image'] : 'uploads/products/placeholder.png';
                ?>
                <div class="order-card bg-white border border-outline-variant/40 rounded-xl p-4 md:p-5 hover:border-secondary transition-all duration-200 flex flex-col gap-3 md:gap-4"
                     data-payment-status="<?= sanitizeOutput($order['payment_status']) ?>" 
                     data-order-status="<?= sanitizeOutput($order['order_status']) ?>">
                    <!-- Top Info bar -->
                    <div class="flex flex-wrap justify-between items-center gap-2 pb-3 border-b border-outline-variant/30 text-xs">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-on-surface-variant text-md">receipt</span>
                            <span class="font-mono font-bold text-on-surface"><?= sanitizeOutput($order['order_code']) ?></span>
                            <span class="text-outline-variant/60 font-light">|</span>
                            <span class="text-on-surface-variant font-medium"><?= date('d F Y, H:i', strtotime($order['created_at'])) ?> WITA</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <!-- Payment Status Badge -->
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border <?= getPaymentStatusBadgeClass($order['payment_status']) ?>">
                                <?= sanitizeOutput(getPaymentStatusLabel($order['payment_status'])) ?>
                            </span>
                            <!-- Order Status Badge -->
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border <?= getOrderStatusBadgeClass($order['order_status']) ?>">
                                <?= sanitizeOutput(getOrderStatusLabel($order['order_status'])) ?>
                            </span>
                        </div>
                    </div>

                    <!-- Items & Price Details -->
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 py-2">
                        <!-- Product Preview -->
                        <div class="flex items-center gap-md min-w-0">
                            <div class="w-16 h-16 bg-surface-container rounded-lg overflow-hidden flex-shrink-0 flex items-center justify-center border border-outline-variant/30 p-1.5 bg-white">
                                <img class="w-full h-full object-contain" src="<?= $itemImg ?>" alt="<?= sanitizeOutput($firstItem['product_name'] ?? 'Produk') ?>"/>
                            </div>
                            <div class="min-w-0">
                                <?php if ($firstItem): ?>
                                    <h3 class="font-bold text-body-sm text-on-background truncate max-w-sm md:max-w-md">
                                        <?= sanitizeOutput($firstItem['product_name']) ?>
                                    </h3>
                                    <p class="text-xs text-on-surface-variant mt-1">
                                        <?= (int)$firstItem['quantity'] ?> barang
                                        <?php if ($order['total_items'] > 1): ?>
                                            <span class="text-secondary font-medium ml-1">+<?= $order['total_items'] - 1 ?> produk lainnya</span>
                                        <?php endif; ?>
                                    </p>
                                <?php else: ?>
                                    <h3 class="font-bold text-body-sm text-on-surface-variant italic">Pesanan tanpa detail barang</h3>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Price summary & Actions -->
                        <div class="flex flex-col md:items-end justify-between gap-2 border-t md:border-t-0 pt-3 md:pt-0 border-outline-variant/20">
                            <div>
                                <span class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider block md:text-right">Total Belanja</span>
                                <span class="text-base font-black text-secondary"><?= formatRupiah((int)$order['total']) ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Card Actions Footer -->
                    <div class="flex justify-end items-center gap-2 pt-3 border-t border-outline-variant/20">
                        <?php 
                        // Show Invoice only if it is not cancelled
                        if ($order['order_status'] !== 'dibatalkan'): 
                        ?>
                            <a href="print-invoice?code=<?= urlencode($order['order_code']) ?>&phone=<?= urlencode($order['buyer_phone']) ?>" target="_blank" class="px-4 py-2 border border-secondary text-secondary hover:bg-secondary-fixed/10 font-bold text-xs rounded-lg transition-colors flex items-center gap-1">
                                <span class="material-symbols-outlined text-[16px]">print</span>
                                Invoice
                            </a>
                        <?php endif; ?>
                        <a href="track-order?order_code=<?= urlencode($order['order_code']) ?>&buyer_phone=<?= urlencode($order['buyer_phone']) ?>" class="px-4 py-2 bg-secondary hover:bg-secondary-container text-white font-bold text-xs rounded-lg transition-colors flex items-center gap-1 shadow-sm">
                            <span class="material-symbols-outlined text-[16px]">local_shipping</span>
                            Lacak Status
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('.order-tab');
    const cards = document.querySelectorAll('.order-card');
    const emptyState = document.getElementById('empty-state-filtered');

    if (!tabs.length) return;

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            // Remove active styles from all tabs
            tabs.forEach(t => {
                t.classList.remove('text-secondary', 'border-secondary', 'font-bold');
                t.classList.add('text-on-surface-variant', 'border-transparent', 'font-semibold');
            });
            // Add active style to clicked tab
            tab.classList.remove('text-on-surface-variant', 'border-transparent', 'font-semibold');
            tab.classList.add('text-secondary', 'border-secondary', 'font-bold');

            const filter = tab.getAttribute('data-tab');
            let visibleCount = 0;

            cards.forEach(card => {
                const payStatus = card.getAttribute('data-payment-status');
                const orderStatus = card.getAttribute('data-order-status');
                
                let show = false;
                if (filter === 'semua') {
                    show = true;
                } else if (filter === 'belum_bayar') {
                    show = (payStatus === 'belum_dibayar' || payStatus === 'menunggu_konfirmasi') && orderStatus !== 'dibatalkan';
                } else if (filter === 'diproses') {
                    show = (orderStatus === 'menunggu_konfirmasi' || orderStatus === 'diproses' || orderStatus === 'siap_diantar') && payStatus !== 'belum_dibayar' && orderStatus !== 'dibatalkan';
                } else if (filter === 'dikirim') {
                    show = orderStatus === 'dikirim';
                } else if (filter === 'selesai') {
                    show = orderStatus === 'selesai';
                } else if (filter === 'dibatalkan') {
                    show = orderStatus === 'dibatalkan';
                }

                if (show) {
                    card.style.display = 'flex';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            if (emptyState) {
                emptyState.style.display = visibleCount === 0 ? 'block' : 'none';
            }
        });
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
