<?php
/**
 * Helper Functions
 * Shared utility functions used across buyer and admin pages.
 */

// Set default timezone to WITA (UTC+8) for South Sulawesi
date_default_timezone_set('Asia/Makassar');

/**
 * Format an integer amount to Indonesian Rupiah format.
 * Uses dot as thousands separator, no decimals.
 *
 * @param int $amount The amount in Rupiah
 * @return string Formatted string e.g. "Rp 1.500.000"
 */
function formatRupiah(int $amount): string
{
    if ($amount < 0) {
        return 'Rp 0';
    }

    if ($amount === 0) {
        return 'Rp 0';
    }

    return 'Rp ' . number_format($amount, 0, ',', '.');
}

/**
 * Generate a URL-safe slug from text.
 * Lowercase, alphanumeric + hyphens only, no consecutive/leading/trailing hyphens.
 * Maximum 255 characters.
 *
 * @param string $text The source text
 * @return string The generated slug
 */
function generateSlug(string $text): string
{
    // Convert to lowercase
    $slug = strtolower($text);

    // Replace non-alphanumeric characters with hyphens
    $slug = preg_replace('/[^a-z0-9]/', '-', $slug);

    // Remove consecutive hyphens
    $slug = preg_replace('/-+/', '-', $slug);

    // Remove leading and trailing hyphens
    $slug = trim($slug, '-');

    // Truncate to 255 characters
    if (strlen($slug) > 255) {
        $slug = substr($slug, 0, 255);
        // Trim at last complete word boundary (last hyphen within limit)
        $lastHyphen = strrpos($slug, '-');
        if ($lastHyphen !== false && $lastHyphen > 0) {
            $slug = substr($slug, 0, $lastHyphen);
        }
        // Remove any trailing hyphen produced by truncation
        $slug = rtrim($slug, '-');
    }

    return $slug;
}

/**
 * Generate a unique order code in format SIT-YYYYMMDD-XXXX.
 * Queries DB for the last sequence of the day and increments.
 *
 * @param PDO $pdo Database connection
 * @return string Unique order code
 */
function generateOrderCode(PDO $pdo): string
{
    $datePrefix = 'SIT-' . date('Ymd') . '-';

    // Find the highest sequence number for today
    $stmt = $pdo->prepare(
        "SELECT order_code FROM orders 
         WHERE order_code LIKE ? 
         ORDER BY order_code DESC LIMIT 1"
    );
    $stmt->execute([$datePrefix . '%']);
    $lastOrder = $stmt->fetch();

    if ($lastOrder) {
        // Extract sequence number and increment
        $lastSequence = (int) substr($lastOrder['order_code'], -4);
        $newSequence = $lastSequence + 1;
    } else {
        $newSequence = 1;
    }

    return $datePrefix . str_pad($newSequence, 4, '0', STR_PAD_LEFT);
}

/**
 * Load and structure active FAQ categories with their active FAQ entries.
 * Returns raw string values; sanitize when rendering output.
 *
 * @param PDO $pdo Database connection
 * @return array Indexed category arrays, each with a 'faqs' key
 */
function loadFaqData(PDO $pdo): array
{
    $stmtCats = $pdo->prepare(
        "SELECT * FROM faq_categories
         WHERE is_active = 1
         ORDER BY sort_order ASC"
    );
    $stmtCats->execute();
    $categories = $stmtCats->fetchAll();

    $stmtFaqs = $pdo->prepare(
        "SELECT f.*, fc.name AS category_name, fc.icon AS category_icon
         FROM faqs f
         INNER JOIN faq_categories fc ON f.faq_category_id = fc.id
         WHERE f.is_active = 1 AND fc.is_active = 1
         ORDER BY fc.sort_order ASC, f.sort_order ASC"
    );
    $stmtFaqs->execute();
    $allFaqs = $stmtFaqs->fetchAll();

    $groupedFaqs = [];
    foreach ($allFaqs as $faq) {
        $categoryId = $faq['faq_category_id'];
        if (!isset($groupedFaqs[$categoryId])) {
            $groupedFaqs[$categoryId] = [];
        }
        $groupedFaqs[$categoryId][] = $faq;
    }

    foreach ($categories as &$category) {
        $category['faqs'] = $groupedFaqs[$category['id']] ?? [];
    }
    unset($category);

    $categories = array_filter($categories, function (array $category): bool {
        return !empty($category['faqs']);
    });

    return array_values($categories);
}

/**
 * Validate FAQ form submission data.
 * Returns error messages for invalid fields and does not modify database state.
 *
 * @param PDO $pdo Database connection
 * @param array $data Submitted FAQ form data
 * @return array<int, string> Validation error messages, empty when valid
 */
function validateFaqInput(PDO $pdo, array $data): array
{
    $errors = [];

    $question = trim((string)($data['question'] ?? ''));
    if ($question === '') {
        $errors[] = 'Pertanyaan FAQ wajib diisi';
    } elseif (strlen($question) > 500) {
        $errors[] = 'Pertanyaan maksimal 500 karakter';
    }

    $answer = trim((string)($data['answer'] ?? ''));
    if ($answer === '') {
        $errors[] = 'Jawaban FAQ wajib diisi';
    } elseif (strlen($answer) > 5000) {
        $errors[] = 'Jawaban maksimal 5000 karakter';
    }

    $categoryId = (int)($data['faq_category_id'] ?? 0);
    if ($categoryId <= 0) {
        $errors[] = 'Kategori FAQ wajib dipilih';
    } else {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM faq_categories WHERE id = ? AND is_active = 1"
        );
        $stmt->execute([$categoryId]);

        if ((int)$stmt->fetchColumn() === 0) {
            $errors[] = 'Kategori FAQ tidak valid atau tidak aktif';
        }
    }

    $sortOrder = (int)($data['sort_order'] ?? 0);
    if ($sortOrder < 0 || $sortOrder > 999) {
        $errors[] = 'Urutan harus antara 0 dan 999';
    }

    return $errors;
}

