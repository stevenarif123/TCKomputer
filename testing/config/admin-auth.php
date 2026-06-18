<?php
/**
 * Admin Authentication Guard
 * Protects admin pages from unauthorized access and manages admin session lifecycle.
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Require admin authentication.
 * Redirects to admin login page if the user is not authenticated.
 *
 * @return void
 */
function requireAdmin(): void
{
    if (!isAdminLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Check if an admin is currently logged in.
 *
 * @return bool
 */
function isAdminLoggedIn(): bool
{
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

/**
 * Get the currently authenticated admin's data.
 *
 * @param PDO $pdo Database connection
 * @return array|null Admin data array or null if not found
 */
function getAdminData(PDO $pdo): ?array
{
    if (!isAdminLoggedIn()) {
        return null;
    }

    try {
        $stmt = $pdo->prepare("SELECT id, name, email, created_at FROM admins WHERE id = ?");
        $stmt->execute([$_SESSION['admin_id']]);
        $admin = $stmt->fetch();

        return $admin ?: null;
    } catch (PDOException $e) {
        error_log('Error fetching admin data: ' . $e->getMessage());
        return null;
    }
}

/**
 * Authenticate admin with email and password.
 * Uses password_verify against bcrypt hash stored in the database.
 * On success, regenerates session ID and stores admin_id in session.
 * On failure, returns false without revealing which field is incorrect.
 *
 * @param PDO $pdo Database connection
 * @param string $email Admin email
 * @param string $password Admin password (plain text)
 * @return bool True on successful login, false otherwise
 */
function adminLogin(PDO $pdo, string $email, string $password): bool
{
    try {
        $stmt = $pdo->prepare("SELECT id, password FROM admins WHERE email = ?");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if (!$admin) {
            return false;
        }

        if (!password_verify($password, $admin['password'])) {
            return false;
        }

        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);

        // Store admin ID in session
        $_SESSION['admin_id'] = $admin['id'];

        return true;
    } catch (PDOException $e) {
        error_log('Admin login error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Log out the admin by destroying the session.
 * Redirects to the admin login page after logout.
 *
 * @return void
 */
function adminLogout(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();

    header('Location: login.php');
    exit;
}
