<?php
/**
 * Admin Dashboard
 * Displays summary stats, recent orders, visual analytics, low stock warnings,
 * and quick tools like Notepad.
 */

$pageTitle = "Dashboard";
require_once __DIR__ . '/../includes/admin-header.php';
require_once __DIR__ . '/../config/analytics.php';

// --- Analytics Engine: last 30 days ---
$dashRange = normalizeDateRange(null, null); // defaults to last 30 days
$summary   = getSalesSummary($pdo, $dashRange);
$funnel    = getFunnelStats($pdo, $dashRange);
$stockHealth = getStockHealth($pdo, 5);

// --- Basic counts (not date-scoped) ---
$totalProducts = (int) $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$totalOrders   = (int) $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$pendingOrders = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE order_status = 'menunggu_konfirmasi'")->fetchColumn();
$totalRevenue  = (int)($summary['revenue'] ?? 0); // from analytics engine (last 30 days)

// --- Recent Orders (last 5) ---
$recentOrders = $pdo->query("SELECT * FROM orders ORDER BY created_at DESC LIMIT 5")->fetchAll();

// --- Monthly Revenue (last 6 months) ---
$monthlyRevenueData = [];
try {
    $monthlyStmt = $pdo->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') AS month,
            SUM(total) AS total_sales
        FROM orders
        WHERE order_status = 'selesai' 
          AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month ASC
    ");
    $monthlyRevenueData = $monthlyStmt->fetchAll();
} catch (PDOException $e) {
    error_log('Error fetching monthly revenue: ' . $e->getMessage());
}

$monthsMap = [
    '01' => 'Jan', '02' => 'Feb', '03' => 'Mar', '04' => 'Apr', '05' => 'Mei', '06' => 'Jun',
    '07' => 'Jul', '08' => 'Agu', '09' => 'Sep', '10' => 'Okt', '11' => 'Nov', '12' => 'Des'
];

$chartMonths = [];
$chartRevenue = [];
for ($i = 5; $i >= 0; $i--) {
    $time = strtotime("-$i months");
    $mNum = date('m', $time);
    $mYear = date('y', $time);
    $mKey = date('Y-m', $time);
    
    $mName = $monthsMap[$mNum] ?? $mNum;
    $chartMonths[] = $mName . ' ' . $mYear;
    
    $salesVal = 0;
    foreach ($monthlyRevenueData as $row) {
        if ($row['month'] === $mKey) {
            $salesVal = (int)$row['total_sales'];
            break;
        }
    }
    $chartRevenue[] = $salesVal;
}

