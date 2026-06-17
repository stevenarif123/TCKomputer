<?php
/**
 * Buyer Header Include
 * Premium Glassmorphism Navigation, Tailwind CSS config, mobile-first meta.
 */

// Security hardening — must run before session_start and before any output
require_once __DIR__ . '/../config/security.php';
configureSecureSession();   // Set HttpOnly, SameSite=Lax (+ Secure on HTTPS) on session cookie
applySecurityHeaders();     // Emit X-Frame-Options, CSP, etc.

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include configuration files
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/analytics.php';

// Fetch store settings
$pdo = getDBConnection();
$stmtSettings = $pdo->query("SELECT * FROM store_settings LIMIT 1");
$storeSettings = $stmtSettings->fetch();

// Record storefront visit (best-effort, never throws, deduped per page per session)
recordVisit($pdo, [
    'session_id' => session_id(),
    'ip'         => $_SERVER['REMOTE_ADDR']     ?? '',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
    'page_url'   => $_SERVER['REQUEST_URI']     ?? '/',
    'referrer'   => $_SERVER['HTTP_REFERER']    ?? null,
]);

$storeName = $storeSettings['store_name'] ?? 'TC Komputer';
$storeLogo = $storeSettings['logo'] ?? null;

// Global Flash Sale State Calculation
$fsSeconds = 0;
$flashSaleEnd = $storeSettings['flash_sale_end'] ?? '';
if (!empty($flashSaleEnd)) {
    $endTime = strtotime($flashSaleEnd);
    $currentTime = time();
    if ($endTime > $currentTime) {
        $fsSeconds = $endTime - $currentTime;
    }
}
$isGlobalFlashSaleActive = !empty($storeSettings['flash_sale_active']) && $fsSeconds > 0;

// Cart count from session
$cartCount = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cartCount += (int)($item['quantity'] ?? 0);
    }
}

$profile = isset($_SESSION['customer_profile']) && is_array($_SESSION['customer_profile']) ? $_SESSION['customer_profile'] : null;
if ($profile) {
    if (!isset($profile['shipping_area_id']) || empty($profile['username'])) {
        try {
            $stmtUser = $pdo->prepare("SELECT * FROM users WHERE id = ? OR phone = ?");
            $stmtUser->execute([$_SESSION['customer_id'] ?? 0, $profile['phone']]);
            $dbUser = $stmtUser->fetch();
            if ($dbUser) {
                $_SESSION['customer_id'] = $dbUser['id'];
                $_SESSION['customer_profile'] = [
                    'id' => $dbUser['id'],
                    'username' => $dbUser['username'],
                    'name' => $dbUser['name'],
                    'email' => $dbUser['email'],
                    'phone' => $dbUser['phone'],
                    'address' => $dbUser['address'],
                    'shipping_area_id' => $dbUser['shipping_area_id']
                ];
                $profile = $_SESSION['customer_profile'];
            }
        } catch (Exception $e) {
            // Silently ignore database or missing table errors
        }
    }
}

// Fetch active shipping areas for registration/profile edit
try {
    $stmtAreasHeader = $pdo->query("SELECT * FROM shipping_areas WHERE is_active = 1 ORDER BY area_name");
    $shippingAreasHeader = $stmtAreasHeader->fetchAll();
} catch (Exception $e) {
    $shippingAreasHeader = [];
}

$userRegency = '';
if ($profile && !empty($profile['shipping_area_id'])) {
    foreach ($shippingAreasHeader as $area) {
        if ($area['id'] == $profile['shipping_area_id']) {
            $userRegency = $area['regency'];
            break;
        }
    }
}


// Wishlist default initialization
if (!isset($_SESSION['wishlist'])) {
    $_SESSION['wishlist'] = [];
}

// Notifications default initialization
if (!isset($_SESSION['notifications'])) {
    $_SESSION['notifications'] = [];
}

$unreadNotificationCount = 0;
if (is_array($_SESSION['notifications'])) {
    foreach ($_SESSION['notifications'] as $notif) {
        if (isset($notif['unread']) && $notif['unread']) {
            $unreadNotificationCount++;
        }
    }
}

// Fetch wishlist products details from database
$wishlistProducts = [];
if (!empty($_SESSION['wishlist'])) {
    try {
        $inClause = implode(',', array_fill(0, count($_SESSION['wishlist']), '?'));
        $stmtWishlist = $pdo->prepare("SELECT id, name, slug, selling_price, image, status FROM products WHERE id IN ($inClause) AND is_active = 1");
        $stmtWishlist->execute($_SESSION['wishlist']);
        $wishlistProducts = $stmtWishlist->fetchAll();
    } catch (Exception $e) {
        $wishlistProducts = [];
    }
}

// Get flash message (single-use: retrieved then cleared)
$flashMessage = getFlashMessage();

// Active nav link helper
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title><?= sanitizeOutput($storeName) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            transition: transform 0.2s ease, font-variation-settings 0.2s ease;
        }
        
        /* Premium custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #eff4ff;
        }
        ::-webkit-scrollbar-thumb {
            background: #adc6ff;
            border-radius: 9999px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #0058be;
        }

        .hide-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .hide-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        
        /* Flat Header styling */
        .glass-header {
            background: #ffffff;
            border-bottom: 1px solid #e5e7eb;
        }

        /* Animations */
        @keyframes fade-in-up {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up {
            animation: fade-in-up 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        /* Flat Card styling */
        .tech-card {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #ffffff;
            transition: border-color 0.15s ease;
        }
        .tech-card:hover {
            border-color: #0058be;
        }

        /* Active Navigation Line */
        .nav-link {
            position: relative;
        }
        .nav-link::after {
            content: '';
            position: absolute;
            bottom: -6px;
            left: 0;
            width: 0;
            height: 2px;
            background-color: #0058be;
            transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .nav-link:hover::after, .nav-link.active::after {
            width: 100%;
        }

        /* Heart Wishlist Animation */
        .wishlist-btn:hover span {
            font-variation-settings: 'FILL' 1, 'wght' 400;
            color: #ba1a1a;
            transform: scale(1.15);
        }
        .wishlist-btn.active span {
            font-variation-settings: 'FILL' 1, 'wght' 400;
            color: #ba1a1a;
        }

        /* Toast Message styling */
        .toast-notification {
            transform: translateY(-120px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            pointer-events: none;
        }
        .toast-notification.show {
            transform: translateY(0);
            opacity: 1;
            pointer-events: auto;
        }
    </style>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "on-primary-container": "#7c839b",
                        "background": "#f9fafb",
                        "on-error": "#ffffff",
                        "on-primary-fixed": "#131b2e",
                        "on-tertiary-container": "#009844",
                        "inverse-primary": "#bec6e0",
                        "on-error-container": "#93000a",
                        "outline-variant": "#e5e7eb",
                        "on-secondary-fixed-variant": "#004395",
                        "inverse-on-surface": "#eaf1ff",
                        "tertiary-fixed-dim": "#4ae176",
                        "on-background": "#0b1c30",
                        "surface": "#ffffff",
                        "surface-bright": "#ffffff",
                        "on-primary": "#ffffff",
                        "secondary-container": "#2170e4",
                        "primary": "#000000",
                        "error-container": "#ffdad6",
                        "on-secondary-container": "#fefcff",
                        "secondary": "#0058be",
                        "on-surface": "#0b1c30",
                        "on-tertiary-fixed": "#002109",
                        "on-surface-variant": "#45464d",
                        "on-tertiary": "#ffffff",
                        "surface-container-low": "#f3f4f6",
                        "primary-fixed": "#dae2fd",
                        "secondary-fixed-dim": "#adc6ff",
                        "surface-container-lowest": "#ffffff",
                        "on-secondary": "#ffffff",
                        "error": "#ba1a1a",
                        "surface-tint": "#565e74",
                        "surface-variant": "#f3f4f6",
                        "primary-container": "#131b2e",
                        "on-tertiary-fixed-variant": "#005321",
                        "surface-container-high": "#e5e7eb",
                        "on-secondary-fixed": "#001a42",
                        "on-primary-fixed-variant": "#3f465c",
                        "primary-fixed-dim": "#bec6e0",
                        "surface-dim": "#f3f4f6",
                        "secondary-fixed": "#d8e2ff",
                        "surface-container": "#f3f4f6",
                        "tertiary-fixed": "#6bff8f",
                        "inverse-surface": "#213145",
                        "surface-container-highest": "#d1d5db",
                        "tertiary-container": "#002109",
                        "outline": "#76777d",
                        "tertiary": "#000000"
                    },
                    borderRadius: {
                        "DEFAULT": "0.25rem",
                        "lg": "0.5rem",
                        "xl": "0.75rem",
                        "full": "9999px"
                    },
                    spacing: {
                        "margin-desktop": "32px",
                        "xs": "6px",
                        "sm": "12px",
                        "max-width": "1280px",
                        "gutter": "16px",
                        "base": "4px",
                        "md": "16px",
                        "margin-mobile": "12px",
                        "xl": "32px",
                        "lg": "20px"
                    },
                    fontFamily: {
                        "headline-lg-mobile": ["Inter"],
                        "body-lg": ["Inter"],
                        "body-md": ["Inter"],
                        "label-sm": ["Inter"],
                        "headline-xl": ["Inter"],
                        "body-sm": ["Inter"],
                        "label-md": ["Inter"],
                        "headline-lg": ["Inter"],
                        "headline-md": ["Inter"]
                    },
                    fontSize: {
                        "headline-lg-mobile": ["24px", {"lineHeight": "32px", "fontWeight": "600"}],
                        "body-lg": ["18px", {"lineHeight": "28px", "fontWeight": "400"}],
                        "body-md": ["16px", {"lineHeight": "24px", "fontWeight": "400"}],
                        "label-sm": ["12px", {"lineHeight": "16px", "fontWeight": "500"}],
                        "headline-xl": ["48px", {"lineHeight": "56px", "letterSpacing": "-0.02em", "fontWeight": "700"}],
                        "body-sm": ["14px", {"lineHeight": "20px", "fontWeight": "400"}],
                        "label-md": ["14px", {"lineHeight": "16px", "letterSpacing": "0.01em", "fontWeight": "600"}],
                        "headline-lg": ["32px", {"lineHeight": "40px", "letterSpacing": "-0.01em", "fontWeight": "600"}],
                        "headline-md": ["20px", {"lineHeight": "28px", "fontWeight": "600"}]
                    }
                },
            },
        }
    </script>
