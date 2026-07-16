<?php
/**
 * TCKomputer API v1 Bootstrap
 * Handlers API initialization, CORS, Bearer Authentication, Rate Limiting, and unified JSON responses.
 */

// Enable CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PATCH, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

header("Content-Type: application/json; charset=UTF-8");

// Load Core PHP Files
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../config/helpers.php';

// Initialize DB Connection
try {
    $pdo = getDBConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed'
    ]);
    exit();
}

/**
 * Send JSON response and exit
 */
function apiResponse(string $status, $dataOrMessage, int $statusCode = 200) {
    http_response_code($statusCode);
    $response = ['status' => $status];
    if ($status === 'success') {
        $response['data'] = $dataOrMessage;
    } else {
        $response['message'] = $dataOrMessage;
    }
    echo json_encode($response);
    exit();
}

/**
 * Send success JSON response
 */
function apiSuccess($data, int $statusCode = 200) {
    apiResponse('success', $data, $statusCode);
}

/**
 * Send error JSON response
 */
function apiError(string $message, int $statusCode = 400) {
    apiResponse('error', $message, $statusCode);
}

/**
 * Validate API Key (Bearer Token)
 */
function validateApiKey() {
    global $pdo;
    
    $apiKeyEnv = $_ENV['API_KEY'] ?? '';
    if (empty($apiKeyEnv)) {
        apiError('API key not configured in .env', 500);
    }
    
    // Extract Authorization header
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (empty($authHeader) && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }
    
    if (empty($authHeader)) {
        apiError('Missing Authorization header', 401);
    }
    
    if (!preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
        apiError('Invalid Authorization header format', 401);
    }
    
    $token = trim($matches[1]);
    
    if (!hash_equals($apiKeyEnv, $token)) {
        apiError('Unauthorized', 401);
    }
}

/**
 * Rate Limiting for API Requests
 */
function checkApiRateLimit() {
    global $pdo;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $action = 'api_request';
    $key = buildRateLimitKey($action, 'global_api', $ip);
    
    // Allow 120 API requests per 1 minute (60 seconds)
    $rate = checkRateLimit($pdo, $action, $key, 120, 60);
    if (!$rate['allowed']) {
        header("Retry-After: " . $rate['retry_after']);
        apiError('Too Many Requests. Please retry after ' . $rate['retry_after'] . ' seconds.', 429);
    }
    
    // Record request attempt to count towards the rate limit
    recordAuthAttempt($pdo, $action, $key);
}

// Validate & throttle immediately upon inclusion
validateApiKey();
checkApiRateLimit();
