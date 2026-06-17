<?php
/**
 * Admin Login Page
 * Handles admin authentication with CSRF protection and brute-force rate limiting.
 * Beautifully redesigned with a premium glassmorphic dark theme.
 */

require_once __DIR__ . '/../config/security.php';
configureSecureSession();
applySecurityHeaders();

session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/admin-auth.php';

// Redirect if already logged in
if (isAdminLoggedIn()) {
    header('Location: index');
    exit;
}

$pdo = getDBConnection();
$error = '';

// Handle POST login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!validateCSRFToken($token)) {
        $error = 'Permintaan tidak valid, silakan coba lagi';
    } else {
        // Rate limit check — scope by action + hashed email + client IP
        $rlKey = buildRateLimitKey('admin_login', $email, $_SERVER['REMOTE_ADDR'] ?? '');
        $rl    = checkRateLimit($pdo, 'admin_login', $rlKey, 5, 900);

        if (!$rl['allowed']) {
            $error = 'Terlalu banyak percobaan login. Coba lagi dalam ' . $rl['retry_after'] . ' detik.';
        } elseif (adminLogin($pdo, $email, $password)) {
            // Success — clear rate limit, redirect
            clearRateLimit($pdo, 'admin_login', $rlKey);
            header('Location: index');
            exit;
        } else {
            // Failure — record attempt
            recordAuthAttempt($pdo, 'admin_login', $rlKey);
            $error = 'Email atau password salah';
        }
    }
}

// Generate CSRF token for form
$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - TC Komputer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        "secondary": "#0058be",
                        "secondary-container": "#2170e4",
                        "on-background": "#0b1c30",
                        "error": "#ba1a1a",
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        @keyframes pulse-glow {
            0%, 100% { opacity: 0.35; transform: scale(1); filter: blur(40px); }
            50% { opacity: 0.5; transform: scale(1.1); filter: blur(50px); }
        }
        .animate-pulse-glow {
            animation: pulse-glow 4s ease-in-out infinite;
        }
    </style>
</head>
<body class="h-full bg-gradient-to-br from-[#0b1c30] via-[#00244d] to-[#003c80] flex items-center justify-center p-4 relative overflow-hidden">
    <!-- Glowing background accents -->
    <div class="absolute -right-20 -top-20 w-96 h-96 bg-[#0058be] rounded-full opacity-40 blur-3xl animate-pulse-glow z-0"></div>
    <div class="absolute -left-20 -bottom-20 w-96 h-96 bg-purple-600 rounded-full opacity-30 blur-3xl animate-pulse-glow z-0" style="animation-delay: 2s;"></div>

    <div class="relative z-10 w-full max-w-md bg-white/10 backdrop-blur-lg border border-white/20 shadow-2xl rounded-3xl p-8 md:p-10 flex flex-col text-white">
        <!-- Logo / Title -->
        <div class="flex flex-col items-center mb-8 select-none text-center">
            <div class="flex items-center justify-center bg-white/10 p-3 rounded-2xl border border-white/20 shadow-lg mb-4">
                <span class="material-symbols-outlined text-white text-3xl" style="font-variation-settings: 'FILL' 1, 'wght' 600;">devices</span>
            </div>
            <h1 class="text-2xl md:text-3xl font-black tracking-tight leading-tight">
                TC <span class="font-light text-white/80">Komputer</span>
            </h1>
            <p class="text-sm text-white/60 mt-1">Panel Administrasi Toko</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="mb-6 bg-error/20 border border-error/35 text-white text-sm rounded-xl p-4 flex items-center gap-2 animate-pulse">
                <span class="material-symbols-outlined text-lg flex-shrink-0 text-red-300">error</span>
                <span><?= sanitizeOutput($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="login" class="flex flex-col gap-5">
            <input type="hidden" name="csrf_token" value="<?= sanitizeOutput($csrfToken) ?>">

            <div class="flex flex-col gap-1.5">
                <label for="email" class="text-[10px] font-bold uppercase tracking-wider text-white/70 block">Email</label>
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-white/50 text-xl">mail</span>
                    <input type="email" id="email" name="email" required autocomplete="email"
                           value="<?= sanitizeOutput($email ?? '') ?>"
                           placeholder="admin@tckomputer.com"
                           class="w-full bg-white/5 border border-white/25 rounded-xl pl-10 pr-4 py-3 text-sm placeholder-white/30 text-white focus:outline-none focus:ring-2 focus:ring-secondary/50 focus:border-white/50 focus:bg-white/10 transition-all">
                </div>
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="password" class="text-[10px] font-bold uppercase tracking-wider text-white/70 block">Password</label>
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-white/50 text-xl">lock</span>
                    <input type="password" id="password" name="password" required autocomplete="current-password"
                           placeholder="••••••••"
                           class="w-full bg-white/5 border border-white/25 rounded-xl pl-10 pr-4 py-3 text-sm placeholder-white/30 text-white focus:outline-none focus:ring-2 focus:ring-secondary/50 focus:border-white/50 focus:bg-white/10 transition-all">
                </div>
            </div>

            <button type="submit" class="mt-4 w-full bg-white hover:bg-white/95 text-on-background font-bold py-3.5 px-6 rounded-xl hover:scale-[1.02] active:scale-95 transition-all shadow-lg text-sm tracking-wide">
                Masuk ke Panel
            </button>
        </form>
    </div>
</body>
</html>