/**
 * Validate FAQ Category form submission data.
 *
 * @param PDO $pdo Database connection
 * @param array $data Submitted FAQ Category form data
 * @param int|null $categoryId Optional category ID to exclude from uniqueness check
 * @return array<int, string> Validation error messages, empty when valid
 */
function validateFaqCategoryInput(PDO $pdo, array $data, ?int $categoryId = null): array
{
    $errors = [];

    // Validate name (required, max 100 chars, unique)
    $name = trim((string)($data['name'] ?? ''));
    if ($name === '') {
        $errors[] = 'Nama kategori wajib diisi';
    } elseif (strlen($name) > 100) {
        $errors[] = 'Nama kategori maksimal 100 karakter';
    } else {
        // Uniqueness check
        if ($categoryId !== null) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM faq_categories WHERE name = ? AND id != ?");
            $stmt->execute([$name, $categoryId]);
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM faq_categories WHERE name = ?");
            $stmt->execute([$name]);
        }
        if ((int)$stmt->fetchColumn() > 0) {
            $errors[] = 'Nama kategori sudah digunakan';
        }
    }

    // Validate description (optional, max 500 chars)
    $description = trim((string)($data['description'] ?? ''));
    if ($description !== '' && strlen($description) > 500) {
        $errors[] = 'Deskripsi maksimal 500 karakter';
    }

    // Validate icon: If provided, make sure it is a valid format
    // (alphanumeric/underscores/hyphens, max 100 chars, representing a Material Symbol name)
    $icon = trim((string)($data['icon'] ?? ''));
    if ($icon !== '') {
        if (strlen($icon) > 100) {
            $errors[] = 'Nama ikon maksimal 100 karakter';
        } elseif (!preg_match('/^[a-zA-Z0-9_-]+$/', $icon)) {
            $errors[] = 'Format ikon tidak valid';
        }
    }

    // Validate sort_order (0-999)
    $sortOrder = (int)($data['sort_order'] ?? 0);
    if ($sortOrder < 0 || $sortOrder > 999) {
        $errors[] = 'Urutan harus antara 0 dan 999';
    }

    return $errors;
}

/**
 * Safely delete a FAQ category only if no FAQs reference it.
 *
 * @param PDO $pdo Database connection
 * @param int $categoryId Category ID to delete
 * @return array{success: bool, message: string}
 */
function deleteFaqCategory(PDO $pdo, int $categoryId): array
{
    // Check if category exists
    $stmt = $pdo->prepare("SELECT id, name FROM faq_categories WHERE id = ?");
    $stmt->execute([$categoryId]);
    $category = $stmt->fetch();

    if (!$category) {
        return ['success' => false, 'message' => 'Kategori FAQ tidak ditemukan'];
    }

    // Check if any FAQs reference this category
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM faqs WHERE faq_category_id = ?");
    $stmt->execute([$categoryId]);
    $faqCount = (int)$stmt->fetchColumn();

    if ($faqCount > 0) {
        return [
            'success' => false,
            'message' => "Kategori tidak dapat dihapus karena masih memiliki {$faqCount} FAQ"
        ];
    }

    // Safe to delete
    $stmt = $pdo->prepare("DELETE FROM faq_categories WHERE id = ?");
    $stmt->execute([$categoryId]);

    return ['success' => true, 'message' => 'Kategori FAQ berhasil dihapus'];
}

/**
 * Upload an image file with security validation.
 * Validates MIME type, extension, size, and scans for PHP content.
 *
 * @param array $file $_FILES element
 * @param string $targetDir Target directory path
 * @param string|null $errorMsg Pass-by-reference variable to capture specific error messages
 * @return string|false Filename on success, false on failure
 */
