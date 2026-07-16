<?php
/**
 * User Chat Polling Endpoint
 * Polls for new messages since last received message ID.
 */
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';

$pdo = getDBConnection();
$sessionToken = trim($_GET['session_token'] ?? '');
$afterId = max(0, (int)($_GET['after_id'] ?? 0));

$userId = $_SESSION['customer_id'] ?? null;
$sessionId = null;

try {
    if ($userId !== null) {
        // Logged-in user session
        $stmt = $pdo->prepare("SELECT id FROM chat_sessions WHERE user_id = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$userId]);
        $sessionId = $stmt->fetchColumn();
    } else {
        // Anonymous session
        if (!empty($sessionToken)) {
            $stmt = $pdo->prepare("SELECT id FROM chat_sessions WHERE session_token = ? LIMIT 1");
            $stmt->execute([$sessionToken]);
            $sessionId = $stmt->fetchColumn();
        }
    }
    
    if (!$sessionId) {
        echo json_encode([
            'success' => true,
            'messages' => [],
            'unread_count' => 0
        ]);
        exit;
    }
    
    $isOpen = (int)($_GET['is_open'] ?? 0);
    
    // Fetch new messages
    $stmtMsgs = $pdo->prepare("SELECT id, sender_type, sender_name, message, is_read, created_at 
                                FROM chat_messages 
                                WHERE session_id = ? AND id > ? 
                                ORDER BY id ASC");
    $stmtMsgs->execute([$sessionId, $afterId]);
    $messages = $stmtMsgs->fetchAll();
    
    // Format timestamp for frontend
    foreach ($messages as &$msg) {
        $msg['time'] = date('H:i', strtotime($msg['created_at']));
    }
    
    if ($isOpen === 1) {
        // Update unread status for messages sent by admin or AI or system
        $stmtUpdateMsgs = $pdo->prepare("UPDATE chat_messages 
                                         SET is_read = 1 
                                         WHERE session_id = ? AND sender_type IN ('admin', 'ai', 'system') AND is_read = 0");
        $stmtUpdateMsgs->execute([$sessionId]);
        
        // Reset user unread counter
        $stmtUpdateSession = $pdo->prepare("UPDATE chat_sessions SET unread_user = 0 WHERE id = ?");
        $stmtUpdateSession->execute([$sessionId]);
    }
    
    // Get unread user counter
    $stmtSession = $pdo->prepare("SELECT unread_user FROM chat_sessions WHERE id = ?");
    $stmtSession->execute([$sessionId]);
    $unreadCount = ($isOpen === 1) ? 0 : (int)$stmtSession->fetchColumn();
    
    // Get last read message ID of the user (for double checkmarks)
    $stmtLastRead = $pdo->prepare("SELECT MAX(id) FROM chat_messages WHERE session_id = ? AND sender_type = 'user' AND is_read = 1");
    $stmtLastRead->execute([$sessionId]);
    $lastReadId = (int)$stmtLastRead->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'messages' => $messages,
        'unread_count' => $unreadCount,
        'last_read_id' => $lastReadId
    ]);
    exit;
    
} catch (Exception $e) {
    error_log('Error in chat-poll: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan sistem.']);
    exit;
}