</head>
<body class="bg-surface-container-low text-on-surface font-body-md overflow-x-hidden min-h-screen flex flex-col">
<header class="sticky top-0 w-full z-50 glass-header scrolled">
    <!-- Top Bar -->
    <nav class="flex items-center justify-between px-3 md:px-margin-desktop h-14 md:h-20 max-w-max-width mx-auto" id="main-nav">
        <div class="flex items-center gap-6 lg:gap-xl">
            <a class="hover:opacity-90 transition-opacity flex items-center gap-2 select-none" href="index">
                <?php if ($storeLogo): ?>
                    <img src="uploads/logo/<?= sanitizeOutput($storeLogo) ?>" alt="<?= sanitizeOutput($storeName) ?>" class="h-8 w-auto object-contain">
                <?php else: ?>
                    <div class="flex items-center justify-center bg-secondary/5 p-2 rounded-lg border border-secondary/15">
                        <span class="material-symbols-outlined text-secondary text-2xl" style="font-variation-settings: 'FILL' 1, 'wght' 600;">devices</span>
                    </div>
                    <div class="flex items-center font-black tracking-tighter text-base md:text-3xl whitespace-nowrap">
                        <span class="text-secondary font-black">TC</span>
                        <span class="text-on-background ml-1 font-medium font-sans">Komputer</span>
                    </div>
                <?php endif; ?>
            </a>
            <div class="hidden lg:flex items-center gap-lg">
                <a class="<?= ($current_page === 'index.php') ? 'text-secondary font-bold active' : 'text-on-surface-variant font-medium' ?> pb-1 text-label-md font-label-md nav-link transition-all duration-200" href="index">Beranda</a>
                <a class="<?= ($current_page === 'products.php' || $current_page === 'product-detail.php') ? 'text-secondary font-bold active' : 'text-on-surface-variant font-medium' ?> pb-1 text-label-md font-label-md nav-link transition-all duration-200" href="products">Produk</a>
                <a class="<?= ($current_page === 'categories.php' || $current_page === 'category.php') ? 'text-secondary font-bold active' : 'text-on-surface-variant font-medium' ?> pb-1 text-label-md font-label-md nav-link transition-all duration-200" href="categories">Kategori</a>
            </div>
        </div>
        
        <!-- Search bar -->
        <form action="products" method="GET" class="flex-grow max-w-xs md:max-w-md px-4 hidden md:block">
            <div class="relative">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-xl">search</span>
                <input name="search" class="bg-surface-container-low border border-outline-variant/60 rounded-lg pl-10 pr-4 py-2 text-body-sm w-full focus:border-secondary focus:bg-white transition-colors outline-none" placeholder="Cari hardware..." type="text" value="<?= sanitizeOutput($_GET['search'] ?? '') ?>"/>
            </div>
        </form>

        <div class="flex items-center gap-xs sm:gap-sm">
            <!-- Mobile Menu Toggle Button -->
            <button class="p-2 hover:bg-surface-container/50 rounded-full transition-all lg:hidden" title="Menu" onclick="toggleMobileMenu()">
                <span class="material-symbols-outlined text-on-surface-variant" id="mobile-menu-icon">menu</span>
            </button>

            <!-- Notifikasi Dropdown Container -->
            <div class="relative hidden sm:block" id="notif-dropdown-wrapper">
                <button class="p-2 hover:bg-surface-container/50 rounded-full transition-all relative group" title="Notifikasi" onclick="toggleNotifDropdown(event)">
                    <span class="material-symbols-outlined text-on-surface-variant group-hover:text-secondary">notifications</span>
                    <?php if ($unreadNotificationCount > 0): ?>
                        <span id="notif-badge-ping" class="absolute top-1.5 right-1.5 w-2.5 h-2.5 bg-error border-2 border-white rounded-full animate-ping"></span>
                        <span id="notif-badge" class="absolute top-1.5 right-1.5 w-2.5 h-2.5 bg-error border-2 border-white rounded-full"></span>
                    <?php endif; ?>
                </button>
                <!-- Notification Dropdown Menu -->
                <div id="notif-dropdown" class="hidden absolute right-0 mt-3 w-80 bg-white border border-outline-variant/50 rounded-lg shadow-sm z-50 overflow-hidden animate-fade-in-up">
                    <div class="p-4 border-b border-outline-variant/30 flex justify-between items-center bg-surface-container-lowest">
                        <span class="text-body-sm font-bold text-on-surface">Notifikasi</span>
                        <button onclick="markAllNotificationsAsRead(event)" class="text-[11px] text-secondary hover:underline font-bold">Tandai semua dibaca</button>
                    </div>
                    <div class="max-h-72 overflow-y-auto divide-y divide-outline-variant/20 hide-scrollbar" id="notif-items-list">
                        <?php if (!empty($_SESSION['notifications'])): ?>
                            <?php foreach (array_reverse($_SESSION['notifications']) as $notif): ?>
                                <div class="p-3 hover:bg-surface-container-low transition-colors <?= ($notif['unread'] ?? false) ? 'bg-secondary/5' : '' ?>">
                                    <div class="flex justify-between items-start mb-0.5">
                                        <h5 class="text-[12px] font-bold text-on-surface"><?= sanitizeOutput($notif['title']) ?></h5>
                                        <span class="text-[9px] text-on-surface-variant/70 font-medium"><?= sanitizeOutput($notif['time']) ?></span>
                                    </div>
                                    <p class="text-[11px] text-on-surface-variant leading-relaxed"><?= sanitizeOutput($notif['message']) ?></p>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="p-6 text-center text-on-surface-variant/60 text-[11px] space-y-1">
                                <span class="material-symbols-outlined text-3xl opacity-40">notifications_off</span>
                                <p>Tidak ada notifikasi baru</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Shopping Cart -->
            <button class="p-2 hover:bg-surface-container/50 rounded-full transition-all relative group" title="Keranjang" onclick="window.location.href='cart'">
                <span class="material-symbols-outlined text-on-surface-variant group-hover:text-secondary">shopping_cart</span>
                <?php if ($cartCount > 0): ?>
                    <span class="absolute -top-0.5 -right-0.5 bg-secondary text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full shadow-md group-hover:scale-110 transition-transform"><?= (int)$cartCount ?></span>
                <?php endif; ?>
            </button>

            <div class="w-px h-6 bg-outline-variant/60 mx-1 hidden sm:block"></div>

            <?php if ($profile): ?>
            <!-- Profil Dropdown Container -->
            <div class="relative hidden sm:block" id="profile-dropdown-wrapper">
                <button class="flex items-center gap-xs p-1.5 hover:bg-surface-container/50 rounded-lg transition-all" onclick="toggleProfileDropdown(event)">
                    <div class="w-7 h-7 rounded-full bg-secondary/10 flex items-center justify-center border border-secondary/20">
                        <span class="material-symbols-outlined text-secondary text-lg" style="font-variation-settings: 'FILL' 1;">account_circle</span>
                    </div>
                    <span id="header-profile-name" class="text-body-sm font-semibold text-on-surface hidden xl:block"><?= sanitizeOutput($profile['name']) ?></span>
                </button>
                <!-- Profile Dropdown Menu -->
                <div id="profile-dropdown" class="hidden absolute right-0 mt-3 w-72 bg-white border border-outline-variant/50 rounded-lg border border-outline-variant/50 shadow-sm z-50 overflow-hidden animate-fade-in-up">
                    <div class="p-4 border-b border-outline-variant/30 flex items-center gap-sm bg-surface-container-lowest">
                        <div class="w-10 h-10 rounded-full bg-secondary/10 flex items-center justify-center border border-secondary/20">
                            <span class="material-symbols-outlined text-secondary text-2xl" style="font-variation-settings: 'FILL' 1;">account_circle</span>
                        </div>
                        <div class="min-w-0">
                            <h5 id="dropdown-profile-name" class="text-body-sm font-bold text-on-surface truncate"><?= sanitizeOutput($profile['name']) ?></h5>
                            <p id="dropdown-profile-email" class="text-[10px] text-on-surface-variant truncate"><?= sanitizeOutput($profile['email'] ?: 'Profil belum diatur') ?></p>
                        </div>
                    </div>
                    <div class="p-2 space-y-0.5">
                        <button onclick="openProfileModal(event)" class="w-full text-left px-3 py-2 rounded-lg text-body-sm hover:bg-surface-container-low transition-colors flex items-center gap-2 text-on-surface">
                            <span class="material-symbols-outlined text-[18px] text-on-surface-variant">person</span>
                            <span>Profil Saya</span>
                        </button>
                        <button onclick="openWishlistDrawer(event)" class="w-full text-left px-3 py-2 rounded-lg text-body-sm hover:bg-surface-container-low transition-colors flex items-center gap-2 text-on-surface">
                            <span class="material-symbols-outlined text-[18px] text-on-surface-variant">favorite</span>
                            <span>Wishlist Favorit (<span id="wishlist-count-badge"><?= count($_SESSION['wishlist']) ?></span>)</span>
                        </button>
                        <a href="my-orders" class="w-full text-left px-3 py-2 rounded-lg text-body-sm hover:bg-surface-container-low transition-colors flex items-center gap-2 text-on-surface">
                            <span class="material-symbols-outlined text-[18px] text-on-surface-variant">receipt_long</span>
                            <span>Pesanan Saya</span>
                        </a>
                        <hr class="border-outline-variant/30 my-1">
                        <a href="actions/profile-logout" class="w-full text-left px-3 py-2 rounded-lg text-body-sm hover:bg-error/10 hover:text-error transition-colors flex items-center gap-2 text-on-surface">
                            <span class="material-symbols-outlined text-[18px] text-error">logout</span>
                            <span>Keluar</span>
                        </a>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <!-- Login Button -->
            <button onclick="openProfileModal(event)" class="px-4 py-2 bg-secondary text-white font-bold text-body-sm rounded-lg hover:bg-secondary-container transition-all">
                Masuk
            </button>
            <?php endif; ?>
        </div>
    </nav>

    <!-- Mobile Navigation Drawer -->
    <div id="mobile-menu" class="hidden border-t border-outline-variant/40 bg-white/95 backdrop-blur-md px-4 py-4 space-y-3 lg:hidden transition-all duration-300">
        <form action="products" method="GET" class="relative pb-2">
            <span class="material-symbols-outlined absolute left-3 top-2.5 text-on-surface-variant text-xl">search</span>
            <input name="search" class="bg-surface-container-low border border-outline-variant/60 rounded-lg pl-10 pr-4 py-2 text-body-sm w-full outline-none" placeholder="Cari hardware..." type="text" value="<?= sanitizeOutput($_GET['search'] ?? '') ?>"/>
        </form>
        <a class="block py-2 px-3 rounded-lg <?= ($current_page === 'index.php') ? 'bg-secondary/10 text-secondary font-bold' : 'text-on-surface-variant font-medium' ?>" href="index">Beranda</a>
        <a class="block py-2 px-3 rounded-lg <?= ($current_page === 'products.php' || $current_page === 'product-detail.php') ? 'bg-secondary/10 text-secondary font-bold' : 'text-on-surface-variant font-medium' ?>" href="products">Produk</a>
        <a class="block py-2 px-3 rounded-lg <?= ($current_page === 'categories.php' || $current_page === 'category.php') ? 'bg-secondary/10 text-secondary font-bold' : 'text-on-surface-variant font-medium' ?>" href="categories">Kategori</a>
        
        <hr class="border-outline-variant/30 my-1">
        <?php if ($profile || !empty($_SESSION['my_orders'])): ?>
            <a class="block py-2 px-3 rounded-lg text-on-surface-variant font-medium" href="my-orders">Pesanan Saya</a>
        <?php endif; ?>
        <?php if ($profile): ?>
            <a class="block py-2 px-3 rounded-lg text-on-surface-variant font-medium" href="#" onclick="openProfileModal(event)">Profil Saya</a>
            <a class="block py-2 px-3 rounded-lg text-error font-medium" href="actions/profile-logout">Keluar</a>
        <?php else: ?>
            <a class="block py-2 px-3 rounded-lg bg-secondary text-white font-bold text-center" href="#" onclick="openProfileModal(event)">Masuk</a>
        <?php endif; ?>
    </div>

    <!-- Secondary Category Nav (Only on index.php and products.php for quicker access) -->
    <?php if ($current_page === 'index.php' || $current_page === 'products.php'): ?>
        <?php
        // Fetch some categories dynamically for this subnav
        $stmtSubnav = $pdo->query("SELECT name, slug FROM categories WHERE is_active = 1 ORDER BY sort_order ASC LIMIT 8");
        $subnavCats = $stmtSubnav->fetchAll();
        ?>
        <?php if (!empty($subnavCats)): ?>
            <div class="bg-white/60 border-t border-outline-variant/40 hidden md:block">
                <div class="max-w-max-width mx-auto px-4 md:px-margin-desktop flex items-center gap-lg h-10 overflow-x-auto hide-scrollbar">
                    <?php foreach ($subnavCats as $subCat): ?>
                        <a class="text-body-sm text-on-surface-variant whitespace-nowrap hover:text-secondary font-medium transition-colors" href="category?slug=<?= sanitizeOutput($subCat['slug']) ?>"><?= sanitizeOutput($subCat['name']) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</header>