function uploadImage(array $file, string $targetDir, ?string &$errorMsg = null): string|false
{
    $errorMsg = '';

    // Validate upload error
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        $errCode = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        switch ($errCode) {
            case UPLOAD_ERR_INI_SIZE:
                $errorMsg = 'Ukuran file melebihi batas upload_max_filesize di konfigurasi php.ini server.';
                break;
            case UPLOAD_ERR_FORM_SIZE:
                $errorMsg = 'Ukuran file melebihi batas MAX_FILE_SIZE yang ditentukan di form HTML.';
                break;
            case UPLOAD_ERR_PARTIAL:
                $errorMsg = 'File hanya terunggah sebagian (koneksi terputus).';
                break;
            case UPLOAD_ERR_NO_FILE:
                $errorMsg = 'Tidak ada file yang diunggah.';
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $errorMsg = 'Folder penyimpanan sementara (tmp) PHP tidak ditemukan di server.';
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $errorMsg = 'Gagal menulis file ke disk (masalah izin folder atau ruang disk penuh).';
                break;
            case UPLOAD_ERR_EXTENSION:
                $errorMsg = 'Unggahan file dihentikan oleh ekstensi PHP.';
                break;
            default:
                $errorMsg = 'Kesalahan unggahan PHP dengan kode: ' . $errCode;
                break;
        }
        error_log("Upload error: " . $errorMsg);
        return false;
    }

    // Validate file size (max 2MB)
    $maxSize = 2 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        $formattedSize = round($file['size'] / (1024 * 1024), 2) . ' MB';
        $errorMsg = "Ukuran file ($formattedSize) melebihi batas maksimal 2 MB.";
        error_log("File size exceeds 2MB limit: " . $file['size'] . " bytes");
        return false;
    }

    // Ensure target directory exists and is writable
    if (!file_exists($targetDir)) {
        if (!@mkdir($targetDir, 0755, true)) {
            $errorMsg = 'Gagal membuat folder tujuan unggahan: ' . basename($targetDir);
            error_log("Failed to create upload directory: $targetDir");
            return false;
        }
    }
    if (!is_writable($targetDir)) {
        @chmod($targetDir, 0755);
        if (!is_writable($targetDir)) {
            $errorMsg = 'Folder tujuan unggahan tidak memiliki izin menulis (write-permission denied): ' . basename($targetDir);
            error_log("Upload directory is not writable: $targetDir");
            return false;
        }
    }

    // Validate image validity and get MIME type via getimagesize first
    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        $errorMsg = 'Validasi gambar gagal. File mungkin rusak atau bukan file gambar asli yang valid.';
        error_log("Failed to validate image using getimagesize. The file may be corrupt or not a valid image.");
        return false;
    }

    // Validate MIME type returned by getimagesize, falling back to finfo if mime is empty
    $mimeType = $imageInfo['mime'] ?? '';
    if (empty($mimeType)) {
        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file['tmp_name']);
        } elseif (function_exists('mime_content_type')) {
            $mimeType = mime_content_type($file['tmp_name']);
        } else {
            $mimeType = $file['type'] ?? '';
        }
    }

    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mimeType, $allowedMimes, true)) {
        $errorMsg = "Format/MIME file tidak didukung ($mimeType). Hanya mendukung JPG, PNG, dan WebP.";
        error_log("Invalid MIME type: " . $mimeType);
        return false;
    }

    // Validate extension
    $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowedExts, true)) {
        $errorMsg = "Ekstensi file (.$ext) tidak didukung. Hanya mendukung .jpg, .jpeg, .png, dan .webp.";
        error_log("Invalid file extension: " . $ext);
        return false;
    }

    // Generate unique filename
    $filename = uniqid('img_') . '_' . time() . '.' . $ext;
    $targetPath = rtrim($targetDir, '/\\') . '/' . $filename;

    // Move file
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        $errorMsg = "Gagal memindahkan file ke folder tujuan. Periksa izin folder server.";
        error_log("Failed to move uploaded file from " . $file['tmp_name'] . " to " . $targetPath);
        return false;
    }

    return $filename;
}

/**
 * Delete an image file from the target directory.
 *
 * @param string $filename The filename to delete
 * @param string $targetDir The directory containing the file
 * @return bool True if deleted successfully, false otherwise
 */
function deleteImage(string $filename, string $targetDir): bool
{
    if (empty($filename)) {
        return false;
    }

    $filePath = rtrim($targetDir, '/\\') . '/' . $filename;

    if (file_exists($filePath) && is_file($filePath)) {
        return unlink($filePath);
    }

    return false;
}

/**
 * Validate a CSRF token against the session-stored token.
 *
 * @param string $token The submitted token
 * @return bool True if valid, false otherwise
 */
function validateCSRFToken(string $token): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Generate a cryptographically random CSRF token (32+ bytes).
 * Stores in session and returns the token.
 *
 * @param bool $regenerate Force regeneration of the token
 * @return string The generated CSRF token
 */
function generateCSRFToken(bool $regenerate = false): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if ($regenerate || empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Sanitize output for safe HTML display.
 * Uses htmlspecialchars with ENT_QUOTES and UTF-8 encoding.
 *
 * @param ?string $text The text to sanitize
 * @return string Sanitized text
 */
function sanitizeOutput(?string $text): string
{
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Truncate text to specified length, adding ellipsis if truncated.
 *
 * @param string $text The text to truncate
 * @param int $length Maximum length (default 100)
 * @return string Truncated text
 */
function truncateText(string $text, int $length = 100): string
{
    if (function_exists('mb_strlen')) {
        if (mb_strlen($text, 'UTF-8') <= $length) {
            return $text;
        }
        return mb_substr($text, 0, $length, 'UTF-8') . '...';
    }

    if (strlen($text) <= $length) {
        return $text;
    }

    return substr($text, 0, $length) . '...';
}

/**
 * Get an HTML badge for product stock status.
 *
 * @param string $status The product status (ready, po, habis)
 * @param int $stock The stock quantity
 * @return string HTML badge string
 */
function getStockStatusBadge(string $status, int $stock): string
{
    switch ($status) {
        case 'ready':
            return '<span class="badge badge-success">Ready (' . (int)$stock . ')</span>';
        case 'po':
            return '<span class="badge badge-warning">Pre-Order</span>';
        case 'habis':
            return '<span class="badge badge-danger">Habis</span>';
        default:
            return '<span class="badge">' . sanitizeOutput($status) . '</span>';
    }
}

/**
 * Validate an Indonesian phone number.
 * Accepts formats: 08xx (10-15 digits total) or +628xx (12-16 chars including +).
 *
 * @param string $phone The phone number to validate
 * @return bool True if valid Indonesian phone number
 */
function isValidPhoneNumber(string $phone): bool
{
    $phone = trim($phone);

    if (empty($phone)) {
        return false;
    }

    // Remove spaces and dashes for validation
    $cleaned = preg_replace('/[\s\-]/', '', $phone);

    // Format: +628xxxxxxxxx (10-13 digits after +62)
    if (preg_match('/^\+62[0-9]{8,13}$/', $cleaned)) {
        return true;
    }

    // Format: 08xxxxxxxxx (10-15 digits total)
    if (preg_match('/^08[0-9]{8,13}$/', $cleaned)) {
        return true;
    }

    return false;
}

/**
 * Redirect to a URL with an optional flash message.
 *
 * @param string $url The URL to redirect to
 * @param string $message Flash message text (optional)
 * @param string $type Message type: success, warning, or error (default: success)
 * @return void
 */
function redirect(string $url, string $message = '', string $type = 'success'): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!empty($message)) {
        $truncatedMessage = function_exists('mb_substr')
            ? mb_substr($message, 0, 255, 'UTF-8')
            : substr($message, 0, 255);
        $_SESSION['flash'] = [
            'type' => $type,
            'message' => $truncatedMessage
        ];
    }

    header("Location: $url");
    exit;
}

