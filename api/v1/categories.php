<?php
/**
 * TCKomputer API v1 - Categories Endpoint
 * Handles fetching categories list and individual category details.
 */
require_once __DIR__ . '/bootstrap.php';

// Single category fetch
if (isset($_GET['id'])) {
    $catId = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT id, name, slug, description, image, is_active, sort_order, created_at, updated_at 
                           FROM categories 
                           WHERE id = ?");
    $stmt->execute([$catId]);
    $category = $stmt->fetch();
    
    if (!$category) {
        apiError('Category not found', 404);
    }
    
    apiSuccess($category);
}

// Listing categories
$active = isset($_GET['is_active']) ? (int)$_GET['is_active'] : 1; // default only active

$where = [];
$params = [];

if ($active !== -1) {
    $where[] = 'c.is_active = ?';
    $params[] = $active;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Query categories with product count
$query = "SELECT c.id, c.name, c.slug, c.description, c.image, c.is_active, c.sort_order, c.created_at,
          (SELECT COUNT(*) FROM products WHERE category_id = c.id AND is_active = 1) AS product_count 
          FROM categories c 
          $whereClause 
          ORDER BY c.sort_order ASC, c.name ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$categories = $stmt->fetchAll();

apiSuccess($categories);
