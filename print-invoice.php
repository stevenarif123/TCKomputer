<?php
/**
 * Print Invoice Page
 * Renders a clean, print-friendly invoice (Nota Pesanan) for buyers and admin.
 * Automatically triggers window.print() on load.
 */

// Security hardening & output buffering for comment stripping
require_once __DIR__ . '/config/security.php';
configureSecureSession();
applySecurityHeaders();

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/config/admin-auth.php';

$pdo = getDBConnection();
$order = null;

// Determine access mode
if (isset($_GET['id']) && isAdminLoggedIn()) {
    // Admin access
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare(
        "SELECT o.*, sa.area_name, sa.regency 
         FROM orders o 
         LEFT JOIN shipping_areas sa ON o.shipping_area_id = sa.id 
         WHERE o.id = ?"
    );
    $stmt->execute([$id]);
    $order = $stmt->fetch();
} elseif (isset($_GET['code']) && isset($_GET['phone'])) {
    // Buyer access
    $code = trim($_GET['code']);
    $phone = trim($_GET['phone']);
    $stmt = $pdo->prepare(
        "SELECT o.*, sa.area_name, sa.regency 
         FROM orders o 
         LEFT JOIN shipping_areas sa ON o.shipping_area_id = sa.id 
         WHERE o.order_code = ? AND o.buyer_phone = ?"
    );
    $stmt->execute([$code, $phone]);
    $order = $stmt->fetch();
}

if (!$order) {
    http_response_code(404);
    echo "<div style='font-family:sans-serif; text-align:center; padding:50px;'>";
    echo "<h2>Pesanan tidak ditemukan atau Anda tidak memiliki akses ke halaman ini.</h2>";
    echo "<p>Pastikan Anda login sebagai admin atau memasukkan kode pesanan dan nomor telepon yang benar.</p>";
    echo "<a href='index.php' style='color:#0058be; text-decoration:none; font-weight:bold;'>Kembali ke Beranda</a>";
    echo "</div>";
    exit;
}

// Fetch order items
$stmtItems = $pdo->prepare(
    "SELECT oi.*, p.brand, p.model 
     FROM order_items oi 
     LEFT JOIN products p ON oi.product_id = p.id 
     WHERE oi.order_id = ? 
     ORDER BY oi.id ASC"
);
$stmtItems->execute([$order['id']]);
$orderItems = $stmtItems->fetchAll();

// Fetch store settings
$stmtSettings = $pdo->query("SELECT store_name, logo, phone, email, address, bank_account FROM store_settings LIMIT 1");
$storeSettings = $stmtSettings->fetch();
$storeName = preg_replace('/^PT\.?\s+/i', '', $storeSettings['store_name'] ?? 'TC Komputer');
$storeLogo = $storeSettings['logo'] ?? null;
$storePhone = $storeSettings['phone'] ?? '082293924242';
$storeEmail = $storeSettings['email'] ?? 'info@tckomputer.com';
$storeAddress = $storeSettings['address'] ?? 'Jl. Teknologi No. 88, Kota Komputerindo, Jawa Timur 60123';

// Payment formatting
$paymentMethodLabel = $order['payment_method'] === 'transfer' ? 'Transfer Bank' : 'COD (Bayar di Tempat)';
$shippingOptionLabel = ucwords(str_replace('_', ' ', $order['shipping_option']));

// Payment timestamp logic
$isPaid = $order['payment_status'] === 'sudah_dibayar';
$paymentTime = null;
if ($isPaid) {
    $paymentTime = date('d/m/y H:i', strtotime($order['updated_at']));
}

