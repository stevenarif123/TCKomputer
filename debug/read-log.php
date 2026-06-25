<?php
/**
 * DIAGNOSTIC: Read the checkout debug log
 * This endpoint reads and displays the log file content
 */
header('Content-Type: text/plain; charset=utf-8');

$logFile = __DIR__ . '/checkout_debug.log';

if (!file_exists($logFile)) {
    echo "Log file does not exist yet.\n";
    echo "Path checked: {$logFile}\n";
    echo "\nPlease perform a checkout and then revisit this page.\n";
} else {
    $size = filesize($logFile);
    echo "Log file size: {$size} bytes\n";
    echo "Last modified: " . date('Y-m-d H:i:s', filemtime($logFile)) . "\n";
    echo "\n=== LOG CONTENT (last 10000 chars) ===\n\n";
    $content = file_get_contents($logFile);
    // Show last 10000 chars to avoid huge output
    if (strlen($content) > 10000) {
        echo "...[truncated, showing last 10000 chars]...\n\n";
        echo substr($content, -10000);
    } else {
        echo $content;
    }
}
