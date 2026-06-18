<?php
/**
 * Admin FAQ Delete Action
 * Handles FAQ entry deletion.
 * Only accepts POST requests with valid CSRF token.
 * Requirements: 7.1, 7.2, 7.3, 7.4, 13.1, 13.2
 */

session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/admin-auth.php';

// Require admin authentication
requireAdmin();

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('faqs', 'Permintaan tidak valid', 'error');
}

// Validate CSRF token
$csrfToken = $_POST['csrf_token'] ?? '';
if (!validateCSRFToken($csrfToken)) {
    redirect('faqs', 'Permintaan tidak valid, silakan coba lagi', 'error');
}

// Get FAQ ID from POST
$faqId = isset($_POST['faq_id']) ? (int) $_POST['faq_id'] : 0;

if ($faqId <= 0) {
    redirect('faqs', 'FAQ tidak ditemukan', 'error');
}

$pdo = getDBConnection();

// Fetch FAQ to verify it exists
try {
    $stmt = $pdo->prepare("SELECT id FROM faqs WHERE id = ?");
    $stmt->execute([$faqId]);
    $faq = $stmt->fetch();
} catch (PDOException $e) {
    error_log('Error fetching FAQ for deletion: ' . $e->getMessage());
    redirect('faqs', 'Terjadi kesalahan, silakan coba lagi', 'error');
}

// If FAQ not found, redirect with error
if (!$faq) {
    redirect('faqs', 'FAQ tidak ditemukan', 'error');
}

// Delete FAQ record from database
try {
    $stmt = $pdo->prepare("DELETE FROM faqs WHERE id = ?");
    $stmt->execute([$faqId]);
} catch (PDOException $e) {
    error_log('Error deleting FAQ: ' . $e->getMessage());
    redirect('faqs', 'Gagal menghapus FAQ, silakan coba lagi', 'error');
}

// Flash success message and redirect to FAQ list
redirect('faqs', 'FAQ berhasil dihapus', 'success');
