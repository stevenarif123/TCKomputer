<?php
/**
 * Admin - Add FAQ Entry
 * Form to create a new FAQ entry with question, answer, category,
 * sort order, and active status.
 * Server-side validation, CSRF protection, and flash messages.
 * Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6, 5.7, 5.8, 13.1, 13.2, 13.3
 */

$pageTitle = "Tambah FAQ";

// Process form before including header (redirect needs to happen before output)
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/admin-auth.php';

requireAdmin();

$pdo = getDBConnection();
$errors = [];
$formData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($submittedToken)) {
        $errors[] = 'Token keamanan tidak valid. Silakan coba lagi.';
    }

    // Collect and trim form data
    $question = trim($_POST['question'] ?? '');
    $answer = trim($_POST['answer'] ?? '');
    $faqCategoryId = isset($_POST['faq_category_id']) ? (int) $_POST['faq_category_id'] : 0;
    $sortOrder = isset($_POST['sort_order']) ? (int) $_POST['sort_order'] : 0;
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    // Preserve form data for re-display on error
    $formData = [
        'question' => $question,
        'answer' => $answer,
        'faq_category_id' => $faqCategoryId,
        'sort_order' => $sortOrder,
        'is_active' => $isActive,
    ];

    // Call validation helper if token is valid
    if (empty($errors)) {
        $validationErrors = validateFaqInput($pdo, $formData);
        $errors = array_merge($errors, $validationErrors);
    }

    // If no errors, insert into database
    if (empty($errors)) {
        $stmt = $pdo->prepare(
            "INSERT INTO faqs (faq_category_id, question, answer, sort_order, is_active, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, NOW(), NOW())"
        );
        $stmt->execute([
            $faqCategoryId,
            $question,
            $answer,
            $sortOrder,
            $isActive
        ]);

        redirect('faqs', 'FAQ berhasil ditambahkan');
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
    <h2>Tambah FAQ Baru</h2>
    <a href="faqs" class="btn btn-secondary">&laquo; Kembali</a>
</div>

<div class="admin-form-container">
    <form action="" method="POST" class="admin-form">
        <input type="hidden" name="csrf_token" value="<?= sanitizeOutput($csrfToken) ?>">

        <div class="form-group">
            <label for="question">Pertanyaan <span class="required">*</span></label>
            <textarea id="question" name="question" rows="4" maxlength="500" required
                      placeholder="Masukkan pertanyaan FAQ (maks 500 karakter)"><?= sanitizeOutput($formData['question'] ?? '') ?></textarea>
            <small class="form-help">Maksimal 500 karakter.</small>
        </div>

        <div class="form-group">
            <label for="answer">Jawaban <span class="required">*</span></label>
            <textarea id="answer" name="answer" rows="8" maxlength="5000" required
                      placeholder="Masukkan jawaban FAQ (maks 5000 karakter)"><?= sanitizeOutput($formData['answer'] ?? '') ?></textarea>
            <small class="form-help">Maksimal 5000 karakter.</small>
        </div>

        <div class="form-group">
            <label for="faq_category_id">Kategori FAQ <span class="required">*</span></label>
            <select id="faq_category_id" name="faq_category_id" required>
                <option value="">-- Pilih Kategori --</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int) $cat['id'] ?>" <?= (($formData['faq_category_id'] ?? 0) == $cat['id']) ? 'selected' : '' ?>>
                        <?= sanitizeOutput($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="sort_order">Urutan</label>
            <input type="number" id="sort_order" name="sort_order" min="0" max="999"
                   value="<?= isset($formData['sort_order']) ? (int) $formData['sort_order'] : 0 ?>"
                   placeholder="0">
            <small class="form-help">Urutan tampil di halaman pembeli (0-999). Angka kecil tampil lebih dulu.</small>
        </div>

        <div class="form-group form-checkbox">
            <label>
                <input type="checkbox" name="is_active" value="1"
                    <?= (!isset($formData['is_active']) || $formData['is_active'] == 1) ? 'checked' : '' ?>>
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
