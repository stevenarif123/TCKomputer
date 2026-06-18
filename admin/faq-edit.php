<?php
/**
 * Admin - Edit FAQ Entry
 * Form to edit an existing FAQ entry with question, answer, category,
 * sort order, and active status.
 * Server-side validation, CSRF protection, and flash messages.
 * Requirements: 6.1, 6.2, 6.3, 6.4, 13.1, 13.2, 13.3
 */

$pageTitle = "Edit FAQ";

// Process form/redirect logic before any output is rendered
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/admin-auth.php';

requireAdmin();

$pdo = getDBConnection();
$errors = [];

// Get FAQ ID from query string
$faqId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($faqId <= 0) {
    redirect('faqs', 'FAQ tidak ditemukan', 'error');
}

// Fetch FAQ from database
try {
    $stmt = $pdo->prepare("SELECT * FROM faqs WHERE id = ?");
    $stmt->execute([$faqId]);
    $faq = $stmt->fetch();
} catch (PDOException $e) {
    error_log('Error fetching FAQ: ' . $e->getMessage());
    redirect('faqs', 'Terjadi kesalahan, silakan coba lagi', 'error');
}

if (!$faq) {
    redirect('faqs', 'FAQ tidak ditemukan', 'error');
}

// Keep track of data to display in the form (initially pre-populated from db)
$formData = [
    'question' => $faq['question'],
    'answer' => $faq['answer'],
    'faq_category_id' => (int) $faq['faq_category_id'],
    'sort_order' => (int) $faq['sort_order'],
    'is_active' => (int) $faq['is_active'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($submittedToken)) {
        redirect('faq-edit?id=' . $faqId, 'Permintaan tidak valid, silakan coba lagi', 'error');
    }

    // Collect and trim form data
    $question = trim($_POST['question'] ?? '');
    $answer = trim($_POST['answer'] ?? '');
    $faqCategoryId = isset($_POST['faq_category_id']) ? (int) $_POST['faq_category_id'] : 0;
    $sortOrder = isset($_POST['sort_order']) ? (int) $_POST['sort_order'] : 0;
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    // Update formData for re-display in case of errors
    $formData = [
        'question' => $question,
        'answer' => $answer,
        'faq_category_id' => $faqCategoryId,
        'sort_order' => $sortOrder,
        'is_active' => $isActive,
    ];

    // Call validation helper
    $errors = validateFaqInput($pdo, $formData);

    // If no errors, update database
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare(
                "UPDATE faqs 
                 SET faq_category_id = ?, question = ?, answer = ?, sort_order = ?, is_active = ?, updated_at = NOW() 
                 WHERE id = ?"
            );
            $stmt->execute([
                $faqCategoryId,
                $question,
                $answer,
                $sortOrder,
                $isActive,
                $faqId
            ]);

            redirect('faqs', 'FAQ berhasil diperbarui');
        } catch (PDOException $e) {
            error_log('Error updating FAQ: ' . $e->getMessage());
            $errors[] = 'Gagal memperbarui FAQ, silakan coba lagi';
        }
    }
}

// Fetch active categories to populate dropdown
// Ordering by sort_order ASC
$stmtCats = $pdo->query("SELECT * FROM faq_categories WHERE is_active = 1 ORDER BY sort_order ASC");
$categories = $stmtCats->fetchAll();

// Include header after processing (generates new CSRF token)
require_once __DIR__ . '/../includes/admin-header.php';
?>

<?php if (!empty($errors)): ?>
<div class="alert alert-error">
    <ul>
        <?php foreach ($errors as $error): ?>
            <li><?= sanitizeOutput($error) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="admin-page-header">
    <h2>Edit FAQ</h2>
    <a href="faqs" class="btn btn-secondary">&laquo; Kembali</a>
</div>

<div class="admin-form-container">
    <form action="" method="POST" class="admin-form">
        <input type="hidden" name="csrf_token" value="<?= sanitizeOutput($csrfToken) ?>">

        <div class="form-group">
            <label for="question">Pertanyaan <span class="required">*</span></label>
            <textarea id="question" name="question" rows="4" maxlength="500" required
                      placeholder="Masukkan pertanyaan FAQ (maks 500 karakter)"><?= sanitizeOutput($formData['question']) ?></textarea>
            <small class="form-help">Maksimal 500 karakter.</small>
        </div>

        <div class="form-group">
            <label for="answer">Jawaban <span class="required">*</span></label>
            <textarea id="answer" name="answer" rows="8" maxlength="5000" required
                      placeholder="Masukkan jawaban FAQ (maks 5000 karakter)"><?= sanitizeOutput($formData['answer']) ?></textarea>
            <small class="form-help">Maksimal 5000 karakter.</small>
        </div>

        <div class="form-group">
            <label for="faq_category_id">Kategori FAQ <span class="required">*</span></label>
            <select id="faq_category_id" name="faq_category_id" required>
                <option value="">-- Pilih Kategori --</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int) $cat['id'] ?>" <?= ($formData['faq_category_id'] == $cat['id']) ? 'selected' : '' ?>>
                        <?= sanitizeOutput($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="sort_order">Urutan</label>
            <input type="number" id="sort_order" name="sort_order" min="0" max="999"
                   value="<?= (int) $formData['sort_order'] ?>"
                   placeholder="0">
            <small class="form-help">Urutan tampil di halaman pembeli (0-999). Angka kecil tampil lebih dulu.</small>
        </div>

        <div class="form-group form-checkbox">
            <label>
                <input type="checkbox" name="is_active" value="1"
                    <?= ($formData['is_active'] == 1) ? 'checked' : '' ?>>
                Aktif (tampilkan di toko)
            </label>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Simpan FAQ</button>
            <a href="faqs" class="btn btn-secondary">Batal</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
