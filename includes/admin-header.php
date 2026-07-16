<?php
/**
 * Admin Header/Sidebar Include
 * Provides admin navigation, user display, and flash message area.
 * All admin pages include this file after setting $pageTitle.
 */

// Security hardening — must run before session_start and before any output
require_once __DIR__ . '/../config/security.php';
configureSecureSession();
applySecurityHeaders();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/admin-auth.php';

// Protect admin page - redirect to login if not authenticated
requireAdmin();

// Get database connection and admin data
$pdo = getDBConnection();
$adminData = getAdminData($pdo);

// Get flash message if any
$flashMessage = getFlashMessage();

// Generate CSRF token for forms
$csrfToken = generateCSRFToken();

// Count unread admin chat messages
$unreadChatCount = 0;
try {
    $unreadChatCount = (int) $pdo->query(
        "SELECT COALESCE(SUM(unread_admin), 0) FROM chat_sessions WHERE status = 'active'"
    )->fetchColumn();
} catch (Exception $e) { /* Table may not exist yet */ }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? sanitizeOutput($pageTitle) . ' - ' : '' ?>Admin TC Komputer</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=2.4">
    <script>
        // Inline theme checker to prevent flashing
        (function() {
            const theme = localStorage.getItem('admin-theme') || 'light';
            if (theme === 'dark') {
                document.documentElement.classList.add('dark-mode');
                document.addEventListener('DOMContentLoaded', function() {
                    document.body.classList.add('dark-mode');
                    const themeIcon = document.getElementById('theme-icon');
                    if (themeIcon) themeIcon.textContent = 'light_mode';
                });
            }
        })();
    </script>
</head>
<body class="admin-body">
    <!-- Overlay for mobile drawer -->
    <div class="admin-overlay" id="adminOverlay" onclick="toggleSidebar()"></div>

    <div class="admin-wrapper">
        <!-- Sidebar Navigation -->
        <aside class="admin-sidebar" id="adminSidebar">
            <div class="sidebar-header">
                <h2 class="sidebar-logo">
                    <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1; color: var(--admin-primary); font-size: 26px;">devices</span>
                    TC <span class="logo-accent">Komputer</span>
                </h2>
                <span class="sidebar-subtitle">Admin Panel</span>
            </div>

            <?php if ($adminData): ?>
            <div class="admin-user-info">
                <div class="user-avatar-placeholder">
                    <?= sanitizeOutput(strtoupper(substr($adminData['name'], 0, 1))) ?>
                </div>
                <div class="admin-user-details">
                    <span class="admin-user-name"><?= sanitizeOutput($adminData['name']) ?></span>
                    <span class="admin-user-role">Administrator</span>
                </div>
            </div>
            <?php endif; ?>

            <nav class="admin-nav">
                <ul>
                    <li><a href="index" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>"><span class="material-symbols-outlined">dashboard</span> Dashboard</a></li>
                    <li><a href="products" class="nav-link <?= in_array(basename($_SERVER['PHP_SELF']), ['products.php', 'product-add.php', 'product-edit.php']) ? 'active' : '' ?>"><span class="material-symbols-outlined">inventory_2</span> Produk</a></li>
                    <li><a href="categories" class="nav-link <?= in_array(basename($_SERVER['PHP_SELF']), ['categories.php', 'category-add.php', 'category-edit.php']) ? 'active' : '' ?>"><span class="material-symbols-outlined">category</span> Kategori</a></li>
                    <li><a href="orders" class="nav-link <?= in_array(basename($_SERVER['PHP_SELF']), ['orders.php', 'order-detail.php']) ? 'active' : '' ?>"><span class="material-symbols-outlined">shopping_cart</span> Pesanan</a></li>
                    <li><a href="shipping-areas" class="nav-link <?= in_array(basename($_SERVER['PHP_SELF']), ['shipping-areas.php', 'shipping-area-add.php', 'shipping-area-edit.php']) ? 'active' : '' ?>"><span class="material-symbols-outlined">local_shipping</span> Area Kirim</a></li>
                    <li><a href="banners" class="nav-link <?= in_array(basename($_SERVER['PHP_SELF']), ['banners.php', 'banner-add.php', 'banner-edit.php']) ? 'active' : '' ?>"><span class="material-symbols-outlined">view_carousel</span> Banner</a></li>
                    <li><a href="flash-sales" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'flash-sales.php' ? 'active' : '' ?>"><span class="material-symbols-outlined">bolt</span> Flash Sale</a></li>
                    <li><a href="promotions" class="nav-link <?= in_array(basename($_SERVER['PHP_SELF']), ['promotions.php', 'promotion-add.php', 'promotion-edit.php']) ? 'active' : '' ?>"><span class="material-symbols-outlined">campaign</span> Promosi</a></li>
                    <li><a href="faqs" class="nav-link <?= in_array(basename($_SERVER['PHP_SELF']), ['faqs.php', 'faq-add.php', 'faq-edit.php', 'faq-categories.php', 'faq-category-add.php', 'faq-category-edit.php']) ? 'active' : '' ?>"><span class="material-symbols-outlined">quiz</span> FAQ</a></li>
                    <li>
                        <a href="chats" class="nav-link <?= in_array(basename($_SERVER['PHP_SELF']), ['chats.php']) ? 'active' : '' ?>" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                            <span style="display: inline-flex; align-items: center; gap: 8px;">
                                <span class="material-symbols-outlined">chat</span> Live Chat
                            </span>
                            <span class="chat-badge" style="background-color: var(--admin-danger, #ba1a1a); color: white; border-radius: 10px; padding: 2px 8px; font-size: 10px; font-weight: bold; min-width: 18px; text-align: center; display: <?= $unreadChatCount > 0 ? 'inline-block' : 'none' ?>;"><?= $unreadChatCount ?></span>
                        </a>
                    </li>
                    <li><a href="analytics" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'analytics.php' ? 'active' : '' ?>"><span class="material-symbols-outlined">bar_chart</span> Analitik</a></li>
                    <li><a href="settings" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : '' ?>"><span class="material-symbols-outlined">settings</span> Pengaturan</a></li>
                    <li><a href="system-tester" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'system-tester.php' ? 'active' : '' ?>"><span class="material-symbols-outlined">fact_check</span> System Tester</a></li>
                    <li><a href="logout" class="nav-link nav-logout"><span class="material-symbols-outlined">logout</span> Keluar</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content Area -->
        <main class="admin-main">
            <!-- Top bar with mobile menu toggle -->
            <header class="admin-topbar">
                <button class="sidebar-toggle" id="sidebarToggle" onclick="toggleSidebar()" aria-label="Toggle navigation">
                    <span class="material-symbols-outlined">menu</span>
                </button>
                <h1 class="page-title"><?= isset($pageTitle) ? sanitizeOutput($pageTitle) : 'Admin' ?></h1>
                <div class="topbar-actions">
                    <button class="theme-toggle-btn" id="themeToggleBtn" onclick="toggleTheme()" title="Ganti Tema">
                        <span class="material-symbols-outlined" id="theme-icon">dark_mode</span>
                    </button>
                    <?php if ($adminData): ?>
                    <span class="topbar-user"><?= sanitizeOutput($adminData['name']) ?></span>
                    <?php endif; ?>
                </div>
            </header>

            <!-- Flash Message Display -->
            <?php if ($flashMessage): ?>
            <div class="flash-message flash-<?= sanitizeOutput($flashMessage['type']) ?>">
                <?= sanitizeOutput($flashMessage['message']) ?>
            </div>
            <?php endif; ?>

            <!-- Content area starts here (closed by admin-footer.php) -->
            <div class="admin-content">
