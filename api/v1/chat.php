<?php
/**
 * TCKomputer API v1 - Chat Endpoint
 * Enables AI agent to fetch active chats, message histories, and reply to chats.
 */
require_once __DIR__ . '/bootstrap.php';

// Check if tables exist
$checkTable = $pdo->query("SHOW TABLES LIKE 'chat_sessions'")->fetch();
if (!$checkTable) {
    apiError('Chat database tables have not been migrated yet.', 503);
}

// 1. Single session details (GET /api/v1/chat.php?session_id=X)
if (isset($_GET['session_id'])) {
    $sessionId = (int)$_GET['session_id'];
    
    // Fetch session details
    $stmtSession = $pdo->prepare("SELECT * FROM chat_sessions WHERE id = ?");
    $stmtSession->execute([$sessionId]);
    $session = $stmtSession->fetch();
    
    if (!$session) {
        apiError('Chat session not found', 404);
    }
    
    // Fetch messages
    $afterId = isset($_GET['after_id']) ? (int)$_GET['after_id'] : 0;
    $stmtMsgs = $pdo->prepare("SELECT * FROM chat_messages WHERE session_id = ? AND id > ? ORDER BY id ASC");
    $stmtMsgs->execute([$sessionId, $afterId]);
    $messages = $stmtMsgs->fetchAll();
    
    // Optional: Mark admin unread as 0 when AI fetches
    if ($session['unread_admin'] > 0) {
        $stmtClear = $pdo->prepare("UPDATE chat_sessions SET unread_admin = 0 WHERE id = ?");
        $stmtClear->execute([$sessionId]);
        $session['unread_admin'] = 0;
    }
    
    apiSuccess([
        'session' => $session,
        'messages' => $messages
    ]);
}

// 2. Query sessions list (GET /api/v1/chat.php)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $unread = isset($_GET['unread']) ? (int)$_GET['unread'] : 0;
    $status = $_GET['status'] ?? 'active'; // active, closed, archived, or '' for all
    
    $where = [];
    $params = [];
    
    if ($unread === 1) {
        $where[] = 'unread_admin > 0';
    }
    
    if ($status !== '') {
        $where[] = 'status = ?';
        $params[] = $status;
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $query = "SELECT * FROM chat_sessions 
              $whereClause 
              ORDER BY last_message_at DESC";
              
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $sessions = $stmt->fetchAll();
    
    apiSuccess($sessions);
}

// 3. Send AI reply (POST /api/v1/chat.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json = file_get_contents('php://input');
    $body = json_decode($json, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        apiError('Invalid JSON body');
    }
    
    $sessionId = isset($body['session_id']) ? (int)$body['session_id'] : 0;
    $message = trim($body['message'] ?? '');
    
    if ($sessionId <= 0) {
        apiError('Valid session_id is required');
    }
    if (empty($message)) {
        apiError('Message cannot be empty');
    }
    
    // Check if session exists
    $stmtCheck = $pdo->prepare("SELECT * FROM chat_sessions WHERE id = ?");
    $stmtCheck->execute([$sessionId]);
    $session = $stmtCheck->fetch();
    
    if (!$session) {
        apiError('Chat session not found', 404);
    }
    
    try {
        $pdo->beginTransaction();
        
        // Insert message
        $stmtInsert = $pdo->prepare("INSERT INTO chat_messages (session_id, sender_type, sender_name, message, created_at)
                                     VALUES (?, 'ai', 'AI Assistant', ?, NOW())");
        $stmtInsert->execute([$sessionId, $message]);
        $messageId = (int)$pdo->lastInsertId();
        
        // Update session: set unread_admin = 0, increment unread_user, update last_message_at
        $stmtUpdate = $pdo->prepare("UPDATE chat_sessions 
                                     SET unread_admin = 0, 
                                         unread_user = unread_user + 1, 
                                         last_message_at = NOW(),
                                         updated_at = NOW() 
                                     WHERE id = ?");
        $stmtUpdate->execute([$sessionId]);
        
        $pdo->commit();
        
        apiSuccess([
            'message' => 'AI reply sent successfully',
            'message_id' => $messageId,
            'created_at' => date('Y-m-d H:i:s')
        ], 201);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        apiError('Failed to send AI reply: ' . $e->getMessage(), 500);
    }
}
