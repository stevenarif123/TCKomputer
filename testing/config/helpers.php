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
