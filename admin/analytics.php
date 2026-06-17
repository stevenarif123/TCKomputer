<?php
/**
 * Admin Analytics Page
 *
 * Displays Pillar-A analytics metrics with Chart.js visualizations:
 * - Conversion funnel (Visits → Registrations → Purchases)
 * - Sales summary KPIs (Revenue, Gross Profit, AOV, Cancellation Rate)
 * - Revenue trend chart (day/week/month)
 * - Sales by category and by shipping area
 * - Top profitable products
 * - Promotion effectiveness
 * - Stock health (low-stock + out-of-stock alerts)
 *
 * All math delegated to config/analytics.php (no inline computation).
 * All stored values passed through sanitizeOutput() before HTML render.
 */

$pageTitle = 'Analitik';
require_once __DIR__ . '/../includes/admin-header.php';
require_once __DIR__ . '/../config/analytics.php';

// ─── Date range ───────────────────────────────────────────────────────────────
$range       = normalizeDateRange($_GET['from'] ?? null, $_GET['to'] ?? null);
$fromDisplay = sanitizeOutput($range['start_date']);
$toDisplay   = sanitizeOutput($range['end_date']);

// Granularity for trend chart
$granularity = $_GET['granularity'] ?? 'day';
if (!in_array($granularity, ['day', 'week', 'month'], true)) {
    $granularity = 'day';
}

// ─── Gather all metrics (no math inline) ─────────────────────────────────────
try {
    $funnel    = getFunnelStats($pdo, $range);
    $summary   = getSalesSummary($pdo, $range);
    $products  = getProfitByProduct($pdo, $range, 10);
    $byCategory = getSalesByCategory($pdo, $range);
    $byArea    = getSalesByArea($pdo, $range);
    $trend     = getSalesTrend($pdo, $range, $granularity);
    $promotions = getPromotionEffectiveness($pdo, $range);
    $stock     = getStockHealth($pdo, 5);
    $analyticsError = null;
} catch (Throwable $e) {
    error_log('Analytics page error: ' . $e->getMessage());
    $analyticsError = 'Gagal mengambil data analitik: ' . $e->getMessage();
    $funnel = $summary = $products = $byCategory = $byArea = $trend = $promotions = [];
    $stock  = ['low_stock' => [], 'out_of_stock' => [], 'error' => $analyticsError];
}

// ─── Prepare Chart.js datasets ────────────────────────────────────────────────
$trendLabels   = json_encode(array_column($trend, 'bucket'));
$trendRevenue  = json_encode(array_column($trend, 'revenue'));

$catLabels  = json_encode(array_column($byCategory, 'category_name'));
$catRevenue = json_encode(array_column($byCategory, 'revenue'));
$catProfit  = json_encode(array_column($byCategory, 'profit'));

$areaLabels  = json_encode(array_column($byArea, 'area_name'));
$areaRevenue = json_encode(array_column($byArea, 'revenue'));

$funnelLabels = json_encode(['Kunjungan', 'Registrasi', 'Pembelian']);
$funnelValues = json_encode([$funnel['visits'] ?? 0, $funnel['registrations'] ?? 0, $funnel['purchases'] ?? 0]);
?>

<div class="analytics-page">

<?php if ($analyticsError): ?>
<div class="alert alert-error">
    <strong>⚠️ <?= sanitizeOutput($analyticsError) ?></strong>
</div>
<?php endif; ?>

<!-- Date Range Filter -->
<div class="card">
    <form method="GET" action="" class="analytics-filter-form">
        <div class="analytics-filter-field">
            <label>Dari Tanggal</label>
            <input type="date" name="from" value="<?= $fromDisplay ?>" max="<?= date('Y-m-d') ?>"
                   class="form-control">
        </div>
        <div class="analytics-filter-field">
            <label>Sampai Tanggal</label>
            <input type="date" name="to" value="<?= $toDisplay ?>" max="<?= date('Y-m-d') ?>"
                   class="form-control">
        </div>
        <div class="analytics-filter-field">
            <label>Granularitas Tren</label>
            <select name="granularity" class="form-select">
                <option value="day"   <?= $granularity === 'day'   ? 'selected' : '' ?>>Harian</option>
                <option value="week"  <?= $granularity === 'week'  ? 'selected' : '' ?>>Mingguan</option>
                <option value="month" <?= $granularity === 'month' ? 'selected' : '' ?>>Bulanan</option>
            </select>
        </div>
        <div class="analytics-filter-actions">
            <button type="submit" class="btn btn-primary">
                <span class="material-symbols-outlined">filter_alt</span>
                Filter
            </button>
            <a href="analytics" class="btn btn-outline">Reset</a>
        </div>
    </form>
