<?php
/**
 * User Chat Action Endpoint
 * Handles chat initialization (pre-chat form) and sending messages.
 */
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/security.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Metode request tidak valid.']);
    exit;
}

// CSRF validation
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Permintaan tidak valid, silakan muat ulang halaman.']);
    exit;
}

$pdo = getDBConnection();
$action = $_GET['action'] ?? 'send';

// 1. INITIALIZE ANONYMOUS SESSION
if ($action === 'init') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Nama wajib diisi.']);
        exit;
    }
    if (empty($phone)) {
        echo json_encode(['success' => false, 'message' => 'Nomor HP wajib diisi.']);
        exit;
    }
    if (!empty($phone) && !isValidPhoneNumber($phone)) {
        echo json_encode(['success' => false, 'message' => 'Format nomor telepon tidak valid.']);
        exit;
    }
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Format email tidak valid.']);
        exit;
    }
    
    // Generate secure session token
    $sessionToken = bin2hex(random_bytes(32));
    
    try {
        $stmt = $pdo->prepare("INSERT INTO chat_sessions (session_token, user_name, user_phone, user_email, status, last_message_at) 
                               VALUES (?, ?, ?, ?, 'active', NOW())");
        $stmt->execute([$sessionToken, $name, $phone, $email ?: null]);
        
        echo json_encode([
            'success' => true,
            'session_token' => $sessionToken,
            'user_name' => $name
        ]);
        exit;
    } catch (Exception $e) {
        error_log('Error in chat-send init: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Gagal memulai chat.']);
        exit;
    }
}

// 2. SEND MESSAGE
if ($action === 'send') {
    $message = trim($_POST['message'] ?? '');
    $sessionToken = trim($_POST['session_token'] ?? '');
    
    if (empty($message)) {
        echo json_encode(['success' => false, 'message' => 'Pesan tidak boleh kosong.']);
        exit;
    }
    
    // Rate limit check
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $rlKey = buildRateLimitKey('chat_send', $sessionToken ?: ($_SESSION['customer_id'] ?? 'anonymous'), $ip);
    $rl = checkRateLimit($pdo, 'chat_send', $rlKey, 30, 60); // 30 messages per minute
    if (!$rl['allowed']) {
        echo json_encode(['success' => false, 'message' => 'Kirim pesan terlalu cepat. Coba lagi dalam ' . $rl['retry_after'] . ' detik.']);
        exit;
    }
    
    $sessionId = null;
    $senderName = 'Guest';
    $userId = $_SESSION['customer_id'] ?? null;
    
    try {
        // If user is logged in, find or create session
        if ($userId !== null) {
            $stmt = $pdo->prepare("SELECT id, user_name FROM chat_sessions WHERE user_id = ? AND status = 'active' LIMIT 1");
            $stmt->execute([$userId]);
            $session = $stmt->fetch();
            
            if ($session) {
                $sessionId = (int)$session['id'];
                $senderName = $session['user_name'];
            } else {
                // Create a new session for this user
                $sessionToken = bin2hex(random_bytes(32));
                $profile = $_SESSION['customer_profile'] ?? [];
                $senderName = $profile['name'] ?? $profile['username'] ?? 'User';
                $phone = $profile['phone'] ?? null;
                $email = $profile['email'] ?? null;
                
                $stmtInsert = $pdo->prepare("INSERT INTO chat_sessions (session_token, user_id, user_name, user_phone, user_email, status, last_message_at) 
                                             VALUES (?, ?, ?, ?, ?, 'active', NOW())");
                $stmtInsert->execute([$sessionToken, $userId, $senderName, $phone, $email]);
                $sessionId = (int)$pdo->lastInsertId();
            }
        } else {
            // Anonymous chat, must have session token
            if (empty($sessionToken)) {
                echo json_encode(['success' => false, 'message' => 'Sesi chat tidak valid.']);
                exit;
            }
            
            $stmt = $pdo->prepare("SELECT id, user_name, status FROM chat_sessions WHERE session_token = ? LIMIT 1");
            $stmt->execute([$sessionToken]);
            $session = $stmt->fetch();
            
            if (!$session) {
                echo json_encode(['success' => false, 'message' => 'Sesi chat tidak ditemukan.']);
                exit;
            }
            
            if ($session['status'] !== 'active') {
                echo json_encode(['success' => false, 'message' => 'Sesi chat ini sudah ditutup.']);
                exit;
            }
            
            $sessionId = (int)$session['id'];
            $senderName = $session['user_name'];
        }
        
        // Begin Transaction
        $pdo->beginTransaction();
        
        // Insert message
        $stmtMsg = $pdo->prepare("INSERT INTO chat_messages (session_id, sender_type, sender_name, message, created_at) 
                                  VALUES (?, 'user', ?, ?, NOW())");
        $stmtMsg->execute([$sessionId, $senderName, $message]);
        $messageId = (int)$pdo->lastInsertId();
        
        // Update session
        $stmtSessionUpdate = $pdo->prepare("UPDATE chat_sessions 
                                            SET last_message_at = NOW(), 
                                                unread_admin = unread_admin + 1,
                                                updated_at = NOW() 
                                            WHERE id = ?");
        $stmtSessionUpdate->execute([$sessionId]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message_id' => $messageId,
            'created_at' => date('H:i')
        ]);
        exit;
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Error in chat-send message: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan saat mengirim pesan.']);
        exit;
    }
}
