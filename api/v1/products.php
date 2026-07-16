<?php
/**
 * TCKomputer API v1 - Products Endpoint
 * Handles fetching list of products (paginated, filterable) and individual product details.
 */
require_once __DIR__ . '/bootstrap.php';

// Single product fetch
if (isset($_GET['id'])) {
    $productId = (int)$_GET['id'];
    
    // Fetch product details (exclude purchase_price)
    $stmt = $pdo->prepare("SELECT p.id, p.category_id, p.name, p.slug, p.sku, p.brand, p.model, p.description, p.specification, p.selling_price, p.promo_price, p.promo_active, p.promo_stock, p.stock, p.status, p.condition_type, p.warranty_note, p.image, p.is_featured, p.is_active, p.created_at, p.updated_at, c.name AS category_name 
                           FROM products p 
                           LEFT JOIN categories c ON p.category_id = c.id 
                           WHERE p.id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    
    if (!$product) {
        apiError('Product not found', 404);
    }
    
    // Fetch product images
    $stmtImages = $pdo->prepare("SELECT id, image_path, sort_order FROM product_images WHERE product_id = ? ORDER BY sort_order ASC");
    $stmtImages->execute([$productId]);
    $product['images'] = $stmtImages->fetchAll();
    
    apiSuccess($product);
}

// Listing products
$search = trim($_GET['search'] ?? '');
$categoryId = (int)($_GET['category_id'] ?? 0);
$status = $_GET['status'] ?? ''; // ready, po, habis
$featured = isset($_GET['featured']) ? (int)$_GET['featured'] : null;
$promo = isset($_GET['promo']) ? (int)$_GET['promo'] : null;
$active = isset($_GET['is_active']) ? (int)$_GET['is_active'] : 1; // default only active
$sort = $_GET['sort'] ?? 'newest';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = max(1, min(100, (int)($_GET['per_page'] ?? 20))); // cap at 100

$where = [];
$params = [];

if ($active !== -1) { // -1 means all (both active and inactive)
    $where[] = 'p.is_active = ?';
    $params[] = $active;
}

if ($search !== '') {
    $where[] = '(p.name LIKE ? OR p.brand LIKE ? OR p.description LIKE ? OR p.sku LIKE ?)';
    $searchTerm = '%' . $search . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($categoryId > 0) {
    $where[] = 'p.category_id = ?';
    $params[] = $categoryId;
}

if ($status !== '') {
    $where[] = 'p.status = ?';
    $params[] = $status;
}

if ($featured !== null) {
    $where[] = 'p.is_featured = ?';
    $params[] = $featured;
}

if ($promo !== null) {
    $where[] = 'p.promo_active = ?';
    $params[] = $promo;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Sorting
$sortOptions = [
    'newest' => 'p.created_at DESC',
    'oldest' => 'p.created_at ASC',
    'cheapest' => 'p.selling_price ASC',
    'expensive' => 'p.selling_price DESC',
    'name_asc' => 'p.name ASC',
    'name_desc' => 'p.name DESC'
];
$orderBy = $sortOptions[$sort] ?? 'p.created_at DESC';

// Get total count
$countQuery = "SELECT COUNT(*) FROM products p $whereClause";
$stmtCount = $pdo->prepare($countQuery);
$stmtCount->execute($params);
$totalItems = (int)$stmtCount->fetchColumn();

// Pagination
$totalPages = (int)ceil($totalItems / $perPage);
$page = max(1, min($page, max(1, $totalPages)));
$offset = ($page - 1) * $perPage;

// Fetch products
$query = "SELECT p.id, p.category_id, p.name, p.slug, p.sku, p.brand, p.model, p.description, p.selling_price, p.promo_price, p.promo_active, p.stock, p.status, p.condition_type, p.image, p.is_featured, p.is_active, p.created_at, c.name AS category_name 
          FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id 
          $whereClause 
          ORDER BY $orderBy 
          LIMIT $perPage OFFSET $offset";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll();

apiSuccess([
    'products' => $products,
    'pagination' => [
        'current_page' => $page,
        'per_page' => $perPage,
        'total_items' => $totalItems,
        'total_pages' => $totalPages
    ]
]);