</div>

<!-- ── KPI Cards ─────────────────────────────────────────────────────────── -->
<div class="stats-grid">

    <div class="stat-card-modern">
        <div class="stat-card-icon bg-gradient-emerald">
            <span class="material-symbols-outlined">payments</span>
        </div>
        <div class="stat-card-info">
            <div class="stat-card-title">Total Pendapatan</div>
            <div class="stat-card-value"><?= sanitizeOutput(formatRupiah((int)($summary['revenue'] ?? 0))) ?></div>
        </div>
    </div>

    <div class="stat-card-modern">
        <div class="stat-card-icon bg-gradient-indigo">
            <span class="material-symbols-outlined">trending_up</span>
        </div>
        <div class="stat-card-info">
            <div class="stat-card-title">Laba Kotor (<?= sanitizeOutput(number_format((float)($summary['margin_pct'] ?? 0), 1)) ?>%)</div>
            <div class="stat-card-value"><?= sanitizeOutput(formatRupiah((int)($summary['gross_profit'] ?? 0))) ?></div>
        </div>
    </div>

    <div class="stat-card-modern">
        <div class="stat-card-icon bg-gradient-amber">
            <span class="material-symbols-outlined">shopping_bag</span>
        </div>
        <div class="stat-card-info">
            <div class="stat-card-title">Rata-rata Nilai Pesanan</div>
            <div class="stat-card-value"><?= sanitizeOutput(formatRupiah((int)($summary['aov'] ?? 0))) ?></div>
        </div>
    </div>

    <div class="stat-card-modern">
        <div class="stat-card-icon bg-gradient-rose">
            <span class="material-symbols-outlined">cancel</span>
        </div>
        <div class="stat-card-info">
            <div class="stat-card-title">Tingkat Pembatalan (<?= (int)($summary['cancelled_count'] ?? 0) ?>/<?= (int)($summary['total_count'] ?? 0) ?>)</div>
            <div class="stat-card-value"><?= sanitizeOutput(number_format((float)($summary['cancellation_rate'] ?? 0) * 100, 1)) ?>%</div>
        </div>
    </div>

    <div class="stat-card-modern">
        <div class="stat-card-icon bg-gradient-violet">
            <span class="material-symbols-outlined">visibility</span>
        </div>
        <div class="stat-card-info">
            <div class="stat-card-title">Kunjungan Unik</div>
            <div class="stat-card-value"><?= sanitizeOutput(number_format((int)($funnel['visits'] ?? 0))) ?></div>
        </div>
    </div>

    <div class="stat-card-modern">
        <div class="stat-card-icon bg-gradient-cyan">
            <span class="material-symbols-outlined">person_add</span>
        </div>
        <div class="stat-card-info">
            <div class="stat-card-title">Registrasi Baru</div>
            <div class="stat-card-value"><?= sanitizeOutput(number_format((int)($funnel['registrations'] ?? 0))) ?></div>
        </div>
    </div>

</div>

<!-- ── Funnel + Revenue Trend ──────────────────────────────────────────────── -->
<div class="grid-2col-asym-rev">

    <!-- Conversion Funnel -->
    <div class="card">
        <h3 class="card-section-title">
            <span class="material-symbols-outlined" style="color:var(--admin-danger);">filter_alt_off</span>
            Funnel Konversi
        </h3>
        <canvas id="funnelChart" height="220"></canvas>
        <div class="funnel-stats">
            <div class="funnel-stat-row">
                <span>Konversi ke Registrasi:</span>
                <strong><?= sanitizeOutput(number_format((float)($funnel['registration_rate'] ?? 0) * 100, 1)) ?>%</strong>
            </div>
            <div class="funnel-stat-row">
                <span>Konversi ke Pembelian:</span>
                <strong><?= sanitizeOutput(number_format((float)($funnel['purchase_rate'] ?? 0) * 100, 1)) ?>%</strong>
            </div>
            <div class="funnel-stat-row funnel-stat-row--total">
                <span>Konversi Keseluruhan:</span>
                <strong style="color:var(--admin-primary);"><?= sanitizeOutput(number_format((float)($funnel['overall_rate'] ?? 0) * 100, 1)) ?>%</strong>
            </div>
        </div>
    </div>

    <!-- Revenue Trend -->
    <div class="card">
        <h3 class="card-section-title">
            <span class="material-symbols-outlined" style="color:var(--admin-primary);">show_chart</span>
            Tren Pendapatan
        </h3>
        <canvas id="trendChart" height="220"></canvas>
    </div>