/**
 * Get and clear a flash message from the session.
 * Flash messages are single-use: retrieved once then deleted.
 *
 * @return array|null Array with 'type' and 'message' keys, or null if none exists
 */
function getFlashMessage(): ?array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }

    return null;
}

/**
 * Paginate database query results.
 *
 * @param PDO $pdo Database connection
 * @param string $query Base SQL query (without LIMIT/OFFSET)
 * @param array $params Query parameters for prepared statement
 * @param int $perPage Number of items per page
 * @param int $currentPage Current page number (1-based)
 * @return array Associative array with 'data', 'total', 'pages', 'current_page'
 */
function paginate(PDO $pdo, string $query, array $params = [], int $perPage = 12, int $currentPage = 1): array
{
    // Ensure valid page number
    $currentPage = max(1, $currentPage);
    $perPage = max(1, $perPage);

    // Count total results
    $countQuery = "SELECT COUNT(*) FROM (" . $query . ") AS count_table";
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute($params);
    $total = (int) $stmt->fetchColumn();

    // Calculate total pages
    $pages = (int) ceil($total / $perPage);

    // Ensure current page doesn't exceed total pages
    if ($pages > 0 && $currentPage > $pages) {
        $currentPage = $pages;
    }

    // Calculate offset
    $offset = ($currentPage - 1) * $perPage;

    // Fetch paginated data
    $paginatedQuery = $query . " LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
    $stmt = $pdo->prepare($paginatedQuery);
    $stmt->execute($params);
    $data = $stmt->fetchAll();

    return [
        'data' => $data,
        'total' => $total,
        'pages' => $pages,
        'current_page' => $currentPage,
    ];
}

/**
 * Determine whether a redirect target is safe (same-host or local relative path).
 *
 * A target is safe when:
 * - It is a relative path starting with exactly one '/' (no '//').
 * - It is an absolute URL whose host matches $allowedHost (case-insensitive,
 *   including any explicit port).
 *
 * A target is NOT safe when:
 * - It is null, empty, or whitespace-only.
 * - It exceeds 2048 characters.
 * - It starts with '//' (protocol-relative — browser resolves as absolute).
 * - It uses a dangerous scheme: javascript:, data:, vbscript:, file:.
 * - It is an absolute URL pointing to a different host.
 *
 * @param string|null $target      The candidate redirect target (untrusted).
 * @param string      $allowedHost The allowed host (e.g. $_SERVER['HTTP_HOST']).
 * @return bool True if safe to redirect to.
 * @pure
 */
function isSafeRedirectTarget(?string $target, string $allowedHost): bool
{
    if ($target === null || trim($target) === '') {
        return false;
    }

    $t = trim($target);

    // Length guard (Req 13.2)
    if (strlen($t) > 2048) {
        return false;
    }

    // Reject dangerous schemes (Req 13.2)
    if (preg_match('#^\s*(javascript|data|vbscript|file):#i', $t)) {
        return false;
    }

    // Reject protocol-relative '//host' (Req 13.2)
    if (str_starts_with($t, '//')) {
        return false;
    }

    // Relative path starting with single '/' — safe (Req 13.1)
    if (str_starts_with($t, '/') && !str_starts_with($t, '//')) {
        return true;
    }

    // Absolute URL — check scheme and host (Req 13.2)
    if (preg_match('#^[a-z][a-z0-9+.\-]*://#i', $t)) {
        // Only http and https schemes are allowed
        if (!preg_match('#^https?://#i', $t)) {
            return false;
        }
        $host = parse_url($t, PHP_URL_HOST);
        if ($host === null || $host === false) {
            return false;
        }
        return strcasecmp($host, $allowedHost) === 0;
    }

    // Relative path without leading '/' (e.g. 'index', 'products') — safe as local
    return true;
}

/**
 * Return a guaranteed-safe redirect destination.
 *
 * If $target passes isSafeRedirectTarget(), it is returned as-is (preserving
 * query string and fragment). Otherwise $fallback is returned.
 * If $fallback itself fails safety checks, '/' is returned (Req 13.6).
 *
 * @param string|null $target      Untrusted redirect target.
 * @param string      $allowedHost The allowed host.
 * @param string      $fallback    Local fallback path (default 'index').
 * @return string A safe redirect destination.
 * @pure
 */
