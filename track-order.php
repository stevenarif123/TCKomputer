<?php
/**
 * Track Order Page
 * Allows buyers to look up their order by order code and phone number.
 */

include 'includes/header.php';

// Generate CSRF token
$csrfToken = generateCSRFToken();

$error = '';
$order = null;
$orderItems = [];

$isLookup = false;
$orderCode = '';
$buyerPhone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($submittedToken)) {
        $error = 'Sesi tidak valid. Silakan coba lagi.';
    } else {
        $orderCode = trim($_POST['order_code'] ?? '');
        $buyerPhone = trim($_POST['buyer_phone'] ?? '');
        $isLookup = true;
    }
} elseif (isset($_GET['order_code']) && isset($_GET['buyer_phone'])) {
    $orderCode = trim($_GET['order_code'] ?? '');
    $buyerPhone = trim($_GET['buyer_phone'] ?? '');
    $isLookup = true;
}

if ($isLookup) {
    // Validate order_code format
    if (!preg_match('/^SIT-\d{8}-\d{4}$/', $orderCode)) {
        $error = 'Format kode pesanan tidak valid. Gunakan format: SIT-YYYYMMDD-XXXX';
    } elseif (empty($buyerPhone)) {
        $error = 'Nomor telepon tidak boleh kosong.';
    } else {
        // Query database for the order
        $stmt = $pdo->prepare(
            "SELECT * FROM orders WHERE order_code = ? AND buyer_phone = ?"
        );
        $stmt->execute([$orderCode, $buyerPhone]);
        $order = $stmt->fetch();

        if ($order) {
            // Fetch order items with product image
            $stmtItems = $pdo->prepare(
                "SELECT oi.product_name, oi.product_price, oi.quantity, oi.subtotal, p.image, p.slug
                 FROM order_items oi
                 LEFT JOIN products p ON oi.product_id = p.id
                 WHERE oi.order_id = ?
                 ORDER BY oi.id ASC"
            );
            $stmtItems->execute([$order['id']]);
            $orderItems = $stmtItems->fetchAll();
        } else {
            $error = 'Pesanan tidak ditemukan. Periksa kembali kode pesanan dan nomor telepon Anda.';
        }
    }
}

// Regenerate CSRF token after post check if post was used
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = generateCSRFToken();
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

// Stepper Calculation logic
$progressWidth = '10%';
if ($order) {
    $payDone = in_array($order['payment_status'], ['sudah_dibayar', 'cod']);
    $status = $order['order_status'];
    
    if ($payDone) {
        $progressWidth = '35%';
    }
    if (in_array($status, ['diproses', 'siap_diantar', 'dikirim', 'selesai'])) {
        $progressWidth = '60%';
    }
    if (in_array($status, ['siap_diantar', 'dikirim', 'selesai'])) {
        $progressWidth = '85%';
    }
    if ($status === 'selesai') {
        $progressWidth = '100%';
    }
}
?>