<!-- Edit Profile Modal -->
<div id="profile-modal" class="hidden fixed inset-0 bg-primary-container/40 backdrop-blur-sm z-[100] flex items-center justify-center p-4">
    <div class="bg-white border border-outline-variant/60 w-full max-w-md rounded-lg border border-outline-variant/50 shadow-sm overflow-hidden animate-fade-in-up">
        <div class="p-4 border-b border-outline-variant/30 flex justify-between items-center bg-surface-container-lowest">
            <span class="text-body-md font-extrabold text-on-surface"><?= $profile ? 'Edit Profil Saya' : 'Masuk / Daftar Akun' ?></span>
            <button onclick="closeProfileModal(event)" class="text-on-surface-variant hover:text-secondary font-bold text-xl leading-none" aria-label="Close">&times;</button>
        </div>
        
        <?php if (!$profile): ?>
        <!-- Tab Headers -->
        <div class="flex border-b border-outline-variant/30 bg-surface-container-lowest">
            <button type="button" onclick="switchLoginTab('login')" id="tab-btn-login" class="flex-1 py-3 text-center text-[12px] font-bold border-b-2 border-secondary text-secondary transition-all">Masuk</button>
            <button type="button" onclick="switchLoginTab('register')" id="tab-btn-register" class="flex-1 py-3 text-center text-[12px] font-medium border-b-2 border-transparent text-on-surface-variant hover:text-secondary transition-all">Daftar Akun</button>
        </div>
        
        <!-- Form 1: Username/Phone Login with Password -->
        <form id="login-form" onsubmit="loginUser(event)" class="p-5 space-y-sm">
            <div class="space-y-1">
                <label for="login_identifier" class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider block">Username / Nomor Telepon <span class="text-error">*</span></label>
                <input type="text" id="login_identifier" name="login_identifier" placeholder="Contoh: steven atau 082293924242" class="w-full px-3 py-2 border border-outline-variant/80 rounded-lg text-body-sm focus:border-secondary outline-none bg-surface-container-lowest" required>
            </div>
            <div class="space-y-1">
                <label for="login_password" class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider block">Kata Sandi <span class="text-error">*</span></label>
                <input type="password" id="login_password" name="password" placeholder="Masukkan kata sandi Anda" class="w-full px-3 py-2 border border-outline-variant/80 rounded-lg text-body-sm focus:border-secondary outline-none bg-surface-container-lowest" required>
            </div>
            <div class="pt-2 flex justify-end gap-sm">
                <button type="button" onclick="closeProfileModal(event)" class="px-md py-2 border border-outline-variant/80 text-body-sm font-semibold rounded-lg hover:bg-surface-container-low transition-colors">Batal</button>
                <button type="submit" class="px-md py-2 bg-secondary text-white text-body-sm font-bold rounded-lg hover:bg-secondary-container transition-all">Masuk</button>
            </div>
        </form>

        <!-- Form 2: Register New Account -->
        <form id="register-form" onsubmit="registerUser(event)" class="p-5 space-y-sm hidden max-h-[70vh] overflow-y-auto hide-scrollbar">
            <div class="space-y-1">
                <label for="register_username" class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider block">Username <span class="text-error">*</span></label>
                <input type="text" id="register_username" name="username" placeholder="Contoh: steven_lisu" class="w-full px-3 py-2 border border-outline-variant/80 rounded-lg text-body-sm focus:border-secondary outline-none bg-surface-container-lowest" required minlength="3" maxlength="30">
                <small class="text-[9px] text-on-surface-variant/70 block">Hanya huruf, angka, dan garis bawah (_). Panjang 3-30 karakter.</small>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-sm">
                <div class="space-y-1">
                    <label for="register_password" class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider block">Kata Sandi <span class="text-error">*</span></label>
                    <input type="password" id="register_password" name="password" placeholder="Min. 6 karakter" class="w-full px-3 py-2 border border-outline-variant/80 rounded-lg text-body-sm focus:border-secondary outline-none bg-surface-container-lowest" required minlength="6">
                </div>
                <div class="space-y-1">
                    <label for="register_password_confirm" class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider block">Konfirmasi Sandi <span class="text-error">*</span></label>
                    <input type="password" id="register_password_confirm" name="password_confirm" placeholder="Ulangi kata sandi" class="w-full px-3 py-2 border border-outline-variant/80 rounded-lg text-body-sm focus:border-secondary outline-none bg-surface-container-lowest" required>
                </div>
            </div>
            <div class="space-y-1">
                <label for="register_name" class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider block">Nama Lengkap <span class="text-error">*</span></label>
                <input type="text" id="register_name" name="name" placeholder="Nama lengkap Anda" class="w-full px-3 py-2 border border-outline-variant/80 rounded-lg text-body-sm focus:border-secondary outline-none bg-surface-container-lowest" required minlength="3" maxlength="100">
            </div>
            <div class="space-y-1">
                <label for="register_email" class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider block">Email <span class="text-error">*</span></label>
                <input type="email" id="register_email" name="email" placeholder="Contoh: user@domain.com" class="w-full px-3 py-2 border border-outline-variant/80 rounded-lg text-body-sm focus:border-secondary outline-none bg-surface-container-lowest" required>
            </div>
            <div class="space-y-1">
                <label for="register_phone" class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider block">Nomor Telepon <span class="text-error">*</span></label>
                <input type="tel" id="register_phone" name="phone" placeholder="Contoh: 081234567890" class="w-full px-3 py-2 border border-outline-variant/80 rounded-lg text-body-sm focus:border-secondary outline-none bg-surface-container-lowest" required>
            </div>
            <div class="space-y-1">
                <label for="register_regency" class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider block">Kabupaten/Kota <span class="text-error">*</span></label>
                <select id="register_regency" onchange="updateRegisterKecamatan()" class="w-full px-3 py-2 border border-outline-variant/80 rounded-lg text-body-sm focus:border-secondary outline-none bg-surface-container-lowest" required>
                    <option value="">-- Pilih Kabupaten/Kota --</option>
                    <option value="Tana Toraja">Tana Toraja</option>
                    <option value="Toraja Utara">Toraja Utara</option>
                </select>
            </div>
            <div class="space-y-1 relative" id="register-kecamatan-container">
                <label class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider block">Kecamatan <span class="text-error">*</span></label>
                <input type="hidden" id="register_shipping_area_id" name="shipping_area_id">
                
                <!-- Custom Trigger Button -->
                <button type="button" id="register-kecamatan-trigger" onclick="toggleKecamatanDropdown('register')" class="w-full px-3 py-2 border border-outline-variant/80 rounded-lg text-body-sm text-left flex justify-between items-center outline-none bg-surface-container-lowest hover:border-secondary transition-colors disabled:opacity-60 disabled:cursor-not-allowed disabled:bg-surface-container-low" disabled>
                    <span id="register-kecamatan-label" class="text-on-surface-variant/60">Pilih Kecamatan...</span>
                    <span id="register-kecamatan-arrow" class="material-symbols-outlined text-[18px] text-on-surface-variant transition-transform duration-200">keyboard_arrow_down</span>
                </button>
                
                <!-- Dropdown List Panel -->
                <div id="register-kecamatan-panel" class="hidden absolute top-full left-0 right-0 mt-1 bg-white border border-outline-variant/80 rounded-lg shadow-lg z-[210] p-2 flex flex-col gap-2 max-h-60 animate-fade-in-up">
                    <!-- Search Input -->
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-[16px] text-on-surface-variant/60">search</span>
                        <input type="text" id="register-kecamatan-search" oninput="filterKecamatan('register')" placeholder="Cari kecamatan..." class="w-full pl-8 pr-3 py-1.5 border border-outline-variant/80 rounded-md text-body-sm focus:border-secondary outline-none bg-surface-container-lowest">
                    </div>
                    
                    <!-- Options List -->
                    <div id="register-kecamatan-list" class="flex-grow overflow-y-auto max-h-40 hide-scrollbar flex flex-col gap-0.5">
                        <!-- Populated by JS -->
                    </div>
                </div>
            </div>
            <div class="space-y-1">
                <label for="register_address" class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider block">Alamat Lengkap <span class="text-error">*</span></label>
                <textarea id="register_address" name="address" placeholder="Pilih Area Pengiriman/Kecamatan terlebih dahulu..." class="w-full px-3 py-2 border border-outline-variant/80 rounded-lg text-body-sm focus:border-secondary outline-none bg-surface-container-lowest" rows="3" required minlength="10" maxlength="500"></textarea>
            </div>
            <div class="pt-2 flex justify-end gap-sm">
                <button type="button" onclick="closeProfileModal(event)" class="px-md py-2 border border-outline-variant/80 text-body-sm font-semibold rounded-lg hover:bg-surface-container-low transition-colors">Batal</button>
                <button type="submit" class="px-md py-2 bg-secondary text-white text-body-sm font-bold rounded-lg hover:bg-secondary-container transition-all">Daftar Akun</button>
            </div>
        </form>
        <?php endif; ?>

        <!-- Form 3: Edit Profile (When Logged In) -->
        <?php if ($profile): ?>
        <form id="profile-form" onsubmit="saveProfile(event)" class="p-5 space-y-sm max-h-[70vh] overflow-y-auto hide-scrollbar">
            <div class="space-y-1">
                <label for="modal_profile_username_readonly" class="text-[10px] font-bold text-on-surface-variant/60 uppercase tracking-wider block">Username (Tidak Bisa Diubah)</label>
                <input type="text" id="modal_profile_username_readonly" class="w-full px-3 py-2 border border-outline-variant/50 rounded-lg text-body-sm bg-surface-container-low/75 text-on-surface-variant/80 outline-none" value="<?= sanitizeOutput($profile['username'] ?? '') ?>" readonly>
            </div>
            <div class="space-y-1">
                <label for="modal_profile_name" class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider block">Nama Lengkap <span class="text-error">*</span></label>
                <input type="text" id="modal_profile_name" name="name" class="w-full px-3 py-2 border border-outline-variant/80 rounded-lg text-body-sm focus:border-secondary outline-none bg-surface-container-lowest" value="<?= sanitizeOutput($profile['name'] ?? '') ?>" required minlength="3" maxlength="100">
            </div>
            <div class="space-y-1">
                <label for="modal_profile_email" class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider block">Email <span class="text-error">*</span></label>
                <input type="email" id="modal_profile_email" name="email" class="w-full px-3 py-2 border border-outline-variant/80 rounded-lg text-body-sm focus:border-secondary outline-none bg-surface-container-lowest" value="<?= sanitizeOutput($profile['email'] ?? '') ?>" required>
            </div>
            <div class="space-y-1">
                <label for="modal_profile_phone" class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider block">Nomor Telepon <span class="text-error">*</span></label>
                <input type="tel" id="modal_profile_phone" name="phone" class="w-full px-3 py-2 border border-outline-variant/80 rounded-lg text-body-sm focus:border-secondary outline-none bg-surface-container-lowest" value="<?= sanitizeOutput($profile['phone'] ?? '') ?>" required>
            </div>
            <div class="space-y-1">
                <label for="modal_profile_regency" class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider block">Kabupaten/Kota <span class="text-error">*</span></label>
                <select id="modal_profile_regency" onchange="updateProfileKecamatan()" class="w-full px-3 py-2 border border-outline-variant/80 rounded-lg text-body-sm focus:border-secondary outline-none bg-surface-container-lowest" required>
                    <option value="">-- Pilih Kabupaten/Kota --</option>
                    <option value="Tana Toraja" <?= $userRegency === 'Tana Toraja' ? 'selected' : '' ?>>Tana Toraja</option>
                    <option value="Toraja Utara" <?= $userRegency === 'Toraja Utara' ? 'selected' : '' ?>>Toraja Utara</option>
                </select>
            </div>
            <div class="space-y-1 relative" id="profile-kecamatan-container">
                <label class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider block">Kecamatan <span class="text-error">*</span></label>
                <input type="hidden" id="profile_shipping_area_id" name="shipping_area_id">
                
                <!-- Custom Trigger Button -->
                <button type="button" id="profile-kecamatan-trigger" onclick="toggleKecamatanDropdown('profile')" class="w-full px-3 py-2 border border-outline-variant/80 rounded-lg text-body-sm text-left flex justify-between items-center outline-none bg-surface-container-lowest hover:border-secondary transition-colors disabled:opacity-60 disabled:cursor-not-allowed disabled:bg-surface-container-low" disabled>
                    <span id="profile-kecamatan-label" class="text-on-surface-variant/60">Pilih Kecamatan...</span>
                    <span id="profile-kecamatan-arrow" class="material-symbols-outlined text-[18px] text-on-surface-variant transition-transform duration-200">keyboard_arrow_down</span>
                </button>
                
                <!-- Dropdown List Panel -->
                <div id="profile-kecamatan-panel" class="hidden absolute top-full left-0 right-0 mt-1 bg-white border border-outline-variant/80 rounded-lg shadow-lg z-[210] p-2 flex flex-col gap-2 max-h-60 animate-fade-in-up">
                    <!-- Search Input -->
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-[16px] text-on-surface-variant/60">search</span>
                        <input type="text" id="profile-kecamatan-search" oninput="filterKecamatan('profile')" placeholder="Cari kecamatan..." class="w-full pl-8 pr-3 py-1.5 border border-outline-variant/80 rounded-md text-body-sm focus:border-secondary outline-none bg-surface-container-lowest">
                    </div>
                    
                    <!-- Options List -->
                    <div id="profile-kecamatan-list" class="flex-grow overflow-y-auto max-h-40 hide-scrollbar flex flex-col gap-0.5">
                        <!-- Populated by JS -->
                    </div>
                </div>
            </div>
            <div class="space-y-1">
                <label for="modal_profile_address" class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider block">Alamat Lengkap <span class="text-error">*</span></label>
                <textarea id="modal_profile_address" name="address" placeholder="Tulis alamat pengiriman default lengkap Anda (nama jalan, RT/RW, nomor rumah)..." class="w-full px-3 py-2 border border-outline-variant/80 rounded-lg text-body-sm focus:border-secondary outline-none bg-surface-container-lowest" rows="3" required minlength="10" maxlength="500"><?= sanitizeOutput($profile['address'] ?? '') ?></textarea>
            </div>
            
            <hr class="border-outline-variant/30 my-3">
            <div class="text-[10px] font-black text-secondary uppercase tracking-wider mb-2">Ubah Kata Sandi (Opsional)</div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-sm">
                <div class="space-y-1">
                    <label for="modal_profile_password" class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider block">Sandi Baru</label>
                    <input type="password" id="modal_profile_password" name="password" placeholder="Kosongkan jika tidak diubah" class="w-full px-3 py-2 border border-outline-variant/80 rounded-lg text-body-sm focus:border-secondary outline-none bg-surface-container-lowest" minlength="6">
                </div>
                <div class="space-y-1">
                    <label for="modal_profile_password_confirm" class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider block">Ulangi Sandi Baru</label>
                    <input type="password" id="modal_profile_password_confirm" name="password_confirm" placeholder="Kosongkan jika tidak diubah" class="w-full px-3 py-2 border border-outline-variant/80 rounded-lg text-body-sm focus:border-secondary outline-none bg-surface-container-lowest">
                </div>
            </div>
            
            <div class="pt-3 flex justify-end gap-sm">
                <button type="button" onclick="closeProfileModal(event)" class="px-md py-2 border border-outline-variant/80 text-body-sm font-semibold rounded-lg hover:bg-surface-container-low transition-colors">Batal</button>
                <button type="submit" class="px-md py-2 bg-secondary text-white text-body-sm font-bold rounded-lg hover:bg-secondary-container transition-all">Simpan Profil</button>
            </div>
        </form>
        <?php endif; ?></div>
