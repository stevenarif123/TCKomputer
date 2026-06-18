<?php
/**
 * Admin FAQ Category Delete Action
 * Handles FAQ category deletion with associated FAQs check.
 * Only accepts POST requests with valid CSRF token.
 * Rejects deletion if the category still has associated FAQs.
 * Requirements: 11.1, 11.2, 11.3, 11.4, 11.5, 13.1, 13.2
 */

session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/admin-auth.php';

// Require admin authentication
requireAdmin();

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('faq-categories', 'Permintaan tidak valid', 'error');
}

// Validate CSRF token
$csrfToken = $_POST['csrf_token'] ?? '';
if (!validateCSRFToken($csrfToken)) {
    redirect('faq-categories', 'Permintaan tidak valid, silakan coba lagi', 'error');
}

// Get category ID from POST
$categoryId = isset($_POST['category_id']) ? (int) $_POST['category_id'] : 0;

if ($categoryId <= 0) {
    redirect('faq-categories', 'Kategori FAQ tidak ditemukan', 'error');
}

$pdo = getDBConnection();

try {
    // Call the helper function to delete the category safely.
    // It returns array ['success' => bool, 'message' => string].
    $result = deleteFaqCategory($pdo, $categoryId);
    
    if ($result['success']) {
        redirect('faq-categories', $result['message'], 'success');
    } else {
        redirect('faq-categories', $result['message'], 'error');
    }
} catch (PDOException $e) {
    error_log('Error deleting FAQ category: ' . $e->getMessage());
    redirect('faq-categories', 'Terjadi kesalahan, silakan coba lagi', 'error');
}