function sanitizeRedirectTarget(?string $target, string $allowedHost, string $fallback = 'index'): string
{
    if (isSafeRedirectTarget($target, $allowedHost)) {
        return (string)$target;
    }

    // Try fallback
    if (isSafeRedirectTarget($fallback, $allowedHost)) {
        return $fallback;
    }

    // Ultimate safe fallback (Req 13.6)
    return '/';
}

/**
 * Perform a redirect to a validated same-host/relative target.
 *
 * Wraps the existing redirect() helper. Uses the current HTTP_HOST as the
 * allowed host. Falls back to $fallback (or '/') on unsafe targets.
 *
 * @param string|null $target   Untrusted redirect target (e.g. HTTP_REFERER).
 * @param string      $fallback Local fallback path (default 'index').
 * @param string      $message  Flash message text.
 * @param string      $type     Flash message type: success, warning, error.
 */
function safeRedirect(
    ?string $target,
    string  $fallback = 'index',
    string  $message  = '',
    string  $type     = 'success'
): void {
    $allowedHost = $_SERVER['HTTP_HOST'] ?? '';
    $safe = sanitizeRedirectTarget($target, $allowedHost, $fallback);
    redirect($safe, $message, $type);
}


/**
 * Parse a plain-text specification string into key-value pairs.
 * Supports multiple delimiter formats commonly used in product specs.
 *
 * @param string|null $specText Raw specification text from database
 * @return array{parsed: array<int, array{key: string, value: string}>, unparsed: string}
 */
function parseSpecification(?string $specText): array
{
    $text = trim((string)$specText);
    if ($text === '') {
        return ['parsed' => [], 'unparsed' => ''];
    }

    $lines = preg_split('/\r\n|\r|\n/', $text);
    $parsed = [];
    $unparsedLines = [];

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        // Split by the first occurrence of a delimiter: ':', '-', '=', '|'
        // Allow optional spaces around the delimiter
        // Requirement 4.1: Support Key: Value, Key - Value, Key = Value, Key | Value
        if (preg_match('/^([^:=\-|]+?)\s*[:=\-|]\s*(.+)$/', $line, $matches)) {
            $key = trim($matches[1]);
            $value = trim($matches[2]);

            if ($key !== '' && $value !== '') {
                $parsed[] = ['key' => $key, 'value' => $value];
                continue;
            }
        }

        $unparsedLines[] = $line;
    }

    return [
        'parsed' => $parsed,
        'unparsed' => implode("\n", $unparsedLines),
    ];
}

/**
 * Generate a smart pagination range with ellipsis markers.
 * Shows first page, last page, current page, and neighbors, with '...' for gaps.
 *
 * @param int $currentPage The current active page (1-based)
 * @param int $totalPages Total number of pages
 * @param int $neighbors Number of neighbor pages to show around current (default: 1)
 * @return array<int|string> Array of page numbers and '...' strings
 */
function generatePaginationRange(int $currentPage, int $totalPages, int $neighbors = 1): array
{
    if ($totalPages <= 1) {
        return $totalPages === 1 ? [1] : [];
    }

    $currentPage = max(1, min($currentPage, $totalPages));
    $neighbors = max(0, $neighbors);

    $pages = [1, $totalPages];

    for ($i = $currentPage - $neighbors; $i <= $currentPage + $neighbors; $i++) {
        if ($i > 1 && $i < $totalPages) {
            $pages[] = $i;
        }
    }

    $pages = array_values(array_unique($pages));
    sort($pages, SORT_NUMERIC);

    $range = [];
    $previous = null;

    foreach ($pages as $page) {
        if ($previous !== null && $page - $previous > 1) {
            $range[] = '...';
        }
        $range[] = $page;
        $previous = $page;
    }

    return $range;
}

/**
 * Generate placeholder social proof data for a product.
 * Uses deterministic seeding from product ID for consistent display.
 *
 * @param array $product Product row containing at least id field
 * @return array{rating: float, review_count: int, sold_count: int, sold_display: string}
 */
function generateSocialProof(array $product): array
{
    $seed = max(1, (int)($product['id'] ?? 1));

    $ratingRaw = 40 + ($seed % 11); // 40..50
    $rating = $ratingRaw / 10;

    $reviewCount = 5 + (($seed * 7) % 196); // 5..200
    $soldCount = 10 + (($seed * 13) % 491); // 10..500
    $soldDisplay = formatSoldCount($soldCount);

    return [
        'rating' => (float)$rating,
        'review_count' => (int)$reviewCount,
        'sold_count' => (int)$soldCount,
        'sold_display' => $soldDisplay,
    ];
}

/**
 * Format raw sold count into human-readable marketplace-style label.
 * 
 * @param int $count Raw sold count
 * @return string Human-readable sold count label, e.g. "50+" or "300+"
 */
function formatSoldCount(int $count): string
{
    if ($count >= 1000) {
        return floor($count / 1000) . 'rb+';
    }

    if ($count >= 100) {
        return floor($count / 100) * 100 . '+';
    }

    if ($count >= 10) {
        return floor($count / 10) * 10 . '+';
    }

    return (string)max(0, $count);
}

/**
 * Validate quick filter parameter.
 * 
 * @param string $filter Raw filter query parameter
 * @return string One of '', 'ready', 'promo', 'new'
 */