</div>

<!-- Wishlist Side Drawer -->
<div id="wishlist-drawer" class="hidden fixed inset-y-0 right-0 w-full max-w-sm bg-white border-l border-outline-variant/30 shadow-sm z-[100] flex flex-col transform transition-transform duration-300">
    <div class="p-4 border-b border-outline-variant/30 flex justify-between items-center bg-surface-container-lowest">
        <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-secondary">favorite</span>
            <span class="text-body-md font-extrabold text-on-surface">Favorit Saya</span>
        </div>
        <button onclick="closeWishlistDrawer()" class="text-on-surface-variant hover:text-secondary font-bold text-xl leading-none">&times;</button>
    </div>
    <div class="flex-grow overflow-y-auto p-4 space-y-sm hide-scrollbar" id="wishlist-drawer-items">
        <?php if (!empty($wishlistProducts)): ?>
            <?php foreach ($wishlistProducts as $wProduct): ?>
                <?php 
                $wImg = !empty($wProduct['image']) ? 'uploads/products/' . $wProduct['image'] : 'uploads/products/placeholder.png';
                ?>
                <div class="flex items-center gap-sm p-2 bg-surface-container-lowest border border-outline-variant/40 rounded-lg relative group shadow-sm" id="wishlist-item-<?= (int)$wProduct['id'] ?>">
                    <div class="w-14 h-14 bg-surface-container rounded-lg overflow-hidden flex-shrink-0 flex items-center justify-center p-1 border border-outline-variant/25">
                        <img class="w-full h-full object-contain" src="<?= $wImg ?>" alt="<?= sanitizeOutput($wProduct['name']) ?>"/>
                    </div>
                    <div class="min-w-0 flex-grow">
                        <a href="product-detail?slug=<?= sanitizeOutput($wProduct['slug']) ?>" class="block text-[12px] font-bold text-on-surface hover:text-secondary truncate pr-4"><?= sanitizeOutput($wProduct['name']) ?></a>
                        <p class="text-[11px] text-secondary font-black mt-0.5"><?= formatRupiah((int)$wProduct['selling_price']) ?></p>
                    </div>
                    <button onclick="removeWishlistItem(<?= (int)$wProduct['id'] ?>, event)" class="absolute top-2 right-2 text-outline-variant hover:text-error transition-colors" title="Hapus dari favorit">
                        <span class="material-symbols-outlined text-sm">close</span>
                    </button>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="h-full flex flex-col items-center justify-center text-center p-6 text-on-surface-variant/60 text-[11px] space-y-md">
                <span class="material-symbols-outlined text-6xl opacity-35 text-on-surface-variant">favorite</span>
                <div>
                    <h4 class="font-bold text-on-surface text-body-sm">Belum Ada Favorit</h4>
                    <p class="mt-1">Sukai produk yang Anda minati di katalog untuk memantau harganya di sini.</p>
                </div>
                <a href="products.php" class="px-md py-2 bg-secondary text-white font-bold rounded-lg hover:bg-secondary-container transition-all">Lihat Produk</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Toggle mobile menu drawer
    function toggleMobileMenu() {
        const menu = document.getElementById('mobile-menu');
        const icon = document.getElementById('mobile-menu-icon');
        const isHidden = menu.classList.contains('hidden');
        if (isHidden) {
            menu.classList.remove('hidden');
            icon.textContent = 'close';
        } else {
            menu.classList.add('hidden');
            icon.textContent = 'menu';
        }
    }

    // Dropdowns toggling
    function toggleNotifDropdown(event) {
        event.stopPropagation();
        const dropdown = document.getElementById('notif-dropdown');
        const profileDropdown = document.getElementById('profile-dropdown');
        if (profileDropdown) profileDropdown.classList.add('hidden');
        dropdown.classList.toggle('hidden');

        // Mark as read when opening
        if (!dropdown.classList.contains('hidden')) {
            fetch('actions/notifications-read.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Hide badges
                    const badge = document.getElementById('notif-badge');
                    const badgePing = document.getElementById('notif-badge-ping');
                    if (badge) badge.remove();
                    if (badgePing) badgePing.remove();
                }
            });
        }
    }

    // Mark all notifications as read
    function markAllNotificationsAsRead(event) {
        if (event) event.stopPropagation();
        
        fetch('actions/notifications-read.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Hide badges
                const badge = document.getElementById('notif-badge');
                const badgePing = document.getElementById('notif-badge-ping');
                if (badge) badge.remove();
                if (badgePing) badgePing.remove();
                
                // Refresh list to remove unread backgrounds
                refreshNotificationsList();
                showToast("Notifikasi", "Semua notifikasi ditandai sebagai dibaca.");
            }
        })
        .catch(error => {
            console.error('Error marking notifications as read:', error);
        });
    }

    function toggleProfileDropdown(event) {
        event.stopPropagation();
        const dropdown = document.getElementById('profile-dropdown');
        const notifDropdown = document.getElementById('notif-dropdown');
        if (notifDropdown) notifDropdown.classList.add('hidden');
        dropdown.classList.toggle('hidden');
    }

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(event) {
        const notifDropdown = document.getElementById('notif-dropdown');
        const profileDropdown = document.getElementById('profile-dropdown');
        const notifWrapper = document.getElementById('notif-dropdown-wrapper');
        const profileWrapper = document.getElementById('profile-dropdown-wrapper');
        const wishlistDrawer = document.getElementById('wishlist-drawer');

        if (notifDropdown && !notifDropdown.classList.contains('hidden') && notifWrapper && !notifWrapper.contains(event.target)) {
            notifDropdown.classList.add('hidden');
        }
        if (profileDropdown && !profileDropdown.classList.contains('hidden') && profileWrapper && !profileWrapper.contains(event.target)) {
            profileDropdown.classList.add('hidden');
        }
        if (wishlistDrawer && !wishlistDrawer.classList.contains('hidden') && !wishlistDrawer.contains(event.target) && event.target.closest('button[onclick*="openWishlistDrawer"]') === null && event.target.closest('button[onclick*="toggleWishlist"]') === null) {
            wishlistDrawer.classList.add('hidden');
        }
    });

    // Profile Modal Actions
    function openProfileModal(event) {
        if (event) event.stopPropagation();
        const dropdown = document.getElementById('profile-dropdown');
        if (dropdown) dropdown.classList.add('hidden');
        document.getElementById('profile-modal').classList.remove('hidden');
    }

    function closeProfileModal(event) {
        if (event) event.stopPropagation();
        document.getElementById('profile-modal').classList.add('hidden');
    }

    function saveProfile(event) {
        event.preventDefault();

        const areaId = document.getElementById('profile_shipping_area_id').value;
        if (!areaId) {
            showToast("Error", "Kecamatan wajib dipilih.");
            return;
        }

        // Validate matching passwords if entered
        const password = document.getElementById('modal_profile_password').value;
        const passwordConfirm = document.getElementById('modal_profile_password_confirm').value;
        if (password && password !== passwordConfirm) {
            showToast("Error", "Konfirmasi kata sandi baru tidak cocok.");
            return;
        }

        const form = document.getElementById('profile-form');
        const formData = new FormData(form);

        fetch('actions/profile-update.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update text fields dynamically
                const name = document.getElementById('modal_profile_name').value;
                const email = document.getElementById('modal_profile_email').value;
                const address = document.getElementById('modal_profile_address').value;
                const phone = document.getElementById('modal_profile_phone').value;

                const nameHeader = document.getElementById('header-profile-name');
                const nameDropdown = document.getElementById('dropdown-profile-name');
                const emailDropdown = document.getElementById('dropdown-profile-email');

                if (nameHeader) nameHeader.textContent = name;
                if (nameDropdown) nameDropdown.textContent = name;
                if (emailDropdown) emailDropdown.textContent = email;

                // Also if we are on checkout.php, update inputs there!
                const checkoutName = document.getElementById('buyer_name');
                const checkoutPhone = document.getElementById('buyer_phone');
                const checkoutAddress = document.getElementById('buyer_address');
                if (checkoutName) checkoutName.value = name;
                if (checkoutPhone) checkoutPhone.value = phone;
                if (checkoutAddress) checkoutAddress.value = address;

                const checkoutArea = document.getElementById('shipping_area_id');
                const areaVal = document.getElementById('profile_shipping_area_id')?.value;
                if (checkoutArea && areaVal) {
                    checkoutArea.value = areaVal;
                    checkoutArea.dispatchEvent(new Event('change'));
                }

                closeProfileModal();
                showToast("Profil Saya", data.message);
                
                // Refresh notif list dynamically
                refreshNotificationsList();

                // Reload the page to apply login/session visual updates
                setTimeout(() => {
                    location.reload();
                }, 500);
            } else {
                showToast("Error", data.message || "Gagal menyimpan profil.");
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast("Error", "Gagal menyimpan data ke server.");
        });
    }

    // Wishlist Drawer Actions
    function openWishlistDrawer(event) {
        if (event) event.stopPropagation();
        const dropdown = document.getElementById('profile-dropdown');
        if (dropdown) dropdown.classList.add('hidden');
        document.getElementById('wishlist-drawer').classList.remove('hidden');
    }

    function closeWishlistDrawer() {
        document.getElementById('wishlist-drawer').classList.add('hidden');
    }

    function removeWishlistItem(productId, event) {
        if (event) event.stopPropagation();
        
        fetch('actions/wishlist-toggle.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'product_id=' + productId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remove item element
                const itemEl = document.getElementById('wishlist-item-' + productId);
                if (itemEl) itemEl.remove();
                
                // Update badge counts
                const countBadge = document.getElementById('wishlist-count-badge');
                if (countBadge) countBadge.textContent = data.count;

                // De-activate card heart button if it exists on page
                const wishlistButtons = document.querySelectorAll('button[onclick*="toggleWishlist"]');
                wishlistButtons.forEach(btn => {
                    if (btn.getAttribute('onclick').includes(productId)) {
                        btn.classList.remove('active');
                        const heart = btn.querySelector('span');
                        if (heart) {
                            heart.style.fontVariationSettings = "'FILL' 0, 'wght' 400";
                            heart.style.color = '';
                        }
                    }
                });

                // Show empty text if no items left
                const drawerItems = document.getElementById('wishlist-drawer-items');
                if (data.count === 0 && drawerItems) {
                    drawerItems.innerHTML = `
                        <div class="h-full flex flex-col items-center justify-center text-center p-6 text-on-surface-variant/60 text-[11px] space-y-md">
                            <span class="material-symbols-outlined text-6xl opacity-35 text-on-surface-variant">favorite</span>
                            <div>
                                <h4 class="font-bold text-on-surface text-body-sm">Belum Ada Favorit</h4>
                                <p class="mt-1">Sukai produk yang Anda minati di katalog untuk memantau harganya di sini.</p>
                            </div>
                            <a href="products" class="px-md py-2 bg-secondary text-white font-bold rounded-lg hover:bg-secondary-container transition-all">Lihat Produk</a>
                        </div>`;
                }

                showToast("Favorit", "Produk dihapus dari favorit Anda.");
                refreshNotificationsList();
            }
        });
    }

    // Refresh notifications list dynamically
    function refreshNotificationsList() {
        fetch(window.location.href)
        .then(response => response.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newNotifList = doc.getElementById('notif-items-list').innerHTML;
            const currentNotifList = document.getElementById('notif-items-list');
            if (currentNotifList) {
                currentNotifList.innerHTML = newNotifList;
            }
        });
    }

    // Global Wishlist toggle function for cards
    function toggleWishlist(element, productId) {
        if (event) event.stopPropagation();
        
        fetch('actions/wishlist-toggle.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'product_id=' + productId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.action === 'added') {
                    element.classList.add('active');
                    const heart = element.querySelector('span');
                    if (heart) {
                        heart.style.fontVariationSettings = "'FILL' 1, 'wght' 400";
                        heart.style.color = '#ba1a1a';
                    }
                    showToast("Favorit", "Produk ditambahkan ke favorit Anda.");
                } else {
                    element.classList.remove('active');
                    const heart = element.querySelector('span');
                    if (heart) {
                        heart.style.fontVariationSettings = "'FILL' 0, 'wght' 400";
                        heart.style.color = '';
                    }
                    showToast("Favorit", "Produk dihapus dari favorit Anda.");
                }
                
                // Update badge counts
                const countBadge = document.getElementById('wishlist-count-badge');
                if (countBadge) countBadge.textContent = data.count;

                // Refresh wishlist drawer & notification list dynamically
                refreshWishlistDrawer();
                refreshNotificationsList();
            } else {
                showToast("Favorit", data.message || "Gagal memperbarui favorit.");
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast("Error", "Gagal menghubungi server.");
        });
    }

    function refreshWishlistDrawer() {
        fetch(window.location.href)
        .then(response => response.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newItems = doc.getElementById('wishlist-drawer-items').innerHTML;
            const currentDrawer = document.getElementById('wishlist-drawer-items');
            if (currentDrawer) {
                currentDrawer.innerHTML = newItems;
            }
        });
    }

    function switchLoginTab(tab) {
        const btnLogin = document.getElementById('tab-btn-login');
        const btnRegister = document.getElementById('tab-btn-register');
        const formLogin = document.getElementById('login-form');
        const formRegister = document.getElementById('register-form');
        
        if (tab === 'login') {
            if (btnLogin) btnLogin.className = "flex-1 py-3 text-center text-[12px] font-bold border-b-2 border-secondary text-secondary transition-all";
            if (btnRegister) btnRegister.className = "flex-1 py-3 text-center text-[12px] font-medium border-b-2 border-transparent text-on-surface-variant hover:text-secondary transition-all";
            if (formLogin) formLogin.classList.remove('hidden');
            if (formRegister) formRegister.classList.add('hidden');
        } else {
            if (btnRegister) btnRegister.className = "flex-1 py-3 text-center text-[12px] font-bold border-b-2 border-secondary text-secondary transition-all";
            if (btnLogin) btnLogin.className = "flex-1 py-3 text-center text-[12px] font-medium border-b-2 border-transparent text-on-surface-variant hover:text-secondary transition-all";
            if (formRegister) formRegister.classList.remove('hidden');
            if (formLogin) formLogin.classList.add('hidden');
        }
    }

    function loginUser(event) {
        event.preventDefault();
        const form = document.getElementById('login-form');
        const formData = new FormData(form);

        fetch('actions/profile-login.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                closeProfileModal();
                showToast("Masuk", data.message);
                setTimeout(() => {
                    location.reload();
                }, 500);
            } else {
                showToast("Error", data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast("Error", "Gagal menghubungi server.");
        });
    }

    function registerUser(event) {
        event.preventDefault();
        
        const areaId = document.getElementById('register_shipping_area_id').value;
        if (!areaId) {
            showToast("Error", "Kecamatan wajib dipilih.");
            return;
        }
        
        const password = document.getElementById('register_password').value;
        const passwordConfirm = document.getElementById('register_password_confirm').value;
        
        if (password !== passwordConfirm) {
            showToast("Error", "Konfirmasi kata sandi tidak cocok.");
            return;
        }
        
        const form = document.getElementById('register-form');
        const formData = new FormData(form);

        fetch('actions/profile-register.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                closeProfileModal();
                showToast("Pendaftaran", data.message);
                setTimeout(() => {
                    location.reload();
                }, 500);
            } else {
                showToast("Error", data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast("Error", "Gagal menghubungi server.");
        });
    }

    const shippingAreasData = <?= json_encode($shippingAreasHeader) ?>;

    function updateRegisterKecamatan() {
        const regencySelect = document.getElementById('register_regency');
        const trigger = document.getElementById('register-kecamatan-trigger');
        if (!regencySelect || !trigger) return;
        const selectedRegency = regencySelect.value;

        if (!selectedRegency) {
            setKecamatanSelected('register', '', 'Pilih Kecamatan...');
            trigger.disabled = true;
            return;
        }

        trigger.disabled = false;
        
        const filtered = shippingAreasData.filter(area => area.regency === selectedRegency);
        const listContainer = document.getElementById('register-kecamatan-list');
        if (!listContainer) return;
        listContainer.innerHTML = '';

        if (filtered.length === 0) {
            listContainer.innerHTML = '<div class="px-3 py-2 text-xs text-on-surface-variant/60">Tidak ada kecamatan</div>';
            return;
        }

        const hiddenInput = document.getElementById('register_shipping_area_id');
        const selectedId = hiddenInput ? hiddenInput.value : '';

        // Reset selected kecamatan if the selected one does not belong to the selected regency
        const hasSelectedInFiltered = filtered.some(area => area.id == selectedId);
        if (selectedId && !hasSelectedInFiltered) {
            setKecamatanSelected('register', '', 'Pilih Kecamatan...');
        }

        filtered.forEach(area => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.dataset.id = area.id;
            btn.dataset.name = area.area_name;

            const isCurrentSelected = selectedId && area.id == selectedId;
            if (isCurrentSelected) {
                btn.className = "w-full text-left px-3 py-2 text-body-sm rounded-md bg-secondary/10 text-secondary font-semibold flex justify-between items-center outline-none register-kecamatan-option-btn";
                btn.innerHTML = `<span>${area.area_name}</span><span class="material-symbols-outlined text-[16px] text-secondary">check</span>`;
            } else {
                btn.className = "w-full text-left px-3 py-2 text-body-sm rounded-md hover:bg-surface-container-low transition-colors text-on-surface focus:bg-surface-container-low outline-none register-kecamatan-option-btn";
                btn.textContent = area.area_name;
            }

            btn.onclick = () => {
                setKecamatanSelected('register', area.id, area.area_name);
                toggleKecamatanDropdown('register', false);
            };
            listContainer.appendChild(btn);
        });
    }

    function updateProfileKecamatan(selectedAreaId = null) {
        const regencySelect = document.getElementById('modal_profile_regency');
        const trigger = document.getElementById('profile-kecamatan-trigger');
        if (!regencySelect || !trigger) return;
        const selectedRegency = regencySelect.value;

        if (!selectedRegency) {
            setKecamatanSelected('profile', '', 'Pilih Kecamatan...');
            trigger.disabled = true;
            return;
        }

        trigger.disabled = false;
        
        const filtered = shippingAreasData.filter(area => area.regency === selectedRegency);
        const listContainer = document.getElementById('profile-kecamatan-list');
        if (!listContainer) return;
        listContainer.innerHTML = '';

        if (filtered.length === 0) {
            listContainer.innerHTML = '<div class="px-3 py-2 text-xs text-on-surface-variant/60">Tidak ada kecamatan</div>';
            return;
        }

        let selectedId = selectedAreaId;
        if (selectedId === null) {
            const hiddenInput = document.getElementById('profile_shipping_area_id');
            selectedId = hiddenInput ? hiddenInput.value : '';
        }

        // Reset selected kecamatan if the selected one does not belong to the selected regency
        const hasSelectedInFiltered = filtered.some(area => area.id == selectedId);
        if (selectedId && !hasSelectedInFiltered) {
            setKecamatanSelected('profile', '', 'Pilih Kecamatan...');
            selectedId = '';
        }

        filtered.forEach(area => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.dataset.id = area.id;
            btn.dataset.name = area.area_name;

            const isCurrentSelected = selectedId && area.id == selectedId;
            if (isCurrentSelected) {
                btn.className = "w-full text-left px-3 py-2 text-body-sm rounded-md bg-secondary/10 text-secondary font-semibold flex justify-between items-center outline-none profile-kecamatan-option-btn";
                btn.innerHTML = `<span>${area.area_name}</span><span class="material-symbols-outlined text-[16px] text-secondary">check</span>`;
            } else {
                btn.className = "w-full text-left px-3 py-2 text-body-sm rounded-md hover:bg-surface-container-low transition-colors text-on-surface focus:bg-surface-container-low outline-none profile-kecamatan-option-btn";
                btn.textContent = area.area_name;
            }

            btn.onclick = () => {
                setKecamatanSelected('profile', area.id, area.area_name);
                toggleKecamatanDropdown('profile', false);
            };
            listContainer.appendChild(btn);
        });
    }

    function setKecamatanSelected(prefix, id, name) {
        const hiddenInput = document.getElementById(`${prefix}_shipping_area_id`);
        const label = document.getElementById(`${prefix}-kecamatan-label`);
        
        if (hiddenInput) hiddenInput.value = id;
        if (label) {
            label.textContent = name;
            if (id) {
                label.classList.remove('text-on-surface-variant/60');
                label.classList.add('text-on-surface', 'font-medium');
            } else {
                label.classList.remove('text-on-surface', 'font-medium');
                label.classList.add('text-on-surface-variant/60');
            }
        }

        if (prefix === 'register') {
            enableRegisterAddress(hiddenInput);
        } else if (prefix === 'profile') {
            enableProfileAddress(hiddenInput);
        }
    }

    function toggleKecamatanDropdown(prefix, show = null) {
        const panel = document.getElementById(`${prefix}-kecamatan-panel`);
        const searchInput = document.getElementById(`${prefix}-kecamatan-search`);
        const trigger = document.getElementById(`${prefix}-kecamatan-trigger`);
        const arrow = document.getElementById(`${prefix}-kecamatan-arrow`);

        if (!trigger || trigger.disabled) return;

        const isHidden = panel ? panel.classList.contains('hidden') : true;
        const shouldShow = show !== null ? show : isHidden;

        if (shouldShow) {
            const otherPrefix = prefix === 'register' ? 'profile' : 'register';
            toggleKecamatanDropdown(otherPrefix, false);

            // Populate the dropdown to ensure highlight and checkmark are correctly updated
            if (prefix === 'register') {
                updateRegisterKecamatan();
            } else if (prefix === 'profile') {
                updateProfileKecamatan();
            }

            if (panel) panel.classList.remove('hidden');
            if (arrow) arrow.classList.add('rotate-180');
            if (searchInput) {
                searchInput.value = '';
                filterKecamatan(prefix);
                setTimeout(() => searchInput.focus(), 50);
            }
        } else {
            if (panel) panel.classList.add('hidden');
            if (arrow) arrow.classList.remove('rotate-180');
        }
    }

    function filterKecamatan(prefix) {
        const searchInput = document.getElementById(`${prefix}-kecamatan-search`);
        if (!searchInput) return;
        const query = searchInput.value.toLowerCase();
        const options = document.querySelectorAll(`.${prefix}-kecamatan-option-btn`);
        
        options.forEach(opt => {
            const name = opt.dataset.name.toLowerCase();
            if (name.includes(query)) {
                opt.style.display = '';
            } else {
                opt.style.display = 'none';
            }
        });
    }

    function enableRegisterAddress(select) {
        const textarea = document.getElementById('register_address');
        if (!textarea) return;
        if (select && select.value) {
            textarea.placeholder = "Tulis alamat pengiriman default lengkap Anda (nama jalan, RT/RW, nomor rumah)...";
        } else {
            textarea.placeholder = "Pilih Area Pengiriman/Kecamatan terlebih dahulu...";
        }
    }

    // Close dropdown panels when clicking outside
    document.addEventListener('click', (e) => {
        ['register', 'profile'].forEach(prefix => {
            const container = document.getElementById(`${prefix}-kecamatan-container`);
            const panel = document.getElementById(`${prefix}-kecamatan-panel`);
            if (container && panel && !container.contains(e.target)) {
                panel.classList.add('hidden');
            }
        });
    });

    function enableProfileAddress(select) {
        const textarea = document.getElementById('modal_profile_address');
        if (!textarea) return;
        if (select && select.value) {
            textarea.placeholder = "Tulis alamat pengiriman default lengkap Anda (nama jalan, RT/RW, nomor rumah)...";
        } else {
            textarea.placeholder = "Pilih Area Pengiriman/Kecamatan terlebih dahulu...";
        }
    }

    // Initialize profile kecamatan dropdown on DOM loaded
    window.addEventListener('DOMContentLoaded', () => {
        const regencySelect = document.getElementById('modal_profile_regency');
        if (regencySelect && regencySelect.value) {
            const savedAreaId = <?= !empty($profile['shipping_area_id']) ? (int)$profile['shipping_area_id'] : 'null' ?>;
            updateProfileKecamatan(savedAreaId);
            if (savedAreaId) {
                const area = shippingAreasData.find(a => a.id == savedAreaId);
                if (area) {
                    setKecamatanSelected('profile', area.id, area.area_name);
                }
            }
        }
    });
</script>

<!-- Flash Message: Triggered as floating toast, not inline -->
<?php if ($flashMessage): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const flashType = <?= json_encode($flashMessage['type']) ?>;
        const flashMsg  = <?= json_encode($flashMessage['message']) ?>;
        const title = flashType === 'success' ? 'Berhasil' : 'Perhatian';
        showToast(title, flashMsg, flashType === 'success' ? 'success' : 'error');
    });
</script>
<?php endif; ?>

<!-- Main Content Start -->
<main class="flex-grow">
