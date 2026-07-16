<?php
/**
 * Migration Script for Live Chat System
 * Run this via terminal or browser to create chat database tables.
 */
require_once __DIR__ . '/../config/db.php';

$pdo = getDBConnection();
$messages = [];

try {
    $pdo->beginTransaction();

    // 1. Create chat_sessions table
    $sql1 = "
        CREATE TABLE IF NOT EXISTS `chat_sessions` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `session_token` VARCHAR(64) NOT NULL,
            `user_id` INT UNSIGNED NULL,
            `user_name` VARCHAR(100) NOT NULL,
            `user_phone` VARCHAR(20) NULL,
            `user_email` VARCHAR(100) NULL,
            `status` ENUM('active','closed','archived') NOT NULL DEFAULT 'active',
            `last_message_at` TIMESTAMP NULL,
            `unread_admin` INT NOT NULL DEFAULT 0,
            `unread_user` INT NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_session_token` (`session_token`),
            INDEX `idx_chat_sessions_status` (`status`),
            INDEX `idx_chat_sessions_user_id` (`user_id`),
            CONSTRAINT `fk_chat_sessions_user` FOREIGN KEY (`user_id`) 
                REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sql1);
    $messages[] = "Table `chat_sessions` created or already exists.";

    // 2. Create chat_messages table
    $sql2 = "
        CREATE TABLE IF NOT EXISTS `chat_messages` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `session_id` INT UNSIGNED NOT NULL,
            `sender_type` ENUM('user','admin','system','ai') NOT NULL,
            `sender_name` VARCHAR(100) NOT NULL,
            `message` TEXT NOT NULL,
            `is_read` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_chat_messages_session_id` (`session_id`),
            CONSTRAINT `fk_chat_messages_session` FOREIGN KEY (`session_id`) 
                REFERENCES `chat_sessions` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sql2);
    $messages[] = "Table `chat_messages` created or already exists.";

    $pdo->commit();
    $messages[] = "Migration completed successfully!";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $messages[] = "ERROR: " . $e->getMessage();
}

// Display results
if (php_sapi_name() === 'cli') {
    foreach ($messages as $msg) {
        echo $msg . "\n";
    }
} else {
    echo "<h1>Chat System Migration Result</h1><ul>";
    foreach ($messages as $msg) {
        $color = strpos($msg, 'ERROR') !== false ? 'red' : 'green';
        echo "<li style='color: $color;'>$msg</li>";
    }
    echo "</ul>";
}
