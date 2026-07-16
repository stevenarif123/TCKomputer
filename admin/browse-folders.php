<?php
/**
 * Admin - Folder Browser JSON endpoint for product import images.
 */

require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/admin-auth.php';

requireAdmin();

header('Content-Type: application/json; charset=utf-8');

function jsonResponse(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function isImportImageFilename(string $filename): bool
{
    return in_array(strtolower(pathinfo($filename, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true);
}

function buildFolderBrowserPayload(string $requestedPath, string $baseDir): array
{
    $baseDir = realpath($baseDir);
    if ($baseDir === false) {
        return [['error' => 'Direktori dasar tidak tersedia.'], 500];
    }

    $requestedPath = trim($requestedPath);
    if ($requestedPath !== '' && preg_match('~(^|[\\/])\.\.([\\/]|$)~', $requestedPath)) {
        return [['error' => 'Path tidak valid.'], 400];
    }

    $candidate = $requestedPath === ''
        ? $baseDir
        : ($requestedPath[0] === '/' || preg_match('~^[A-Za-z]:[\\/]~', $requestedPath)
            ? $requestedPath
            : $baseDir . DIRECTORY_SEPARATOR . $requestedPath);

    $resolved = realpath($candidate);
    if ($resolved === false || !is_dir($resolved)) {
        return [['error' => 'Folder tidak ditemukan.'], 404];
    }

    $basePrefix = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if ($resolved !== $baseDir && !str_starts_with($resolved . DIRECTORY_SEPARATOR, $basePrefix)) {
        return [['error' => 'Folder berada di luar direktori yang diizinkan.'], 403];
    }

    $folders = [];
    $files = [];
    foreach (scandir($resolved) ?: [] as $name) {
        if ($name === '.' || $name === '..') {
            continue;
        }

        $path = $resolved . DIRECTORY_SEPARATOR . $name;
        if (is_dir($path)) {
            $folders[] = ['name' => $name, 'path' => $path];
        } elseif (is_file($path) && isImportImageFilename($name)) {
            $files[] = ['name' => $name, 'path' => $path, 'isImage' => true];
        }
    }

    usort($folders, fn($a, $b) => strcasecmp($a['name'], $b['name']));
    usort($files, fn($a, $b) => strcasecmp($a['name'], $b['name']));

    return [['path' => $resolved, 'folders' => $folders, 'files' => $files], 200];
}

if (!defined('FOLDER_BROWSER_TEST')) {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_SERVER['HTTP_X_CSRFTOKEN'] ?? '';
    if (!validateCSRFToken($token)) {
        jsonResponse(['error' => 'Token keamanan tidak valid.'], 403);
    }

    [$payload, $status] = buildFolderBrowserPayload((string)($_GET['path'] ?? ''), __DIR__ . '/..');
    jsonResponse($payload, $status);
}