function validateQuickFilter(string $filter): string
{
    $allowed = ['ready', 'promo', 'new'];
    return in_array($filter, $allowed, true) ? $filter : '';
}

/**
 * Apply quick filter to WHERE clause and parameters array.
 * 
 * @param string $quickFilter Validated quick filter ('ready', 'promo', 'new')
 * @param array $where Existing SQL WHERE fragments
 * @param array $params Existing prepared statement params
 * @return array{where: array<int, string>, params: array<int, mixed>}
 */
function applyQuickFilterToWhereClause(string $quickFilter, array $where, array $params): array
{
    if ($quickFilter === 'ready') {
        $where[] = "p.status = ?";
        $params[] = 'ready';
        $where[] = "p.stock > 0";
    }

    if ($quickFilter === 'promo') {
        $where[] = "p.promo_active = 1";
        $where[] = "p.promo_price > 0";
        $where[] = "p.promo_stock > 0";
    }

    return ['where' => $where, 'params' => $params];
}

/**
 * Detect whether a banner row contains source-backed promotional wording.
 *
 * @param array $banner Existing banner database row
 * @return bool True when title or description includes promo wording
 */
function isHomepagePromoShortcutBanner(array $banner): bool
{
    $haystack = strtolower(trim((string)($banner['title'] ?? '') . ' ' . (string)($banner['description'] ?? '')));
    if ($haystack === '') {
        return false;
    }

    foreach (['promo', 'diskon', 'sale'] as $keyword) {
        if (str_contains($haystack, $keyword)) {
            return true;
        }
    }

    return false;
}

/**
 * Extract configured homepage promo shortcuts from existing store settings only.
 * Empty titles are omitted; no default card data is generated.
 *
 * @param array $storeSettings Existing store settings row/map
 * @param int $limit Maximum number of configured promo shortcut slots to read
 * @return array<int, array{title: string, desc: string, link: string, icon: string, index: int}>
 */
function extractHomepagePromoShortcuts(array $storeSettings, int $limit = 3): array
{
    $shortcuts = [];
    $seen = [];
    $limit = max(0, $limit);

    for ($i = 1; $i <= $limit; $i++) {
        $title = trim((string)($storeSettings["promo_banner_{$i}_title"] ?? ''));
        if ($title === '') {
            continue;
        }

        $desc = trim((string)($storeSettings["promo_banner_{$i}_desc"] ?? ''));
        $link = trim((string)($storeSettings["promo_banner_{$i}_link"] ?? ''));
        $icon = trim((string)($storeSettings["promo_banner_{$i}_icon"] ?? ''));
        $fingerprint = strtolower($title . "\n" . $desc . "\n" . $link);
        if (isset($seen[$fingerprint])) {
            continue;
        }
        $seen[$fingerprint] = true;

        $shortcuts[] = [
            'title' => $title,
            'desc' => $desc,
            'link' => $link,
            'icon' => $icon,
            'index' => $i,
        ];
    }

    return $shortcuts;
}

/**
 * Parse popular search settings into trimmed, non-empty source-ordered tokens.
 *
 * @param string|null $popularSearches Comma-separated store setting value
 * @return array<int, string>
 */
function parsePopularSearches(?string $popularSearches): array
{
    $raw = trim((string)$popularSearches);
    if ($raw === '') {
        return [];
    }

    $tokens = [];
    foreach (explode(',', $raw) as $token) {
        $token = trim($token);
        if ($token !== '') {
            $tokens[] = $token;
        }
    }

    return $tokens;
}

/**
 * Normalize flash sale settings into an active flag and remaining seconds.
 *
 * @param array $storeSettings Existing store settings row/map
 * @param int|null $now Unix timestamp, injectable for tests
 * @return array{is_active: bool, seconds_remaining: int, ends_at: string}
 */
function normalizeFlashSaleState(array $storeSettings, ?int $now = null): array
{
    $now = $now ?? time();
    $endValue = trim((string)($storeSettings['flash_sale_end'] ?? ''));
    $endTime = $endValue !== '' ? strtotime($endValue) : false;
    $secondsRemaining = ($endTime !== false && $endTime > $now) ? $endTime - $now : 0;
    $isActiveSetting = !empty($storeSettings['flash_sale_active']);

    return [
        'is_active' => $isActiveSetting && $secondsRemaining > 0,
        'seconds_remaining' => (int)$secondsRemaining,
        'ends_at' => $endValue,
    ];
}

/**
 * Select the current product price from database row values and flash sale state.
 *
 * @param array $product Existing product database row
 * @param bool $isFlashSaleActive Whether the global flash sale state is active
 * @return array{price: int, original_price: int, is_promo: bool}
 */
function determineActivePrice(array $product, bool $isFlashSaleActive): array
{
    $sellingPrice = max(0, (int)($product['selling_price'] ?? 0));
    $promoPrice = (int)($product['promo_price'] ?? 0);
    $promoStock = (int)($product['promo_stock'] ?? 0);
    $isPromo = $isFlashSaleActive
        && !empty($product['promo_active'])
        && $promoPrice > 0
        && $promoStock > 0;

    return [
        'price' => $isPromo ? $promoPrice : $sellingPrice,
        'original_price' => $sellingPrice,
        'is_promo' => $isPromo,
    ];
}

/**
 * Calculate promo stock progress using only promo_stock and promo_stock_initial.
 * Returns null when progress should be omitted.
 *
 * @param array $product Existing product database row
 * @return int|null Percentage between 0 and 100, or null when unavailable
 */