<style>
    .stepper-line {
        background: repeating-linear-gradient(90deg, #c6c6cd 0, #c6c6cd 4px, transparent 4px, transparent 8px);
    }
    .stepper-line-vertical {
        background: repeating-linear-gradient(180deg, #c6c6cd 0, #c6c6cd 4px, transparent 4px, transparent 8px);
    }
    @media print {
        header, footer, .quick-actions, #chat-drawer, .print-btn, .breadcrumbs, form {
            display: none !important;
        }
        body {
            background: white !important;
            color: black !important;
        }
        main {
            padding: 0 !important;
        }
    }
</style>

<div class="max-w-max-width mx-auto px-4 md:px-margin-desktop py-3 md:py-lg space-y-3 md:space-y-lg animate-fade-in-up">
    <!-- Breadcrumbs -->
    <nav class="flex items-center gap-xs text-body-sm text-on-surface-variant mb-4 breadcrumbs">
        <a class="hover:text-secondary transition-colors" href="index">Beranda</a>
        <span class="material-symbols-outlined text-[16px] text-outline-variant">chevron_right</span>
        <span class="text-on-surface font-semibold">Lacak Pesanan</span>
    </nav>

    <?php if (!$order): ?>
        <!-- Search Order Form Card -->
        <section class="max-w-md mx-auto bg-white p-6 md:p-8 rounded-xl border border-outline-variant/60 space-y-md">
            <div class="text-center">
                <span class="material-symbols-outlined text-5xl text-secondary">query_stats</span>
                <h1 class="text-headline-md font-black text-on-background mt-2">Lacak Pesanan Anda</h1>
                <p class="text-body-sm text-on-surface-variant mt-1">Lihat status perakitan, pembayaran, dan pengiriman barang secara real-time.</p>
            </div>

            <?php if (!empty($error)): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    showToast('Pesanan Tidak Ditemukan', <?= json_encode($error) ?>, 'error');
                });
            </script>
            <?php endif; ?>

            <form method="POST" action="track-order" class="space-y-sm">
                <input type="hidden" name="csrf_token" value="<?= sanitizeOutput($csrfToken) ?>">
                
                <div class="space-y-1">
                    <label for="order_code" class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider block">Kode Pesanan</label>
                    <input type="text" id="order_code" name="order_code" placeholder="SIT-YYYYMMDD-XXXX" maxlength="17" required
                           value="<?= sanitizeOutput($_POST['order_code'] ?? '') ?>"
                           class="w-full px-4 py-3 border border-outline-variant/85 rounded-lg text-body-md bg-surface-container-lowest outline-none"/>
                </div>

                <div class="space-y-1">
                    <label for="buyer_phone" class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider block">Nomor Telepon Pembeli</label>
                    <input type="text" id="buyer_phone" name="buyer_phone" placeholder="Contoh: 081234567890" maxlength="15" required
                           value="<?= sanitizeOutput($_POST['buyer_phone'] ?? '') ?>"
                           class="w-full px-4 py-3 border border-outline-variant/85 rounded-lg text-body-md bg-surface-container-lowest outline-none"/>
                </div>

                <button type="submit" class="w-full bg-secondary hover:bg-secondary-container text-white py-3 rounded-lg font-bold text-label-md transition-colors flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined">search</span>
                    Mulai Lacak
                </button>
            </form>
        </section>
    <?php else: ?>
        <!-- Order Header Status Card -->
        <section class="bg-white p-4 md:p-6 rounded-xl border border-outline-variant/40 flex flex-col md:flex-row justify-between items-start md:items-center gap-md">
            <div>
                <h1 class="text-headline-md font-black text-on-background tracking-tight mb-1">Status Pesanan</h1>
                <div class="flex flex-wrap gap-x-sm gap-y-1 text-body-sm text-on-surface-variant font-medium">
                    <span class="flex items-center gap-xs"><span class="material-symbols-outlined text-[18px]">receipt</span> Order #<?= sanitizeOutput($order['order_code']) ?></span>
                    <span class="text-outline-variant/60 font-light">|</span>
                    <span class="flex items-center gap-xs"><span class="material-symbols-outlined text-[18px]">person</span> <?= sanitizeOutput($order['buyer_name']) ?></span>
                    <span class="text-outline-variant/60 font-light">|</span>
                    <span class="flex items-center gap-xs"><span class="material-symbols-outlined text-[18px]">calendar_today</span> <?= date('d F Y, H:i', strtotime($order['created_at'])) ?> WITA</span>
                </div>
            </div>
            <div class="bg-secondary/10 text-secondary px-4 py-1.5 rounded text-label-md font-black flex items-center gap-xs border border-secondary/20">
                <span class="material-symbols-outlined text-[18px] " style="font-variation-settings: 'FILL' 1;">sync</span>
                <?= sanitizeOutput(getOrderStatusLabel($order['order_status'])) ?>
            </div>
        </section>

        <!-- Dynamic Tracking Stepper -->
        <section class="bg-white p-6 md:p-8 rounded-xl border border-outline-variant/40">
            <div class="relative flex flex-col md:flex-row md:justify-between gap-6 md:gap-0">
                <!-- Background Line (Desktop) -->
                <div class="hidden md:block absolute top-5 left-10 right-10 h-[2.5px] stepper-line"></div>
                <!-- Animated Progress Fill (Desktop) -->
                <div class="hidden md:block absolute top-5 left-10 h-[2.5px] bg-secondary transition-all duration-1000 ease-out" style="width: calc(<?= $progressWidth ?> - 5%);"></div>
                
                <!-- Background Line (Mobile) -->
                <div class="md:hidden absolute left-[19px] top-5 bottom-5 w-[2px] stepper-line-vertical"></div>
                <!-- Animated Progress Fill (Mobile) -->
                <div class="md:hidden absolute left-[19px] top-5 w-[2px] bg-secondary transition-all duration-1000 ease-out" style="height: calc(<?= $progressWidth ?> - 5%);"></div>

                <!-- Step 1: Pesanan Dibuat -->
                <div class="relative z-10 flex flex-row md:flex-col items-center md:items-center gap-4 md:gap-sm">
                    <div class="w-10 h-10 rounded bg-secondary text-white flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-[20px] font-bold" style="font-variation-settings: 'FILL' 1;">check</span>
                    </div>
                    <span class="text-label-md font-bold text-secondary text-left md:text-center w-full md:w-auto">Pesanan Dibuat</span>
                </div>
                
                <!-- Step 2: Pembayaran Berhasil -->
                <?php 
                $step2Active = in_array($order['payment_status'], ['sudah_dibayar', 'cod']);
                ?>
                <div class="relative z-10 flex flex-row md:flex-col items-center md:items-center gap-4 md:gap-sm <?= $step2Active ? '' : 'opacity-50' ?>">
                    <div class="w-10 h-10 rounded <?= $step2Active ? 'bg-secondary text-white shadow-lg' : 'bg-surface-container-high text-on-surface-variant' ?> flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-[20px] font-bold" style="font-variation-settings: 'FILL' 1;">check</span>
                    </div>
                    <span class="text-label-md font-bold text-secondary text-left md:text-center w-full md:w-auto">Pembayaran Berhasil</span>
                </div>
                
                <!-- Step 3: Sedang Diproses -->
                <?php 
                $step3Active = in_array($order['order_status'], ['diproses', 'siap_diantar', 'dikirim', 'selesai']);
                ?>
                <div class="relative z-10 flex flex-row md:flex-col items-center md:items-center gap-4 md:gap-sm <?= $step3Active ? '' : 'opacity-50' ?>">
                    <div class="w-10 h-10 rounded <?= $step3Active ? 'bg-secondary text-white shadow-lg' : 'bg-surface-container-high text-on-surface-variant' ?> flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-[20px]">sync</span>
                    </div>
                    <span class="text-label-md font-black text-secondary text-left md:text-center w-full md:w-auto">Sedang Diproses</span>
                </div>
                
                <!-- Step 4: Dikirim -->
                <?php 
                $step4Active = in_array($order['order_status'], ['siap_diantar', 'dikirim', 'selesai']);
                ?>
                <div class="relative z-10 flex flex-row md:flex-col items-center md:items-center gap-4 md:gap-sm <?= $step4Active ? '' : 'opacity-50' ?>">
                    <div class="w-10 h-10 rounded <?= $step4Active ? 'bg-secondary text-white shadow-lg' : 'bg-surface-container-high text-on-surface-variant' ?> flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-[20px]">local_shipping</span>
                    </div>
                    <span class="text-label-md font-semibold text-on-surface-variant text-left md:text-center w-full md:w-auto">Dikirim</span>
                </div>
                
                <!-- Step 5: Selesai -->
                <?php 
                $step5Active = $order['order_status'] === 'selesai';
                ?>
                <div class="relative z-10 flex flex-row md:flex-col items-center md:items-center gap-4 md:gap-sm <?= $step5Active ? '' : 'opacity-50' ?>">
                    <div class="w-10 h-10 rounded <?= $step5Active ? 'bg-secondary text-white shadow-lg' : 'bg-surface-container-high text-on-surface-variant' ?> flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-[20px]">verified</span>
                    </div>
                    <span class="text-label-md font-semibold text-on-surface-variant text-left md:text-center w-full md:w-auto">Selesai</span>
                </div>
            </div>
        </section>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-gutter">
            <!-- Left Side: Product List -->
            <div class="lg:col-span-2 space-y-md">
                <h2 class="text-headline-md font-extrabold text-on-background px-1">Barang Yang Dibeli</h2>
                
                <div class="space-y-sm">
                    <?php foreach ($orderItems as $item): ?>
                    <?php 
                    $itemImg = !empty($item['image']) ? 'uploads/products/' . $item['image'] : 'uploads/products/placeholder.png';
                    ?>
                    <div class="bg-white p-4 rounded-xl border border-outline-variant/40 flex gap-md group hover:border-secondary transition-colors duration-200">
                        <div class="w-24 h-24 bg-surface-container rounded-lg overflow-hidden flex-shrink-0 flex items-center justify-center border border-outline-variant/30 p-2 bg-white">
                            <img class="w-full h-full object-contain" src="<?= $itemImg ?>" alt="<?= sanitizeOutput($item['product_name']) ?>"/>
                        </div>
                        <div class="flex-grow py-1 flex flex-col justify-between">
                            <div>
                                <?php if (!empty($item['slug'])): ?>
                                    <a href="product-detail?slug=<?= urlencode($item['slug']) ?>" class="font-bold text-body-md text-on-background hover:text-secondary transition-colors">
                                        <?= sanitizeOutput($item['product_name']) ?>
                                    </a>
                                <?php else: ?>
                                    <h3 class="font-bold text-body-md text-on-background"><?= sanitizeOutput($item['product_name']) ?></h3>
                                <?php endif; ?>
                            </div>
                            <div class="flex justify-between items-end mt-2">
                                <span class="text-body-sm font-semibold text-on-surface-variant"><?= (int)$item['quantity'] ?> x Unit</span>
                                <span class="text-body-lg font-black text-secondary"><?= formatRupiah((int)$item['subtotal']) ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Admin Notes -->
                <div class="bg-surface-container-low/60 p-5 rounded-lg border-l-4 border-secondary">
                    <div class="flex items-center gap-xs mb-2">
                        <span class="material-symbols-outlined text-secondary">sticky_note_2</span>
                        <h3 class="text-body-sm font-extrabold text-on-background uppercase tracking-wider">Catatan Pengecekan IT / Admin</h3>
                    </div>
                    <p class="text-body-sm text-on-surface-variant leading-relaxed">
                        <?php if (!empty($order['admin_notes'])): ?>
                            <?= nl2br(sanitizeOutput($order['admin_notes'])) ?>
                        <?php else: ?>
                            Pesanan Anda sedang diproses oleh tim kami. Jika ini produk perakitan, teknisi kami melakukan stress test komponen selama 30 menit sebelum pengemasan.
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            
            <!-- Right Side: Summary & Action Sidebar -->
            <div class="space-y-md">
                <h2 class="text-headline-md font-extrabold text-on-background px-1">Ringkasan</h2>
                
                <div class="bg-white rounded-xl border border-outline-variant/40 overflow-hidden">
                    <div class="bg-primary-container px-4 py-3 border-b border-white/5">
                        <h3 class="text-body-sm font-bold text-white uppercase tracking-wider">Detail Pengiriman</h3>
                    </div>
                    <div class="p-4 space-y-md">
                        <div class="space-y-1">
                            <span class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider block">Opsi Kurir Pengiriman</span>
                            <p class="text-body-sm font-bold flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-[16px] text-secondary">local_shipping</span> 
                                <?= sanitizeOutput(str_replace('_', ' ', $order['shipping_option'])) ?>
                            </p>
                        </div>
                        <div class="space-y-1">
                            <span class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider block">Alamat Pengiriman</span>
                            <p class="text-body-sm leading-relaxed text-on-surface-variant font-medium">
                                <span class="font-extrabold text-on-background"><?= sanitizeOutput($order['buyer_name']) ?></span><br/>
                                <?= nl2br(sanitizeOutput($order['buyer_address'])) ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="bg-primary-container px-4 py-3 border-t border-b border-white/5">
                        <h3 class="text-body-sm font-bold text-white uppercase tracking-wider">Detail Pembayaran</h3>
                    </div>
                    <div class="p-4 space-y-xs">
                        <div class="flex justify-between text-body-sm font-semibold">
                            <span class="text-on-surface-variant">Subtotal Produk</span>
                            <span class="text-on-background"><?= formatRupiah((int)$order['subtotal']) ?></span>
                        </div>
                        <div class="flex justify-between text-body-sm font-semibold">
                            <span class="text-on-surface-variant">Biaya Pengiriman</span>
                            <span class="text-on-background"><?= formatRupiah((int)$order['shipping_cost']) ?></span>
                        </div>
                        <div class="flex justify-between text-body-sm font-semibold">
                            <span class="text-on-surface-variant">Biaya Layanan</span>
                            <span class="text-on-background">Rp 1.000</span>
                        </div>
                        <div class="pt-2 mt-2 border-t border-outline-variant/40 flex justify-between items-center">
                            <span class="text-body-sm font-bold text-primary">Total Pembayaran</span>
                            <span class="text-body-lg font-black text-secondary"><?= formatRupiah((int)$order['total']) ?></span>
                        </div>
                        <div class="mt-4 pt-3 border-t border-dashed border-outline-variant/80 flex items-center justify-between">
                            <div class="flex items-center gap-1">
                                <span class="material-symbols-outlined text-secondary text-[20px]">account_balance</span>
                                <span class="text-body-sm font-semibold"><?= sanitizeOutput(strtoupper($order['payment_method'])) ?></span>
                            </div>
                            <?php if ($order['payment_status'] === 'sudah_dibayar'): ?>
                                <span class="text-on-tertiary-container text-[10px] font-black px-2.5 py-0.5 bg-on-tertiary-container/10 border border-on-tertiary-container/20 rounded">LUNAS</span>
                            <?php else: ?>
                                <span class="text-error text-[10px] font-black px-2.5 py-0.5 bg-error/10 border border-error/20 rounded"><?= sanitizeOutput(getPaymentStatusLabel($order['payment_status'])) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="flex flex-col gap-sm quick-actions">
                    <a href="print-invoice?code=<?= urlencode($order['order_code']) ?>&phone=<?= urlencode($order['buyer_phone']) ?>" target="_blank" class="print-btn w-full bg-secondary hover:bg-secondary-container text-white py-3 rounded-lg font-bold text-label-md flex items-center justify-center gap-xs transition-colors text-center">
                        <span class="material-symbols-outlined text-[20px]">print</span>
                        Cetak Invoice
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
