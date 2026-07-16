<?php
/**
 * Admin Panel Chat API Endpoint
 * Handles chat data operations for the admin panel.
 */
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/admin-auth.php';

// Verify admin login status
if (!isAdminLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$pdo = getDBConnection();
$action = $_GET['action'] ?? '';

try {
    // 1. Fetch chat sessions
    if ($action === 'sessions') {
        $status = $_GET['status'] ?? 'active'; // active, closed, archived, or '' for all
        $search = trim($_GET['search'] ?? '');
        
        $where = [];
        $params = [];
        
        if ($status !== '') {
            $where[] = 'status = ?';
            $params[] = $status;
        }
        
        if ($search !== '') {
            $where[] = '(user_name LIKE ? OR user_phone LIKE ? OR user_email LIKE ?)';
            $searchTerm = '%' . $search . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $query = "SELECT * FROM chat_sessions 
                  $whereClause 
                  ORDER BY last_message_at DESC";
                  
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $sessions = $stmt->fetchAll();
        
        // Add relative time/formatted last message preview
        foreach ($sessions as &$session) {
            // Fetch last message
            $stmtLast = $pdo->prepare("SELECT message, sender_type, created_at FROM chat_messages WHERE session_id = ? ORDER BY id DESC LIMIT 1");
            $stmtLast->execute([$session['id']]);
            $lastMsg = $stmtLast->fetch();
            
            $session['last_msg_text'] = $lastMsg ? $lastMsg['message'] : 'Belum ada pesan.';
            $session['last_msg_sender'] = $lastMsg ? $lastMsg['sender_type'] : '';
            $session['last_msg_time'] = $lastMsg ? date('H:i', strtotime($lastMsg['created_at'])) : '';
        }
        
        echo json_encode(['success' => true, 'sessions' => $sessions]);
        exit;
    }
    
    // 2. Fetch conversation messages
    if ($action === 'messages') {
        $sessionId = (int)($_GET['session_id'] ?? 0);
        $afterId = (int)($_GET['after_id'] ?? 0);
        
        if ($sessionId <= 0) {
            echo json_encode(['success' => false, 'message' => 'session_id tidak valid.']);
            exit;
        }
        
        // Mark session as read by admin
        $stmtClear = $pdo->prepare("UPDATE chat_sessions SET unread_admin = 0 WHERE id = ?");
        $stmtClear->execute([$sessionId]);
        
        // Mark messages from user as read
        $stmtRead = $pdo->prepare("UPDATE chat_messages SET is_read = 1 WHERE session_id = ? AND sender_type = 'user' AND is_read = 0");
        $stmtRead->execute([$sessionId]);
        
        // Fetch messages
        $stmtMsgs = $pdo->prepare("SELECT * FROM chat_messages WHERE session_id = ? AND id > ? ORDER BY id ASC");
        $stmtMsgs->execute([$sessionId, $afterId]);
        $messages = $stmtMsgs->fetchAll();
        
        foreach ($messages as &$msg) {
            $msg['time'] = date('H:i', strtotime($msg['created_at']));
        }
        
        echo json_encode(['success' => true, 'messages' => $messages]);
        exit;
    }
    
    // 3. Send admin reply
    if ($action === 'send') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Metode request tidak valid.']);
            exit;
        }
        
        $sessionId = (int)($_POST['session_id'] ?? 0);
        $message = trim($_POST['message'] ?? '');
        
        if ($sessionId <= 0 || empty($message)) {
            echo json_encode(['success' => false, 'message' => 'Pesan atau session_id kosong.']);
            exit;
        }
        
        $pdo->beginTransaction();
        
        // Insert message
        $stmtMsg = $pdo->prepare("INSERT INTO chat_messages (session_id, sender_type, sender_name, message, created_at) 
                                  VALUES (?, 'admin', 'Admin', ?, NOW())");
        $stmtMsg->execute([$sessionId, $message]);
        $messageId = (int)$pdo->lastInsertId();
        
        // Update session: increment user unread, update last message time
        $stmtSession = $pdo->prepare("UPDATE chat_sessions 
                                      SET last_message_at = NOW(), 
                                          unread_user = unread_user + 1, 
                                          unread_admin = 0,
                                          updated_at = NOW() 
                                      WHERE id = ?");
        $stmtSession->execute([$sessionId]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message_id' => $messageId,
            'created_at' => date('H:i')
        ]);
        exit;
    }
    
    // 4. Close chat session
    if ($action === 'close') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Metode request tidak valid.']);
            exit;
        }
        
        $sessionId = (int)($_POST['session_id'] ?? 0);
        if ($sessionId <= 0) {
            echo json_encode(['success' => false, 'message' => 'session_id tidak valid.']);
            exit;
        }
        
        $pdo->beginTransaction();
        
        // Update session status
        $stmtSession = $pdo->prepare("UPDATE chat_sessions SET status = 'closed', updated_at = NOW() WHERE id = ?");
        $stmtSession->execute([$sessionId]);
        
        // Insert system message
        $stmtMsg = $pdo->prepare("INSERT INTO chat_messages (session_id, sender_type, sender_name, message, created_at) 
                                  VALUES (?, 'system', 'System', 'Sesi chat ini telah ditutup oleh Admin.', NOW())");
        $stmtMsg->execute([$sessionId]);
        
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Sesi chat ditutup.']);
        exit;
    }
    
    // 5. Total admin unread count
    if ($action === 'unread_count') {
        $unreadCount = (int)$pdo->query("SELECT COALESCE(SUM(unread_admin), 0) FROM chat_sessions WHERE status = 'active'")->fetchColumn();
        echo json_encode(['success' => true, 'unread_count' => $unreadCount]);
        exit;
    }
    
    echo json_encode(['success' => false, 'message' => 'Action tidak valid.']);
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error in admin chat-api: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan internal server.']);
    exit;
}