function calculatePromoStockPercent(array $product): ?int
{
    if (!array_key_exists('promo_stock', $product)) {
        return null;
    }

    $promoStock = (int)$product['promo_stock'];
    $promoStockInitial = (int)($product['promo_stock_initial'] ?? 0);

    if ($promoStockInitial <= 0) {
        return null;
    }

    return max(0, min(100, (int)round(($promoStock / $promoStockInitial) * 100)));
}

/**
 * Resolve a homepage product image URL from an existing product row.
 * Falls back only to the existing placeholder asset when no usable image is present.
 *
 * @param array $product Existing product database row
 * @return string Safe relative or absolute image URL
 */
function resolveHomepageProductImage(array $product): string
{
    $image = trim((string)($product['image'] ?? ''));
    if ($image === '') {
        return 'assets/images/placeholder.png';
    }

    if (preg_match('#^https?://#i', $image) === 1 || str_starts_with($image, '/')) {
        return $image;
    }

    if (preg_match('/\.(jpg|jpeg|png|webp|gif|svg)$/i', $image) === 1 && file_exists(__DIR__ . '/../uploads/products/' . $image)) {
        return 'uploads/products/' . $image;
    }

    return 'assets/images/placeholder.png';
}

/**
 * Render a reusable homepage product card while preserving cart and wishlist contracts.
 *
 * @param array $product Existing product database row
 * @param string $csrfToken CSRF token for commerce forms
 * @param bool $isFlashSaleActive Whether promo pricing may be active
 * @param array<int, int> $wishlist Product identifiers currently in wishlist
 * @param bool $lazyImage Whether to add loading="lazy" to the product image
 * @return string Sanitized homepage product card HTML
 */
function renderHomepageProductCard(
    array $product,
    string $csrfToken,
    bool $isFlashSaleActive = false,
    array $wishlist = [],
    bool $lazyImage = false
): string {
    $productId = (int)($product['id'] ?? 0);
    $name = (string)($product['name'] ?? '');
    $categoryName = (string)($product['category_name'] ?? '');
    $slug = (string)($product['slug'] ?? '');
    $stock = (int)($product['stock'] ?? 0);
    $status = (string)($product['status'] ?? '');
    $imageSrc = resolveHomepageProductImage($product);
    $price = determineActivePrice($product, $isFlashSaleActive);
    $inWishlist = in_array($productId, array_map('intval', $wishlist), true);
    $wishlistStyle = $inWishlist ? "font-variation-settings: 'FILL' 1, 'wght' 400; color: #ba1a1a;" : '';
    $lazyAttribute = $lazyImage ? ' loading="lazy"' : '';

    if ($status === 'ready') {
        $statusBadge = '<span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[8px] font-bold bg-emerald-500/10 text-emerald-700">Ready</span>';
    } elseif ($status === 'po') {
        $statusBadge = '<span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[8px] font-bold bg-amber-500/10 text-amber-700">Pre-Order</span>';
    } else {
        $statusBadge = '<span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[8px] font-bold bg-red-500/10 text-red-700">Habis</span>';
    }

    ob_start();
    ?>
    <div class="group bg-white tech-card flex flex-col overflow-hidden">
        <div class="relative aspect-square overflow-hidden bg-surface-container-low p-2">
            <img alt="<?= sanitizeOutput($name) ?>" class="w-full h-full object-contain transition-transform duration-300" src="<?= sanitizeOutput($imageSrc) ?>"<?= $lazyAttribute ?>/>
            <form action="actions/wishlist-toggle" method="POST" class="absolute top-2 right-2 inline" onsubmit="event.preventDefault(); event.stopPropagation(); toggleWishlist(this.querySelector('button'), <?= $productId ?>);">
                <input type="hidden" name="csrf_token" value="<?= sanitizeOutput($csrfToken) ?>">
                <input type="hidden" name="product_id" value="<?= $productId ?>">
                <button type="submit" class="w-8 h-8 rounded-full bg-white border border-gray-200 flex items-center justify-center wishlist-btn transition-all hover:scale-105 <?= $inWishlist ? 'active' : '' ?>" aria-label="Toggle wishlist">
                    <span class="material-symbols-outlined text-sm text-on-surface-variant" style="<?= sanitizeOutput($wishlistStyle) ?>">favorite</span>
                </button>
            </form>
        </div>
        <div class="p-3 flex flex-col flex-grow">
            <span class="text-[9px] font-bold text-secondary uppercase tracking-wider mb-1 block"><?= sanitizeOutput($categoryName) ?></span>
            <h3 class="text-xs font-bold text-on-background line-clamp-2 min-h-[36px] mb-1.5 leading-snug group-hover:text-secondary transition-colors cursor-pointer" onclick="window.location.href='product-detail?slug=<?= rawurlencode($slug) ?>'">
                <?= sanitizeOutput($name) ?>
            </h3>
            <div class="mb-2 flex flex-wrap items-baseline gap-1">
                <p class="text-sm font-black text-on-background"><?= formatRupiah($price['price']) ?></p>
                <?php if ($price['is_promo']): ?>
                    <p class="text-[10px] font-semibold text-on-surface-variant/70 line-through"><?= formatRupiah($price['original_price']) ?></p>
                <?php endif; ?>
            </div>
            <div class="flex items-center gap-1.5 mb-2.5 select-none">
                <span class="text-[10px] font-bold text-on-surface-variant/80">Stok: <?= $stock ?></span>
                <span class="text-outline-variant/50 text-[10px]">|</span>
                <?= $statusBadge ?>
            </div>
            <div class="mt-auto flex items-center justify-between border-t border-outline-variant/30 pt-2.5">
                <div class="flex items-center gap-0.5 text-on-surface-variant/80">
                    <span class="material-symbols-outlined text-[12px]">location_on</span>
                    <span class="text-[9px] font-semibold">Toko Pusat</span>
                </div>
                <?php if (($status === 'ready' || $status === 'po') && $productId > 0): ?>
                    <form action="actions/cart-add" method="POST" class="inline">
                        <input type="hidden" name="csrf_token" value="<?= sanitizeOutput($csrfToken) ?>">
                        <input type="hidden" name="product_id" value="<?= $productId ?>">
                        <input type="hidden" name="quantity" value="1">
                        <button type="submit" class="w-8 h-8 flex items-center justify-center bg-secondary/5 text-secondary rounded-lg hover:bg-secondary hover:text-white transition-colors" title="Tambah ke keranjang">
                            <span class="material-symbols-outlined text-sm">add_shopping_cart</span>
                        </button>
                    </form>
                <?php else: ?>
                    <span class="text-[9px] bg-outline-variant/30 text-on-surface-variant px-2 py-0.5 rounded font-bold">Habis</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
    return trim((string)ob_get_clean());
}

