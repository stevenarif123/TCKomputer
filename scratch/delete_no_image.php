<?php
require_once __DIR__ . '/../config/db.php';
$pdo = getDBConnection();

// Check how many products don't have images
$stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE image IS NULL OR image = '' OR image = 'no-image.jpg' OR image = 'default.jpg'");
$count = $stmt->fetchColumn();
echo "Found $count products without images.\n";

if ($count > 0) {
    // Delete them
    $deleted = $pdo->exec("DELETE FROM products WHERE image IS NULL OR image = '' OR image = 'no-image.jpg' OR image = 'default.jpg'");
    echo "Deleted $deleted products.\n";
} else {
    echo "No products to delete.\n";
}
