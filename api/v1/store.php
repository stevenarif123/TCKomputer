<?php
/**
 * TCKomputer API v1 - Store Endpoint
 * Handles fetching store settings, shipping areas, and FAQs.
 */
require_once __DIR__ . '/bootstrap.php';

// If shipping areas requested
if (isset($_GET['shipping_areas'])) {
    $active = isset($_GET['is_active']) ? (int)$_GET['is_active'] : 1;
    $where = [];
    $params = [];
    if ($active !== -1) {
        $where[] = 'is_active = ?';
        $params[] = $active;
    }
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $stmt = $pdo->prepare("SELECT id, area_name, regency, cost, is_active FROM shipping_areas $whereClause ORDER BY regency ASC, area_name ASC");
    $stmt->execute($params);
    apiSuccess($stmt->fetchAll());
}

// If FAQs requested
if (isset($_GET['faqs'])) {
    $active = isset($_GET['is_active']) ? (int)$_GET['is_active'] : 1;
    $where = [];
    $params = [];
    if ($active !== -1) {
        $where[] = 'f.is_active = ?';
        $params[] = $active;
    }
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $query = "SELECT f.id, f.faq_category_id, f.question, f.answer, f.sort_order, f.is_active, fc.name AS category_name 
              FROM faqs f 
              LEFT JOIN faq_categories fc ON f.faq_category_id = fc.id 
              $whereClause 
              ORDER BY fc.sort_order ASC, f.sort_order ASC";
              
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    apiSuccess($stmt->fetchAll());
}

// Fetch general store settings by default
$stmt = $pdo->query("SELECT * FROM store_settings LIMIT 1");
$settings = $stmt->fetch();

if (!$settings) {
    apiError('Store settings not configured', 500);
}

apiSuccess($settings);
