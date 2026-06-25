<?php
/**
 * DIAGNOSTIC: Query the latest order and its items from the database
 * Also check for any active promotions and database triggers
 */
require_once __DIR__ . '/config/db.php';
$pdo = getDBConnection();

header('Content-Type: text/plain; charset=utf-8');

echo "=== LATEST ORDER ===\n";
$stmt = $pdo->query("SELECT * FROM orders ORDER BY id DESC LIMIT 1");
$order = $stmt->fetch(PDO::FETCH_ASSOC);
if ($order) {
    foreach ($order as $k => $v) {
        echo "  {$k}: {$v}\n";
    }
    
    echo "\n=== ORDER ITEMS for order #{$order['id']} ===\n";
    $stmtItems = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC");
    $stmtItems->execute([$order['id']]);
    $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
    foreach ($items as $i => $item) {
        echo "  Item " . ($i+1) . ":\n";
        foreach ($item as $k => $v) {
            echo "    {$k}: {$v}\n";
        }
    }
} else {
    echo "  No orders found\n";
}

echo "\n=== ALL ACTIVE PROMOTIONS ===\n";
$stmt = $pdo->query("SELECT * FROM promotions WHERE is_active = 1");
$promos = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($promos)) {
    echo "  NONE - No active promotions\n";
} else {
    foreach ($promos as $promo) {
        echo "  ID={$promo['id']} name={$promo['name']} type={$promo['promo_type']} ";
        echo "start={$promo['start_date']} end={$promo['end_date']} ";
        echo "free_item_id=" . ($promo['free_item_id'] ?? 'NULL') . "\n";
    }
}

echo "\n=== ALL PROMOTIONS (active + inactive) ===\n";
$stmt = $pdo->query("SELECT id, name, promo_type, is_active, start_date, end_date, free_item_id, min_spend FROM promotions ORDER BY id");
$promos = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($promos)) {
    echo "  NONE - Promotions table is completely empty\n";
} else {
    foreach ($promos as $promo) {
        $active = $promo['is_active'] ? 'ACTIVE' : 'INACTIVE';
        echo "  [{$active}] ID={$promo['id']} name={$promo['name']} type={$promo['promo_type']} ";
        echo "start={$promo['start_date']} end={$promo['end_date']} ";
        echo "free_item_id=" . ($promo['free_item_id'] ?? 'NULL') . " ";
        echo "min_spend={$promo['min_spend']}\n";
    }
}

echo "\n=== DATABASE TRIGGERS ===\n";
$stmt = $pdo->query("SHOW TRIGGERS");
$triggers = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($triggers)) {
    echo "  NONE - No database triggers\n";
} else {
    foreach ($triggers as $t) {
        echo "  Trigger: {$t['Trigger']} on {$t['Table']} ({$t['Event']} {$t['Timing']})\n";
        echo "    Statement: {$t['Statement']}\n";
    }
}

echo "\n=== DONE ===\n";
