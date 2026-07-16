<?php
/**
 * TCKomputer API v1 - Statistics Endpoint
 * Returns dashboard key stats (all time and recently).
 */
require_once __DIR__ . '/bootstrap.php';

// Counts
$totalProducts = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$totalCategories = (int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
$totalOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$pendingOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE order_status = 'menunggu_konfirmasi'")->fetchColumn();
$totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

// Revenue (finished orders)
$allTimeRevenue = (int)$pdo->query("SELECT SUM(total) FROM orders WHERE order_status = 'selesai'")->fetchColumn();

// Low stock warning (stock <= 5)
$lowStockProducts = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE stock <= 5 AND is_active = 1")->fetchColumn();

apiSuccess([
    'total_products' => $totalProducts,
    'total_categories' => $totalCategories,
    'total_orders' => $totalOrders,
    'pending_orders' => $pendingOrders,
    'total_users' => $totalUsers,
    'all_time_revenue' => $allTimeRevenue,
    'low_stock_products' => $lowStockProducts
]);
