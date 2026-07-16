<?php
/**
 * TCKomputer API v1 - Users Endpoint
 * Handles checking user registration status and registering users from chat.
 */
require_once __DIR__ . '/bootstrap.php';

// Check if a phone number is registered (GET /api/v1/users.php?phone=08xxx)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_GET['phone'])) {
        apiError('Missing phone parameter');
    }
    
    $phone = trim($_GET['phone']);
    if (empty($phone)) {
        apiError('Phone parameter cannot be empty');
    }
    
    $stmt = $pdo->prepare("SELECT id, username, phone, name, email, address, shipping_area_id, created_at 
                           FROM users 
                           WHERE phone = ?");
    $stmt->execute([$phone]);
    $user = $stmt->fetch();
    
    if ($user) {
        apiSuccess([
            'registered' => true,
            'user' => $user
        ]);
    } else {
        apiSuccess([
            'registered' => false
        ]);
    }
}

// Register a user (POST /api/v1/users.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json = file_get_contents('php://input');
    $body = json_decode($json, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        apiError('Invalid JSON body');
    }
    
    $username = trim($body['username'] ?? '');
    $phone = trim($body['phone'] ?? '');
    $name = trim($body['name'] ?? '');
    $email = trim($body['email'] ?? '');
    $address = trim($body['address'] ?? '');
    $password = $body['password'] ?? '';
    $shippingAreaId = isset($body['shipping_area_id']) && $body['shipping_area_id'] !== '' ? (int)$body['shipping_area_id'] : null;
    $chatSessionId = isset($body['chat_session_id']) && $body['chat_session_id'] !== '' ? (int)$body['chat_session_id'] : null;
    
    // Required fields check
    if (empty($username)) apiError('Username is required');
    if (empty($phone)) apiError('Phone number is required');
    if (empty($name)) apiError('Full name is required');
    if (empty($email)) apiError('Email is required');
    if (empty($address)) apiError('Address is required');
    if (empty($password)) apiError('Password is required');
    if ($shippingAreaId === null || $shippingAreaId <= 0) apiError('Valid shipping_area_id is required');
    
    // Username validation
    if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
        apiError('Username must be alphanumeric with underscores, and between 3-30 characters');
    }
    
    // Password validation
    if (strlen($password) < 6) {
        apiError('Password must be at least 6 characters');
    }
    
    // Email validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        apiError('Invalid email format');
    }
    
    // Phone validation
    if (function_exists('isValidPhoneNumber')) {
        if (!isValidPhoneNumber($phone)) {
            apiError('Invalid phone number format');
        }
    } else {
        if (!preg_match('/^(08|\+628)\d{8,13}$/', $phone)) {
            apiError('Invalid phone number format');
        }
    }
    
    // Check duplicates
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetchColumn() > 0) {
        apiError('Username is already taken');
    }
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE phone = ?");
    $stmt->execute([$phone]);
    if ($stmt->fetchColumn() > 0) {
        apiError('Phone number is already registered');
    }
    
    // Begin transaction
    try {
        $pdo->beginTransaction();
        
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        
        // Insert user
        $stmtInsert = $pdo->prepare("INSERT INTO users (username, phone, name, email, address, shipping_area_id, password, created_at)
                                     VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmtInsert->execute([
            $username, $phone, $name, $email, $address, $shippingAreaId, $passwordHash
        ]);
        
        $userId = (int)$pdo->lastInsertId();
        
        // If chat session ID is provided, link it
        if ($chatSessionId !== null && $chatSessionId > 0) {
            // Verify table existence first to avoid errors if run before migration
            $checkTable = $pdo->query("SHOW TABLES LIKE 'chat_sessions'")->fetch();
            if ($checkTable) {
                $stmtChat = $pdo->prepare("UPDATE chat_sessions SET user_id = ?, user_name = ?, user_phone = ? WHERE id = ?");
                $stmtChat->execute([$userId, $name, $phone, $chatSessionId]);
            }
        }
        
        $pdo->commit();
        
        apiSuccess([
            'message' => 'User registered successfully',
            'user_id' => $userId,
            'username' => $username
        ], 201);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        apiError('Registration failed: ' . $e->getMessage(), 500);
    }
}