// Math verification
$subtotal = (int)$order['subtotal'];
$shippingCost = (int)$order['shipping_cost'];
$total = (int)$order['total'];
$discountAmount = (int)($order['discount_amount'] ?? 0);
$serviceFee = $total - ($subtotal + $shippingCost - $discountAmount);
if ($serviceFee < 0) {
    $serviceFee = 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nota Pesanan - SIT-<?= htmlspecialchars($order['order_code']) ?></title>
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #1a1a1a;
            background-color: #ffffff;
            margin: 0;
            padding: 20px;
            font-size: 12px;
            line-height: 1.4;
        }

        .invoice-box {
            max-width: 800px;
            margin: auto;
            padding: 10px;
        }

        .no-print-bar {
            background-color: #f3f4f6;
            border: 1px solid #e5e7eb;
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .btn {
            background-color: #0058be;
            color: #ffffff;
            border: none;
            padding: 8px 16px;
            font-size: 12px;
            font-weight: 700;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: background 0.15s ease;
        }

        .btn:hover {
            background-color: #00479b;
        }

        .btn-secondary {
            background-color: #4b5563;
        }

        .btn-secondary:hover {
            background-color: #374151;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .logo-container {
            vertical-align: middle;
            text-align: left;
        }

        .logo-img {
            max-height: 48px;
            width: auto;
            object-fit: contain;
        }

        .logo-text {
            font-size: 22px;
            font-weight: 900;
            letter-spacing: -0.7px;
            margin: 0;
        }
        
        .logo-text span {
            color: #0058be;
        }

        .doc-title {
            vertical-align: middle;
            text-align: right;
        }

        .doc-title h2 {
            margin: 0;
            font-size: 18px;
            font-weight: 800;
            color: #111111;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .parties-box {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            margin-bottom: 20px;
            background-color: #fafafa;
        }

        .parties-box td {
            padding: 12px 15px;
            vertical-align: top;
            width: 50%;
        }

        .parties-box td:first-child {
            border-right: 1px solid #e5e7eb;
        }

        .parties-title {
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            color: #6b7280;
            margin-bottom: 6px;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 4px;
        }

        .info-row {
            margin-bottom: 4px;
            display: flex;
        }

        .info-label {
            font-weight: 700;
            width: 110px;
            flex-shrink: 0;
            color: #4b5563;
        }

        .info-value {
            color: #111111;
        }

        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            border: 1px solid #e5e7eb;
        }

        .meta-table th, .meta-table td {
            border: 1px solid #e5e7eb;
            padding: 10px;
            text-align: left;
            width: 25%;
        }

        .meta-table th {
            background-color: #f3f4f6;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            color: #4b5563;
            letter-spacing: 0.5px;
        }

        .meta-table td {
            font-size: 11px;
            font-weight: 600;
        }

        .section-title {
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            margin-bottom: 8px;
            color: #111111;
            letter-spacing: 0.5px;
            border-bottom: 1.5px solid #111111;
            padding-bottom: 4px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .items-table th, .items-table td {
            padding: 10px 8px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        .items-table th {
            border-bottom: 2px solid #111111;
            background-color: #fafafa;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            color: #4b5563;
        }

        .items-table td {
            font-size: 11px;
        }

        .text-right {
            text-align: right !important;
        }

        .payment-verification-box {
            border: 1px solid #e5e7eb;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .payment-verification-box.paid {
            border-color: #10b981;
            background-color: #ecfdf5;
            color: #065f46;
        }
        .payment-verification-box.paid strong {
            color: #047857;
        }

        .payment-verification-box.cod {
            border-color: #f59e0b;
            background-color: #fffbeb;
            color: #78350f;
        }
        .payment-verification-box.cod strong {
            color: #b45309;
        }

        .payment-verification-box.pending {
            border-color: #3b82f6;
            background-color: #eff6ff;
            color: #1e3a8a;
        }
        .payment-verification-box.pending strong {
            color: #1d4ed8;
        }

        .payment-verification-box.unpaid {
            border-color: #ef4444;
            background-color: #fef2f2;
            color: #7f1d1d;
        }
        .payment-verification-box.unpaid strong {
            color: #b91c1c;
        }

        .verification-header {
            font-weight: 800;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 6px;
        }

        .verification-details {
            display: grid;
            grid-template-columns: 120px 1fr;
            gap: 4px;
            font-size: 11px;
        }

        .summary-wrapper {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 25px;
        }

        .summary-table {
            width: 300px;
            border-collapse: collapse;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            overflow: hidden;
            background-color: #fafafa;
        }

        .summary-table td {
            padding: 8px 12px;
            font-size: 11px;
            border-bottom: 1px solid #e5e7eb;
        }

        .summary-table tr:last-child td {
            border-bottom: none;
        }

        .summary-table tr.total td {
            background-color: #f3f4f6;
            border-top: 1.5px solid #d1d5db;
            font-weight: 800;
            font-size: 13px;
            color: #111111;
            padding: 10px 12px;
        }

        .notes-section {
            font-size: 10px;
            color: #6b7280;
            line-height: 1.5;
            margin-top: 10px;
        }

        .company-footer {
            margin-top: 40px;
            border-top: 1px solid #e5e7eb;
            padding-top: 15px;
            text-align: center;
            font-size: 10px;
            color: #6b7280;
            line-height: 1.5;
        }

        @media print {
            .no-print-bar {
                display: none !important;
            }
            body {
                padding: 0;
                font-size: 11px;
            }
            .invoice-box {
                max-width: 100%;
                padding: 0;
            }
            .parties-box, .summary-table {
                background-color: transparent !important;
            }
            .meta-table th, .items-table th, .summary-table tr.total td {
                background-color: #f3f4f6 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .payment-verification-box.paid {
                background-color: #ecfdf5 !important;
                border-color: #10b981 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .payment-verification-box.cod {
                background-color: #fffbeb !important;
                border-color: #f59e0b !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .payment-verification-box.pending {
                background-color: #eff6ff !important;
                border-color: #3b82f6 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .payment-verification-box.unpaid {
                background-color: #fef2f2 !important;
                border-color: #ef4444 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
    </style>
</head>
<body>
    <div class="invoice-box">
        <!-- Control Bar (hidden during printing) -->
        <div class="no-print-bar">
            <div>
                <span style="font-weight: 700; color: #374151;">Nota Pesanan SIT-<?= htmlspecialchars($order['order_code']) ?></span>
            </div>
            <div style="display: flex; gap: 8px;">
                <button onclick="window.print()" class="btn">
                    Cetak Nota
                </button>
                <button onclick="window.close()" class="btn btn-secondary">
                    Tutup
                </button>
            </div>
        </div>

        <!-- Invoice Header -->
        <table class="header-table">
            <tr>
                <td class="logo-container">
                    <?php if ($storeLogo): ?>
                        <img src="uploads/logo/<?= sanitizeOutput($storeLogo) ?>" alt="<?= sanitizeOutput($storeName) ?>" class="logo-img">
                    <?php else: ?>
                        <h1 class="logo-text"><span>TC</span> Komputer</h1>
                    <?php endif; ?>
                </td>
                <td class="doc-title">
                    <h2>Nota Pesanan</h2>
                </td>
            </tr>
        </table>

        <!-- Buyer & Seller Details -->
        <table class="parties-box">
            <tr>
                <td>
                    <div class="parties-title">Pembeli (Buyer)</div>
                    <div class="info-row">
                        <span class="info-label">Nama Pembeli:</span>
                        <span class="info-value"><?= sanitizeOutput($order['buyer_name']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Alamat Kirim:</span>
                        <span class="info-value"><?= nl2br(sanitizeOutput($order['buyer_address'])) ?></span>
                    </div>
                    <?php if (!empty($order['area_name'])): ?>
                    <div class="info-row">
                        <span class="info-label">Kecamatan (Kab):</span>
                        <span class="info-value"><?= sanitizeOutput($order['area_name']) ?> (<?= sanitizeOutput($order['regency'] ?? 'Tana Toraja') ?>)</span>
                    </div>
                    <?php endif; ?>
                    <div class="info-row">
                        <span class="info-label">No. Handphone:</span>
                        <span class="info-value"><?= sanitizeOutput($order['buyer_phone']) ?></span>
                    </div>
                </td>
                <td>
                    <div class="parties-title">Penjual (Seller)</div>
                    <div class="info-row">
                        <span class="info-label">Nama Toko:</span>
                        <span class="info-value"><?= sanitizeOutput($storeName) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Alamat Toko:</span>
                        <span class="info-value"><?= nl2br(sanitizeOutput($storeAddress)) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Kontak:</span>
                        <span class="info-value"><?= sanitizeOutput($storePhone) ?> | <?= sanitizeOutput($storeEmail) ?></span>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Transaction Meta -->
        <table class="meta-table">
            <thead>
                <tr>
                    <th>No. Pesanan</th>
                    <th>Tanggal Transaksi</th>
                    <th>Metode Pembayaran</th>
                    <th>Jasa Kirim</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?= sanitizeOutput($order['order_code']) ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?> WITA</td>
                    <td><?= sanitizeOutput($paymentMethodLabel) ?></td>
                    <td><?= sanitizeOutput($shippingOptionLabel) ?></td>
                </tr>
            </tbody>
        </table>

        <!-- Payment Status / Verification Stamp -->
        <?php if ($isPaid): ?>
            <div class="payment-verification-box paid">
                <div class="verification-header">
                    <span style="font-size: 14px;">✔</span> DETAIL PEMBAYARAN TERVERIFIKASI (LUNAS)
                </div>
                <div class="verification-details">
                    <strong>Waktu Pembayaran:</strong>
                    <span><?= $paymentTime ?> WITA</span>
                    <strong>Penerima:</strong>
                    <span><?= sanitizeOutput($storeName) ?></span>
                    <strong>Metode Pembayaran:</strong>
                    <span><?= sanitizeOutput($paymentMethodLabel) ?></span>
                    <strong>Status Transaksi:</strong>
                    <span>Pembayaran berhasil diverifikasi secara otomatis oleh sistem</span>
                </div>
            </div>
        <?php elseif ($order['payment_status'] === 'cod'): ?>
            <div class="payment-verification-box cod">
                <div class="verification-header">
                    <span style="font-size: 14px;">ℹ</span> STATUS PEMBAYARAN: COD (BAYAR DI TEMPAT)
                </div>
                <div class="verification-details">
                    <strong>Metode:</strong>
                    <span>COD (Cash on Delivery)</span>
                    <strong>Total Tagihan:</strong>
                    <span><?= formatRupiah($total) ?></span>
                    <strong>Keterangan:</strong>
                    <span>Pembayaran tunai dilakukan secara langsung kepada kurir saat menerima barang</span>
                </div>
            </div>
        <?php elseif ($order['payment_status'] === 'menunggu_konfirmasi'): ?>
            <div class="payment-verification-box pending">
                <div class="verification-header">
                    <span style="font-size: 14px;">⌛</span> STATUS PEMBAYARAN: MENUNGGU VERIFIKASI
                </div>
                <div class="verification-details">
                    <strong>Metode:</strong>
                    <span>Transfer Bank</span>
                    <strong>Keterangan:</strong>
                    <span>Bukti transfer telah dikirimkan oleh pembeli dan sedang dalam proses pengecekan admin</span>
                </div>
            </div>
        <?php else: ?>
            <div class="payment-verification-box unpaid">
                <div class="verification-header">
                    <span style="font-size: 14px;">⚠</span> STATUS PEMBAYARAN: BELUM DIBAYAR (UNPAID)
                </div>
                <div class="verification-details">
                    <strong>Metode:</strong>
                    <span>Transfer Bank</span>
                    <strong>Total Tagihan:</strong>
                    <span><?= formatRupiah($total) ?></span>
                    <strong>Instruksi:</strong>
                    <span>Silakan lakukan transfer ke salah satu rekening berikut:<br><strong style="font-family: monospace;"><?= str_replace(["\r\n", "\r", "\n"], " | ", sanitizeOutput($storeSettings['bank_account'] ?? '')) ?></strong><br>Setelah transfer, kirim bukti transfer ke WhatsApp <strong><?= sanitizeOutput($storePhone) ?></strong></span>
                </div>
            </div>
        <?php endif; ?>

        <!-- Order Items -->
        <div class="section-title">Rincian Pesanan</div>
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 5%;">No.</th>
                    <th style="width: 50%;">Nama Produk</th>
                    <th style="width: 15%; text-align: right;">Harga Satuan</th>
                    <th style="width: 10%; text-align: center;">Kuantitas</th>
                    <th style="width: 20%; text-align: right;">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $index = 1;
                $totalQty = 0;
                foreach ($orderItems as $item): 
                    $totalQty += (int)$item['quantity'];
                    $displayName = $item['product_name'];
                    if (!empty($item['brand'])) {
                        $displayName = '[' . $item['brand'] . '] ' . $displayName;
                    }
                ?>
                    <tr<?= ((int)$item['product_price'] === 0) ? ' style="background-color: #f0fdf4;"' : '' ?>>
                        <td><?= $index++ ?></td>
                        <td>
                            <strong><?= sanitizeOutput($displayName) ?></strong>
                            <?php if ((int)$item['product_price'] === 0): ?>
                                <span style="display:inline-block; background:#16a34a; color:#fff; font-size:8px; font-weight:900; padding:1px 5px; border-radius:3px; margin-left:4px; vertical-align:middle;">🎁 BONUS GRATIS</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-right"><?= formatRupiah((int)$item['product_price']) ?></td>
                        <td style="text-align: center;"><?= (int)$item['quantity'] ?></td>
                        <td class="text-right"><?= formatRupiah((int)$item['subtotal']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pricing Calculations Summary -->
        <div class="summary-wrapper">
            <table class="summary-table">
                <tr>
                    <td>Subtotal Pesanan</td>
                    <td class="text-right"><?= formatRupiah($subtotal) ?></td>
                </tr>
                <tr>
                    <td>Subtotal Pengiriman</td>
                    <td class="text-right"><?= formatRupiah($shippingCost) ?></td>
                </tr>
                <?php if (!empty($order['discount_amount']) && $order['discount_amount'] > 0): ?>
                    <tr>
                        <td>Diskon / Promosi<?php if (!empty($order['applied_promotions'])): ?><br><span style="font-size:8px; color:#6b7280;">(<?= sanitizeOutput($order['applied_promotions']) ?>)</span><?php endif; ?></td>
                        <td class="text-right" style="color: #10b981;">-<?= formatRupiah((int)$order['discount_amount']) ?></td>
                    </tr>
                <?php endif; ?>
                <?php if ($serviceFee > 0): ?>
                    <tr>
                        <td>Biaya Layanan</td>
                        <td class="text-right"><?= formatRupiah($serviceFee) ?></td>
                    </tr>
                <?php endif; ?>
                <tr class="total">
                    <td>Total Pembayaran</td>
                    <td class="text-right"><?= formatRupiah($total) ?></td>
                </tr>
            </table>
        </div>

        <!-- Notes / T&C -->
        <div class="notes-section">
            <strong>Catatan Toko:</strong>
            <ul style="margin: 4px 0; padding-left: 15px;">
                <li>Pesanan ini adalah bukti transaksi sah dari <?= htmlspecialchars($storeName) ?>.</li>
                <li>Simpan nomor pesanan Anda untuk keperluan klaim garansi toko dan bantuan teknis.</li>
                <li>Biaya-biaya yang ditagihkan oleh <?= htmlspecialchars($storeName) ?> sudah termasuk PPN (jika ada).</li>
            </ul>
        </div>

        <!-- Corporate Address Footer -->
        <div class="company-footer">
            <strong><?= htmlspecialchars($storeName) ?></strong><br>
            <?= htmlspecialchars($storeAddress) ?><br>
            Telepon: <?= htmlspecialchars($storePhone) ?> | Email: <?= htmlspecialchars($storeEmail) ?>
        </div>
    </div>

    <script>
        // Auto trigger window print on load
        window.addEventListener('load', () => {
            // Wait a split second to make sure styles render correctly
            setTimeout(() => {
                window.print();
            }, 300);
        });
    </script>
</body>
</html>