</div>

<!-- ── Category + Area ─────────────────────────────────────────────────────── -->
<div class="grid-2col-equal">

    <div class="card">
        <h3 class="card-section-title">
            <span class="material-symbols-outlined" style="color:var(--admin-info);">category</span>
            Penjualan per Kategori
        </h3>
        <div class="chart-canvas-wrap">
            <canvas id="categoryChart"></canvas>
        </div>
    </div>

    <div class="card">
        <h3 class="card-section-title">
            <span class="material-symbols-outlined" style="color:var(--admin-warning);">distance</span>
            Penjualan per Area Kirim
        </h3>
        <div class="chart-canvas-wrap">
            <canvas id="areaChart"></canvas>
        </div>
    </div>

</div>

<!-- ── Top Products ────────────────────────────────────────────────────────── -->
<div class="card">
    <h3 class="card-section-title">
        <span class="material-symbols-outlined" style="color:var(--admin-primary);">emoji_events</span>
        Produk Terprofitabel (Top 10)
    </h3>
    <?php if (!empty($products)): ?>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Produk</th>
                    <th style="text-align:right;">Terjual</th>
                    <th style="text-align:right;">Pendapatan</th>
                    <th style="text-align:right;">HPP (Harga Beli)</th>
                    <th style="text-align:right;">Laba Kotor</th>
                    <th style="text-align:right;">Margin</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $i => $p): ?>
                <tr>
                    <td style="color:var(--admin-text-muted);"><?= $i + 1 ?></td>
                    <td style="font-weight:600; color:var(--admin-text);"><?= sanitizeOutput($p['product_name'] ?? '-') ?></td>
                    <td style="text-align:right; font-weight:500;"><?= sanitizeOutput(number_format((int)$p['units_sold'])) ?></td>
                    <td style="text-align:right;"><?= sanitizeOutput(formatRupiah((int)$p['revenue'])) ?></td>
                    <td style="text-align:right; color:var(--admin-text-muted);"><?= sanitizeOutput(formatRupiah((int)$p['cost'])) ?></td>
                    <td style="text-align:right; <?= (int)$p['gross_profit'] < 0 ? 'color:var(--admin-danger);' : 'color:var(--admin-success); font-weight:700;' ?>"><?= sanitizeOutput(formatRupiah((int)$p['gross_profit'])) ?></td>
                    <td style="text-align:right; font-weight:600;"><?= sanitizeOutput(number_format((float)$p['margin_pct'], 1)) ?>%</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <p style="color:var(--admin-text-muted); text-align:center; padding:2rem 0;">Belum ada data penjualan di rentang ini.</p>
    <?php endif; ?>
</div>