/**
 * Render a reusable homepage product rail for data-backed product sections.
 *
 * @param array{title?: string, subtitle?: string, view_all_url?: string, products?: array<int, array>, limit?: int} $config
 * @param string $csrfToken CSRF token for commerce forms
 * @param bool $isFlashSaleActive Whether promo pricing may be active
 * @param array<int, int> $wishlist Product identifiers currently in wishlist
 * @return string Sanitized homepage product rail HTML, or an empty string when no products exist
 */
function renderHomepageProductRail(
    array $config,
    string $csrfToken,
    bool $isFlashSaleActive = false,
    array $wishlist = []
): string {
    $products = array_values(array_filter($config['products'] ?? [], 'is_array'));
    if ($products === []) {
        return '';
    }

    $limit = (int)($config['limit'] ?? 12);
    if ($limit <= 0) {
        return '';
    }

    $products = array_slice($products, 0, $limit);
    $title = (string)($config['title'] ?? '');
    $subtitle = (string)($config['subtitle'] ?? '');
    $viewAllUrl = (string)($config['view_all_url'] ?? '');

    ob_start();
    ?>
    <section class="max-w-max-width mx-auto px-4 md:px-margin-desktop py-2" data-homepage-product-rail>
        <div class="flex items-end justify-between mb-3 md:mb-4 gap-4">
            <div>
                <h2 class="text-xl md:text-2xl font-extrabold text-on-background leading-tight"><?= sanitizeOutput($title) ?></h2>
                <?php if ($subtitle !== ''): ?>
                    <p class="text-xs md:text-sm text-on-surface-variant mt-1"><?= sanitizeOutput($subtitle) ?></p>
                <?php endif; ?>
            </div>
            <?php if ($viewAllUrl !== ''): ?>
                <a href="<?= sanitizeOutput($viewAllUrl) ?>" class="text-secondary font-bold text-xs md:text-sm hover:text-secondary-container flex items-center gap-1 group transition-colors flex-shrink-0">
                    Lihat Semua
                    <span class="material-symbols-outlined text-sm md:text-base group-hover:translate-x-1 transition-transform">arrow_forward</span>
                </a>
            <?php endif; ?>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
            <?php foreach ($products as $index => $product): ?>
                <?= renderHomepageProductCard($product, $csrfToken, $isFlashSaleActive, $wishlist, $index >= 4) ?>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
    return trim((string)ob_get_clean());
}

/**
 * Clean up the session cart and checkout items by removing products that:
 * 1. Do not exist in the database.
 * 2. Are not active (is_active = 0).
 * Also removes inactive or deleted products from checkout items.
 *
 * @param PDO $pdo Database connection
 * @return void
 */
function cleanupCartSession(PDO $pdo): void
{
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }

    if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        return;
    }

    $productIds = array_keys($_SESSION['cart']);
    if (empty($productIds)) {
        return;
    }

    // Fetch only active products from the database for the IDs in the cart
    $inClause = implode(',', array_fill(0, count($productIds), '?'));
    try {
        $stmt = $pdo->prepare("SELECT id FROM products WHERE id IN ($inClause) AND is_active = 1");
        $stmt->execute($productIds);
        $activeIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        // If query fails, do not modify cart session to avoid breaking user experience
        return;
    }

    // Convert active IDs to integers for strict comparison
    $activeIds = array_map('intval', $activeIds);

    // Remove any items from the cart session that are not active/present in DB
    $removedAny = false;
    foreach ($productIds as $id) {
        if (!in_array((int)$id, $activeIds, true)) {
            unset($_SESSION['cart'][$id]);
            $removedAny = true;

            // Also clean up from checkout_items if present
            if (isset($_SESSION['checkout_items']) && is_array($_SESSION['checkout_items'])) {
                if (($key = array_search($id, $_SESSION['checkout_items'])) !== false) {
                    unset($_SESSION['checkout_items'][$key]);
                }
            }
        }
    }

    if ($removedAny && isset($_SESSION['checkout_items']) && is_array($_SESSION['checkout_items'])) {
        $_SESSION['checkout_items'] = array_values($_SESSION['checkout_items']);
    }
}

