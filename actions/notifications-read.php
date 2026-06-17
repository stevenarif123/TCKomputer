<?php
/**
 * Mark Notifications as Read Endpoint
 */

session_start();
header('Content-Type: application/json');

if (isset($_SESSION['notifications']) && is_array($_SESSION['notifications'])) {
    foreach ($_SESSION['notifications'] as &$notif) {
        $notif['unread'] = false;
    }
    unset($notif); // break reference
}

echo json_encode(['success' => true]);
exit;