// --- Order Status Distribution ---
$statusData = [];
try {
    $statusStmt = $pdo->query("
        SELECT order_status, COUNT(*) AS count
        FROM orders
        GROUP BY order_status
    ");
    $statusData = $statusStmt->fetchAll();
} catch (PDOException $e) {
    error_log('Error fetching order status count: ' . $e->getMessage());
}

$statusLabelsMap = [
    'menunggu_konfirmasi' => 'Menunggu Konfirmasi',
    'diproses' => 'Diproses',
    'siap_diantar' => 'Siap Diantar',
    'dikirim' => 'Dikirim',
    'selesai' => 'Selesai',
    'dibatalkan' => 'Dibatalkan',
];

$statusLabels = [];
$statusCounts = [];
$statusColors = [
    'menunggu_konfirmasi' => '#ea580c', // orange
    'diproses' => '#2563eb', // blue
    'siap_diantar' => '#7c3aed', // purple
    'dikirim' => '#0891b2', // cyan
    'selesai' => '#16a34a', // green
    'dibatalkan' => '#dc2626', // red
];
$chartColors = [];

foreach ($statusData as $row) {
    $status = $row['order_status'];
    $statusLabels[] = $statusLabelsMap[$status] ?? $status;
    $statusCounts[] = (int)$row['count'];
    $chartColors[] = $statusColors[$status] ?? '#64748b';
}

if (empty($statusData)) {
    $statusLabels = ['Belum Ada Pesanan'];
    $statusCounts = [0];
    $chartColors = ['#e2e8f0'];
}

// --- Low Stock Products (stock <= 5) ---
$lowStockProducts = [];
try {
    $lowStockStmt = $pdo->query("
        SELECT id, name, stock 
        FROM products 
        WHERE stock <= 5 AND is_active = 1
        ORDER BY stock ASC 
        LIMIT 5
    ");
    $lowStockProducts = $lowStockStmt->fetchAll();
} catch (PDOException $e) {
    error_log('Error fetching low stock products: ' . $e->getMessage());
}

// Translate payment status to display label
function getDashboardPaymentStatusLabel(string $status): string
{
    $labels = [
        'belum_dibayar' => 'Belum Dibayar',
        'menunggu_konfirmasi' => 'Menunggu Konfirmasi',
        'sudah_dibayar' => 'Sudah Dibayar',
        'cod' => 'COD (Bayar di Tempat)',
    ];
    return $labels[$status] ?? $status;
}

// Translate order status to display label
function getDashboardOrderStatusLabel(string $status): string
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
?>

<div class="stats-grid">
    <div class="stat-card-modern">
        <div class="stat-card-icon bg-gradient-indigo">
            <span class="material-symbols-outlined">inventory_2</span>
        </div>
        <div class="stat-card-info">
            <div class="stat-card-title">Total Produk</div>
            <div class="stat-card-value"><?= sanitizeOutput((string) $totalProducts) ?></div>
        </div>
    </div>
    <div class="stat-card-modern">
        <div class="stat-card-icon bg-gradient-cyan">
            <span class="material-symbols-outlined">shopping_cart</span>
        </div>
        <div class="stat-card-info">
            <div class="stat-card-title">Total Pesanan</div>
            <div class="stat-card-value"><?= sanitizeOutput((string) $totalOrders) ?></div>
        </div>
    </div>
    <div class="stat-card-modern">
        <div class="stat-card-icon bg-gradient-amber">
            <span class="material-symbols-outlined">hourglass_empty</span>
        </div>
        <div class="stat-card-info">
            <div class="stat-card-title">Menunggu Konfirmasi</div>
            <div class="stat-card-value"><?= sanitizeOutput((string) $pendingOrders) ?></div>
        </div>
    </div>
    <div class="stat-card-modern">
        <div class="stat-card-icon bg-gradient-emerald">
            <span class="material-symbols-outlined">payments</span>
        </div>
        <div class="stat-card-info">
            <div class="stat-card-title">Total Pendapatan</div>
            <div class="stat-card-value"><?= sanitizeOutput(formatRupiah($totalRevenue)) ?></div>
        </div>
    </div>
</div>

<!-- Charts Section -->
<div class="dashboard-charts-grid" style="margin-top: 20px;">
    <!-- Revenue Line Chart -->
    <div class="chart-card">
        <h3>
            <span class="material-symbols-outlined" style="color: var(--admin-primary); font-size: 22px; vertical-align: middle;">show_chart</span>
            Grafik Pendapatan Bulanan (6 Bulan Terakhir)
        </h3>
        <div class="chart-container">
            <canvas id="salesChart"></canvas>
        </div>
    </div>
    
    <!-- Order Status Doughnut Chart -->
    <div class="chart-card">
        <h3>
            <span class="material-symbols-outlined" style="color: var(--admin-info); font-size: 22px; vertical-align: middle;">pie_chart</span>
            Status Pesanan
        </h3>
        <div class="chart-container">
            <canvas id="statusChart"></canvas>
        </div>
    </div>
</div>

<!-- Main Section & Widgets Sidebar -->
<div class="dashboard-layout-main" style="margin-top: 20px;">
    <!-- Recent Orders Table -->
    <div class="dashboard-section" style="margin-bottom: 0;">
        <h2 style="font-size: 1.1rem; font-weight: 800; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; color: var(--admin-text);">
            <span class="material-symbols-outlined" style="color: var(--admin-primary); font-size: 22px; vertical-align: middle;">receipt_long</span>
            Pesanan Terbaru
        </h2>
        <?php if (empty($recentOrders)): ?>
            <p class="empty-message" style="padding: 24px 0;">Belum ada pesanan terbaru.</p>
        <?php else: ?>
            <div class="table-responsive" style="box-shadow: none; border-color: var(--admin-border);">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Kode Pesanan</th>
                            <th>Pembeli</th>
                            <th>Total</th>
                            <th>Status Pembayaran</th>
                            <th>Status Pesanan</th>
                            <th>Tanggal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentOrders as $order): ?>
                        <tr>
                            <td><a href="order-detail?id=<?= (int)$order['id'] ?>" style="font-weight: 700; color: var(--admin-primary); text-decoration: none;"><?= sanitizeOutput($order['order_code']) ?></a></td>
                            <td><?= sanitizeOutput($order['buyer_name']) ?></td>
                            <td><?= sanitizeOutput(formatRupiah((int) $order['total'])) ?></td>
                            <td>
                                <span class="badge badge-payment-<?= sanitizeOutput($order['payment_status']) ?>">
                                    <?= sanitizeOutput(getDashboardPaymentStatusLabel($order['payment_status'])) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-order-<?= sanitizeOutput($order['order_status']) ?>">
                                    <?= sanitizeOutput(getDashboardOrderStatusLabel($order['order_status'])) ?>
                                </span>
                            </td>
                            <td><?= sanitizeOutput(date('d/m/Y H:i', strtotime($order['created_at']))) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div style="margin-top: 16px; text-align: right;">
                <a href="orders" class="btn btn-outline btn-sm">Lihat Semua Pesanan &raquo;</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Widgets Sidebar -->
    <div class="dashboard-sidebar-widgets">
        <!-- Low Stock Alerts Widget -->
        <div class="admin-card">
            <h3 style="font-size: 1rem; font-weight: 800; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; color: var(--admin-text);">
                <span class="material-symbols-outlined" style="color: var(--admin-danger); font-size: 22px; vertical-align: middle;">error</span>
                Peringatan Stok Menipis
            </h3>
            <?php if (empty($lowStockProducts)): ?>
                <p style="font-size: 0.85rem; color: var(--admin-text-muted);">Semua stok produk aman.</p>
            <?php else: ?>
                <div class="low-stock-list">
                    <?php foreach ($lowStockProducts as $p): ?>
                        <div class="low-stock-item">
                            <span class="low-stock-name" title="<?= sanitizeOutput($p['name']) ?>"><?= sanitizeOutput($p['name']) ?></span>
                            <span class="low-stock-value"><?= (int)$p['stock'] ?> unit</span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div style="margin-top: 12px; text-align: right;">
                    <a href="products" class="btn btn-outline btn-sm" style="padding: 4px 10px; font-size: 11px;">Kelola Produk &raquo;</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Quick Notebook Widget -->
        <div class="admin-card">
            <div class="notepad-widget">
                <h3 style="font-size: 1rem; font-weight: 800; display: flex; align-items: center; gap: 8px; margin-bottom: 4px; color: var(--admin-text);">
                    <span class="material-symbols-outlined" style="color: var(--admin-warning); font-size: 22px; vertical-align: middle;">edit_note</span>
                    Catatan Cepat Admin
                </h3>
                <p style="font-size: 0.75rem; color: var(--admin-text-muted); margin-bottom: 8px;">Disimpan otomatis di komputer Anda.</p>
                <textarea id="notepadTextarea" class="notepad-textarea" placeholder="Tulis pengingat, catatan restock, atau tugas admin di sini..."></textarea>
            </div>
        </div>
    </div>
</div>

<!-- Chart Initialization Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Monthly Revenue Chart (Line)
    const salesCtx = document.getElementById('salesChart').getContext('2d');
    const salesChart = new Chart(salesCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode($chartMonths) ?>,
            datasets: [{
                label: 'Pendapatan (Rp)',
                data: <?= json_encode($chartRevenue) ?>,
                borderColor: '#2563eb', // primary blue
                backgroundColor: 'rgba(37, 99, 235, 0.08)',
                borderWidth: 3,
                tension: 0.35,
                fill: true,
                pointBackgroundColor: '#2563eb',
                pointHoverRadius: 7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let value = context.raw;
                            return ' Pendapatan: Rp ' + new Intl.NumberFormat('id-ID').format(value);
                        }
                    }
                }
            },
            scales: {
                y: {
                    grid: {
                        color: 'rgba(100, 116, 139, 0.12)'
                    },
                    ticks: {
                        color: '#64748b',
                        font: {
                            family: 'Inter',
                            size: 11
                        },
                        callback: function(value) {
                            if (value >= 1000000) {
                                return 'Rp ' + (value / 1000000) + 'jt';
                            }
                            return 'Rp ' + new Intl.NumberFormat('id-ID').format(value);
                        }
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        color: '#64748b',
                        font: {
                            family: 'Inter',
                            size: 11
                        }
                    }
                }
            }
        }
    });

    // 2. Order Status Chart (Doughnut)
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    const statusChart = new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($statusLabels) ?>,
            datasets: [{
                data: <?= json_encode($statusCounts) ?>,
                backgroundColor: <?= json_encode($chartColors) ?>,
                borderWidth: 2,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: '#64748b',
                        padding: 12,
                        font: {
                            family: 'Inter',
                            size: 11,
                            weight: 500
                        }
                    }
                }
            },
            cutout: '65%'
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
