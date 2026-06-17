<?php
/**
 * Production Migration Script for Promotions Engine
 * Upload this file to production and run it via browser: https://yourdomain.com/migrate_promotions_prod.php
 * WARNING: Delete this file after successful migration.
 */

require_once __DIR__ . '/config/db.php';

$pdo = getDBConnection();
$messages = [];

try {
    $pdo->beginTransaction();

    // 1. Create promotions table
    $sql1 = "
        CREATE TABLE IF NOT EXISTS `promotions` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(255) NOT NULL,
            `description` TEXT NULL,
            `promo_type` ENUM('free_shipping', 'category_discount', 'free_item', 'cart_discount') NOT NULL,
            `discount_type` ENUM('percentage', 'fixed') NOT NULL DEFAULT 'fixed',
            `discount_value` INT NOT NULL DEFAULT 0,
            `min_spend` INT NOT NULL DEFAULT 0,
            `target_category_id` INT UNSIGNED NULL,
            `free_item_id` INT UNSIGNED NULL,
            `start_date` DATETIME NOT NULL,
            `end_date` DATETIME NOT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            CONSTRAINT `fk_promo_category` FOREIGN KEY (`target_category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
            CONSTRAINT `fk_promo_free_item` FOREIGN KEY (`free_item_id`) REFERENCES `products` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sql1);
    $messages[] = "Table `promotions` created or already exists.";

    // 2. Add discount_amount to orders
    $stmt2 = $pdo->query("SHOW COLUMNS FROM `orders` LIKE 'discount_amount'");
    if ($stmt2->rowCount() == 0) {
        $pdo->exec("ALTER TABLE `orders` ADD `discount_amount` INT NOT NULL DEFAULT 0 AFTER `shipping_cost`");
        $messages[] = "Column `discount_amount` added to `orders`.";
    } else {
        $messages[] = "Column `discount_amount` already exists in `orders`.";
    }

    // 3. Add applied_promotions to orders
    $stmt3 = $pdo->query("SHOW COLUMNS FROM `orders` LIKE 'applied_promotions'");
    if ($stmt3->rowCount() == 0) {
        $pdo->exec("ALTER TABLE `orders` ADD `applied_promotions` TEXT NULL AFTER `discount_amount`");
        $messages[] = "Column `applied_promotions` added to `orders`.";
    } else {
        $messages[] = "Column `applied_promotions` already exists in `orders`.";
    }

    $pdo->commit();
    $messages[] = "Migration completed successfully!";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $messages[] = "ERROR: " . $e->getMessage();
}

// Display results
echo "<h1>Promotions Migration Result</h1><ul>";
foreach ($messages as $msg) {
    $color = strpos($msg, 'ERROR') !== false ? 'red' : 'green';
    echo "<li style='color: $color;'>$msg</li>";
}
echo "</ul><br><p><strong>Please delete this file (migrate_promotions_prod.php) after running it for security.</strong></p>";