<!-- ── Promotions ──────────────────────────────────────────────────────────── -->
<?php if (!empty($promotions)): ?>
<div class="card">
    <h3 class="card-section-title">
        <span class="material-symbols-outlined" style="color:var(--admin-warning);">campaign</span>
        Efektivitas Promosi
    </h3>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Promosi</th>
                    <th style="text-align:right;">Pesanan</th>
                    <th style="text-align:right;">Total Diskon</th>
                    <th style="text-align:right;">Total Pendapatan</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($promotions as $promo): ?>
                <tr>
                    <td style="font-weight:600; color:var(--admin-text);"><?= sanitizeOutput($promo['promotion_name'] ?? '-') ?></td>
                    <td style="text-align:right; font-weight:500;"><?= (int)$promo['order_count'] ?></td>
                    <td style="text-align:right; color:var(--admin-danger); font-weight:500;"><?= sanitizeOutput(formatRupiah((int)$promo['total_discount'])) ?></td>
                    <td style="text-align:right; color:var(--admin-success); font-weight:700;"><?= sanitizeOutput(formatRupiah((int)$promo['total_revenue'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- ── Stock Health ────────────────────────────────────────────────────────── -->
<?php
$hasStockIssues = !empty($stock['out_of_stock']) || !empty($stock['low_stock']);
$stockError     = $stock['error'] ?? null;
?>
<?php if ($stockError): ?>
<div class="card card--warning-border">
    <p style="color:var(--admin-warning); margin:0; font-weight:600;">⚠️ <?= sanitizeOutput($stockError) ?></p>
</div>
<?php elseif ($hasStockIssues): ?>
<div class="card">
    <h3 class="card-section-title">
        <span class="material-symbols-outlined" style="color:var(--admin-danger);">inventory</span>
        Peringatan Stok Produk
    </h3>
    <div class="grid-2col-equal">

        <?php if (!empty($stock['out_of_stock'])): ?>
        <div class="stock-alert-box stock-alert-box--danger">
            <h4 class="stock-alert-title stock-alert-title--danger">
                <span class="material-symbols-outlined" style="font-size:18px;">cancel</span>
                Stok Habis (<?= count($stock['out_of_stock']) ?>)
            </h4>
            <?php foreach ($stock['out_of_stock'] as $p): ?>
            <div class="stock-alert-row">
                <span><?= sanitizeOutput($p['name']) ?></span>
                <span style="color:var(--admin-danger); font-weight:700;">0 unit</span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($stock['low_stock'])): ?>
        <div class="stock-alert-box stock-alert-box--warning">
            <h4 class="stock-alert-title stock-alert-title--warning">
                <span class="material-symbols-outlined" style="font-size:18px;">warning</span>
                Stok Menipis (<?= count($stock['low_stock']) ?>)
            </h4>
            <?php foreach ($stock['low_stock'] as $p): ?>
            <div class="stock-alert-row">
                <span><?= sanitizeOutput($p['name']) ?></span>
                <span style="color:var(--admin-warning); font-weight:700;"><?= (int)$p['stock'] ?> unit</span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div>
</div>
<?php endif; ?>

</div><!-- .analytics-page -->

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    'use strict';

    // Detect dark mode
    const isDark = document.body.classList.contains('dark-mode');
    const gridColor  = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)';
    const textColor  = isDark ? '#94a3b8' : '#64748b';
    Chart.defaults.color = textColor;

    // ── Funnel Chart ────────────────────────────────────────────────────────
    const funnelCtx = document.getElementById('funnelChart');
    if (funnelCtx) {
        new Chart(funnelCtx, {
            type: 'bar',
            data: {
                labels: <?= $funnelLabels ?>,
                datasets: [{
                    data: <?= $funnelValues ?>,
                    backgroundColor: ['#6366f1', '#10b981', '#f59e0b'],
                    borderRadius: 6,
                }]
            },
            options: {
                responsive: true,
                indexAxis: 'y',
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { color: gridColor }, ticks: { color: textColor } },
                    y: { grid: { display: false }, ticks: { color: textColor } },
                }
            }
        });
    }

    // ── Revenue Trend Chart ─────────────────────────────────────────────────
    const trendCtx = document.getElementById('trendChart');
    if (trendCtx) {
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: <?= $trendLabels ?>,
                datasets: [{
                    label: 'Pendapatan (Rp)',
                    data: <?= $trendRevenue ?>,
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99,102,241,0.1)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: <?= count($trend) > 60 ? 0 : 3 ?>,
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { color: gridColor }, ticks: { color: textColor, maxTicksLimit: 12 } },
                    y: {
                        grid: { color: gridColor },
                        ticks: {
                            color: textColor,
                            callback: v => 'Rp ' + (v / 1e6).toFixed(1) + 'jt'
                        }
                    },
                }
            }
        });
    }

    // ── Category Chart ──────────────────────────────────────────────────────
    const catCtx = document.getElementById('categoryChart');
    if (catCtx) {
        const colors = ['#6366f1','#10b981','#f59e0b','#ef4444','#06b6d4','#8b5cf6','#ec4899','#84cc16'];
        new Chart(catCtx, {
            type: 'doughnut',
            data: {
                labels: <?= $catLabels ?>,
                datasets: [{
                    data: <?= $catRevenue ?>,
                    backgroundColor: colors,
                    borderWidth: 2,
                    borderColor: isDark ? '#1e293b' : '#ffffff',
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom', labels: { color: textColor, padding: 12 } },
                    tooltip: {
                        callbacks: {
                            label: ctx => ' Rp ' + ctx.raw.toLocaleString('id-ID')
                        }
                    }
                }
            }
        });
    }

    // ── Area Chart ──────────────────────────────────────────────────────────
    const areaCtx = document.getElementById('areaChart');
    if (areaCtx) {
        new Chart(areaCtx, {
            type: 'bar',
            data: {
                labels: <?= $areaLabels ?>,
                datasets: [{
                    label: 'Pendapatan',
                    data: <?= $areaRevenue ?>,
                    backgroundColor: '#06b6d4',
                    borderRadius: 5,
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false }, ticks: { color: textColor } },
                    y: {
                        grid: { color: gridColor },
                        ticks: {
                            color: textColor,
                            callback: v => 'Rp ' + (v / 1e6).toFixed(1) + 'jt'
                        }
                    }
                }
            }
        });
    }
})();
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
