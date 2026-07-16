<?php
/**
 * TCKomputer API v1 - Orders Endpoint
 * Handles retrieving order lists, details, and updating order status/notes.
 */
require_once __DIR__ . '/bootstrap.php';

// Single order fetch or update
if (isset($_GET['id'])) {
    $orderId = (int)$_GET['id'];
    
    // Fetch order main details
    $stmt = $pdo->prepare("SELECT o.*, s.area_name AS shipping_area_name 
                           FROM orders o 
                           LEFT JOIN shipping_areas s ON o.shipping_area_id = s.id 
                           WHERE o.id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    
    if (!$order) {
        apiError('Order not found', 404);
    }
    
    // Handle status update (PATCH request)
    if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
        // Read JSON body
        $json = file_get_contents('php://input');
        $body = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            apiError('Invalid JSON body');
        }
        
        $newStatus = $body['order_status'] ?? null;
        $adminNotes = $body['admin_notes'] ?? null;
        
        if ($newStatus === null && $adminNotes === null) {
            apiError('No fields to update. Provide order_status or admin_notes.');
        }
        
        $updateFields = [];
        $updateParams = [];
        
        if ($newStatus !== null) {
            $validStatuses = ['menunggu_konfirmasi', 'diproses', 'siap_diantar', 'dikirim', 'selesai', 'dibatalkan'];
            if (!in_array($newStatus, $validStatuses)) {
                apiError('Invalid order status. Allowed: ' . implode(', ', $validStatuses));
            }
            
            // Validate transition
            $currentStatus = $order['order_status'];
            $isValidTransition = false;
            
            if ($currentStatus === $newStatus) {
                $isValidTransition = true;
            } elseif ($currentStatus === 'menunggu_konfirmasi' && in_array($newStatus, ['diproses', 'dibatalkan'])) {
                $isValidTransition = true;
            } elseif ($currentStatus === 'diproses' && in_array($newStatus, ['siap_diantar', 'dikirim', 'dibatalkan'])) {
                $isValidTransition = true;
            } elseif ($currentStatus === 'siap_diantar' && in_array($newStatus, ['dikirim', 'dibatalkan'])) {
                $isValidTransition = true;
            } elseif ($currentStatus === 'dikirim' && in_array($newStatus, ['selesai', 'dibatalkan'])) {
                $isValidTransition = true;
            }
            
            if (!$isValidTransition) {
                apiError("Invalid status transition from '$currentStatus' to '$newStatus'");
            }
            
            $updateFields[] = 'order_status = ?';
            $updateParams[] = $newStatus;
            $order['order_status'] = $newStatus; // update local representation for response
        }
        
        if ($adminNotes !== null) {
            $updateFields[] = 'admin_notes = ?';
            $updateParams[] = $adminNotes;
            $order['admin_notes'] = $adminNotes; // update local representation for response
        }
        
        $updateParams[] = $orderId;
        $stmtUpdate = $pdo->prepare("UPDATE orders SET " . implode(', ', $updateFields) . ", updated_at = NOW() WHERE id = ?");
        $stmtUpdate->execute($updateParams);
    }
    
    // Fetch order items
    $stmtItems = $pdo->prepare("SELECT oi.id, oi.product_id, oi.product_name, oi.product_price, oi.quantity, oi.subtotal, p.image 
                                FROM order_items oi 
                                LEFT JOIN products p ON oi.product_id = p.id 
                                WHERE oi.order_id = ?");
    $stmtItems->execute([$orderId]);
    $order['items'] = $stmtItems->fetchAll();
    
    apiSuccess($order);
}

// Listing orders
$status = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = max(1, min(100, (int)($_GET['per_page'] ?? 20)));

$where = [];
$params = [];

if ($status !== '') {
    $where[] = 'o.order_status = ?';
    $params[] = $status;
}

if ($search !== '') {
    $where[] = '(o.order_code LIKE ? OR o.buyer_name LIKE ? OR o.buyer_phone LIKE ?)';
    $searchTerm = '%' . $search . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Get total count
$countQuery = "SELECT COUNT(*) FROM orders o $whereClause";
$stmtCount = $pdo->prepare($countQuery);
$stmtCount->execute($params);
$totalItems = (int)$stmtCount->fetchColumn();

// Pagination
$totalPages = (int)ceil($totalItems / $perPage);
$page = max(1, min($page, max(1, $totalPages)));
$offset = ($page - 1) * $perPage;

// Fetch orders
$query = "SELECT o.id, o.order_code, o.buyer_name, o.buyer_phone, o.subtotal, o.shipping_cost, o.discount_amount, o.total, o.payment_method, o.payment_status, o.order_status, o.created_at, s.area_name AS shipping_area_name 
          FROM orders o 
          LEFT JOIN shipping_areas s ON o.shipping_area_id = s.id 
          $whereClause 
          ORDER BY o.created_at DESC 
          LIMIT $perPage OFFSET $offset";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$orders = $stmt->fetchAll();

apiSuccess([
    'orders' => $orders,
    'pagination' => [
        'current_page' => $page,
        'per_page' => $perPage,
        'total_items' => $totalItems,
        'total_pages' => $totalPages
    ]
]);
