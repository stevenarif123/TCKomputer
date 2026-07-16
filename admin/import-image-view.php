<?php
require_once __DIR__ . '/../config/security.php';
configureSecureSession();
require_once __DIR__ . '/../config/admin-auth.php';
requireAdmin();

$file = $_GET['file'] ?? '';

if ($file === '') {
    header("HTTP/1.0 404 Not Found");
    exit;
}

// Clean filename to prevent directory traversal
$file = basename(urldecode($file));

$allowedDirs = [];
// 1. Session temp zip dir
$allowedDirs[] = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'product_import_zip_' . session_id();
// 2. Session temp direct files dir
$allowedDirs[] = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'product_import_' . session_id();
// 3. User uploaded files in temp
$allowedDirs[] = sys_get_temp_dir();

// 4. If there's a session active image folder, allow it too
if (isset($_SESSION['import_data']['image_folder'])) {
    $allowedDirs[] = $_SESSION['import_data']['image_folder'];
}

$filePath = null;
foreach ($allowedDirs as $dir) {
    if (empty($dir)) continue;
    $path = realpath($dir . DIRECTORY_SEPARATOR . $file);
    if ($path !== false && is_file($path)) {
        // Double check it's inside the allowed directory
        $realDir = realpath($dir);
        if ($realDir !== false && str_starts_with($path, $realDir . DIRECTORY_SEPARATOR)) {
            $filePath = $path;
            break;
        }
    }
}

if ($filePath === null) {
    header("HTTP/1.0 404 Not Found");
    exit;
}

// Check mime type
$mime = '';
if (class_exists('finfo')) {
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($filePath) ?: '';
} elseif (function_exists('mime_content_type')) {
    $mime = mime_content_type($filePath) ?: '';
}

if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

// Send headers and file
header("Content-Type: $mime");
header("Content-Length: " . filesize($filePath));
readfile($filePath);
exit;
