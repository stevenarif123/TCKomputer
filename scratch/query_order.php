<?php
require 'config/db.php';
$_ENV['DB_HOST'] = '127.0.0.1'; // Try forcing IPv4
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT * FROM orders ORDER BY id DESC LIMIT 1");
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmtItems = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
    $stmtItems->execute([$order['id']]);
    $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    echo "ORDER:\n";
    print_r($order);
    echo "\nITEMS:\n";
    print_r($items);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
