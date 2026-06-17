<?php
/**
 * Admin Products List
 * Paginated list of all products with search/filter and action links.
 */

$pageTitle = "Kelola Produk";
require_once __DIR__ . '/../includes/admin-header.php';

// Get search parameter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get current page
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$page = max(1, $page);

// Build query
$baseQuery = "SELECT p.*, c.name AS category_name 
              FROM products p 
              LEFT JOIN categories c ON p.category_id = c.id";
$params = [];

if ($search !== '') {
    $baseQuery .= " WHERE p.name LIKE ?";
    $params[] = '%' . $search . '%';
}

$baseQuery .= " ORDER BY p.created_at DESC";

// Paginate results (10 per page)
$result = paginate($pdo, $baseQuery, $params, 10, $page);
$products = $result['data'];
$totalPages = $result['pages'];
$currentPage = $result['current_page'];
$totalProducts = $result['total'];
?>

<div class="admin-page-header">
    <h2>Kelola Produk</h2>
    <div class="header-actions" style="display: flex; gap: 8px;">
        <a href="export-products" class="btn btn-outline">
            <span class="material-symbols-outlined">download</span> Ekspor ke CSV
        </a>
        <a href="product-add" class="btn btn-primary">+ Tambah Produk</a>
    </div>
</div>

<!-- Search Form -->
<div class="admin-filter-bar">
    <form method="GET" action="products" class="search-form">
        <input type="text" name="search" value="<?= sanitizeOutput($search) ?>" placeholder="Cari produk..." class="form-input">
        <button type="submit" class="btn btn-secondary">Cari</button>
        <?php if ($search !== ''): ?>
            <a href="products" class="btn btn-outline">Reset</a>
        <?php endif; ?>
    </form>
</div>

<?php if ($search !== ''): ?>
<p class="search-result-info">Menampilkan hasil pencarian untuk: <strong><?= sanitizeOutput($search) ?></strong> (<?= $totalProducts ?> produk ditemukan)</p>
<?php endif; ?>

<!-- Products Table -->
<div class="table-responsive">
    <table class="admin-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Nama Produk</th>
                <th>Kategori</th>
                <th>Harga</th>
                <th>Stok</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($products)): ?>
                <tr>
                    <td colspan="7" class="text-center">Tidak ada produk ditemukan.</td>
                </tr>
            <?php else: ?>
                <?php
                $startNumber = ($currentPage - 1) * 10 + 1;
                foreach ($products as $index => $product):
                ?>
                <tr>
                    <td><?= $startNumber + $index ?></td>
                    <td><?= sanitizeOutput($product['name']) ?></td>
                    <td><?= sanitizeOutput($product['category_name'] ?? '-') ?></td>
                    <td class="price-cell" data-product-id="<?= (int) $product['id'] ?>" data-price="<?= (int) $product['selling_price'] ?>">
                        <span class="price-text"><?= formatRupiah((int) $product['selling_price']) ?></span>
                        <button type="button" class="btn-quick-edit" onclick="startQuickEdit(this, 'price')" title="Edit Cepat Harga">
                            <span class="material-symbols-outlined">edit</span>
                        </button>
                    </td>
                    <td class="stock-cell" data-product-id="<?= (int) $product['id'] ?>" data-stock="<?= (int) $product['stock'] ?>">
                        <span class="stock-text"><?= (int) $product['stock'] ?></span>
                        <button type="button" class="btn-quick-edit" onclick="startQuickEdit(this, 'stock')" title="Edit Cepat Stok">
                            <span class="material-symbols-outlined">edit</span>
                        </button>
                    </td>
                    <td><?= getStockStatusBadge($product['status'], (int) $product['stock']) ?></td>
                    <td class="action-links">
                        <a href="product-edit?id=<?= (int) $product['id'] ?>" class="btn btn-sm btn-secondary">Edit</a>
                        <form method="POST" action="product-delete" class="inline-form" onsubmit="return confirm('Yakin ingin menghapus produk ini?');">
                            <input type="hidden" name="csrf_token" value="<?= sanitizeOutput($csrfToken) ?>">
                            <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div class="pagination">
    <?php if ($currentPage > 1): ?>
        <a href="?page=<?= $currentPage - 1 ?><?= $search !== '' ? '&search=' . urlencode($search) : '' ?>" class="pagination-link">&laquo; Sebelumnya</a>
    <?php endif; ?>

    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <?php if ($i === $currentPage): ?>
            <span class="pagination-link active"><?= $i ?></span>
        <?php else: ?>
            <a href="?page=<?= $i ?><?= $search !== '' ? '&search=' . urlencode($search) : '' ?>" class="pagination-link"><?= $i ?></a>
        <?php endif; ?>
    <?php endfor; ?>

    <?php if ($currentPage < $totalPages): ?>
        <a href="?page=<?= $currentPage + 1 ?><?= $search !== '' ? '&search=' . urlencode($search) : '' ?>" class="pagination-link">Berikutnya &raquo;</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<style>
    .price-cell, .stock-cell {
        position: relative;
        padding-right: 32px !important;
    }
    .btn-quick-edit {
        position: absolute;
        right: 8px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: var(--admin-primary, #0058be);
        cursor: pointer;
        padding: 4px;
        display: none;
        align-items: center;
        justify-content: center;
        border-radius: 4px;
        transition: all 0.2s ease;
    }
    .btn-quick-edit:hover {
        background-color: rgba(0, 88, 190, 0.1);
    }
    .price-cell:hover .btn-quick-edit, .stock-cell:hover .btn-quick-edit {
        display: inline-flex;
    }
    .btn-quick-edit span {
        font-size: 16px;
    }
    
    #quick-edit-toast {
        position: fixed;
        top: 24px;
        right: 24px;
        background-color: #22543d;
        color: #fff;
        padding: 12px 20px;
        border-radius: 8px;
        font-weight: 600;
        font-family: 'Inter', sans-serif;
        font-size: 13px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 9999;
        opacity: 0;
        transform: translateY(-20px);
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        pointer-events: none;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    #quick-edit-toast.show {
        opacity: 1;
        transform: translateY(0);
    }
</style>

<script>
function startQuickEdit(btn, type) {
    const cell = btn.closest('td');
    const textSpan = cell.querySelector(type === 'price' ? '.price-text' : '.stock-text');
    const originalVal = cell.dataset[type];
    
    // Hide text and edit button
    textSpan.style.display = 'none';
    btn.style.display = 'none';
    
    // Create form if it doesn't exist
    let form = cell.querySelector('.quick-edit-form');
    if (!form) {
        form = document.createElement('div');
        form.className = 'quick-edit-form';
        form.style.display = 'flex';
        form.style.gap = '4px';
        form.style.alignItems = 'center';
        
        const input = document.createElement('input');
        input.type = 'number';
        input.className = 'form-input quick-edit-input';
        input.value = originalVal;
        input.style.width = type === 'price' ? '110px' : '75px';
        input.style.padding = '2px 6px';
        input.style.fontSize = '13px';
        input.style.height = '28px';
        input.style.border = '1px solid #c6c6cd';
        input.style.borderRadius = '6px';
        input.style.background = 'white';
        
        // Save on enter key, cancel on escape
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                saveQuickEdit(saveBtn, type);
            } else if (e.key === 'Escape') {
                cancelQuickEdit(cancelBtn, type);
            }
        });
        
        const saveBtn = document.createElement('button');
        saveBtn.type = 'button';
        saveBtn.className = 'btn btn-sm btn-primary';
        saveBtn.style.padding = '2px 6px';
        saveBtn.style.height = '28px';
        saveBtn.style.display = 'inline-flex';
        saveBtn.style.alignItems = 'center';
        saveBtn.innerHTML = '<span class="material-symbols-outlined" style="font-size: 16px;">check</span>';
        saveBtn.onclick = () => saveQuickEdit(saveBtn, type);
        
        const cancelBtn = document.createElement('button');
        cancelBtn.type = 'button';
        cancelBtn.className = 'btn btn-sm btn-outline';
        cancelBtn.style.padding = '2px 6px';
        cancelBtn.style.height = '28px';
        cancelBtn.style.display = 'inline-flex';
        cancelBtn.style.alignItems = 'center';
        cancelBtn.style.border = '1px solid #c6c6cd';
        cancelBtn.style.background = 'white';
        cancelBtn.innerHTML = '<span class="material-symbols-outlined" style="font-size: 16px; color:#45464d;">close</span>';
        cancelBtn.onclick = () => cancelQuickEdit(cancelBtn, type);
        
        form.appendChild(input);
        form.appendChild(saveBtn);
        form.appendChild(cancelBtn);
        cell.appendChild(form);
        
        input.focus();
        input.select();
    } else {
        form.style.display = 'flex';
        const input = form.querySelector('.quick-edit-input');
        input.value = originalVal;
        input.focus();
        input.select();
    }
}

function cancelQuickEdit(btn, type) {
    const cell = btn.closest('td');
    const textSpan = cell.querySelector(type === 'price' ? '.price-text' : '.stock-text');
    const editBtn = cell.querySelector('.btn-quick-edit');
    const form = cell.querySelector('.quick-edit-form');
    
    if (form) form.style.display = 'none';
    textSpan.style.display = '';
    editBtn.style.display = '';
}

function saveQuickEdit(btn, type) {
    const cell = btn.closest('td');
    const productId = cell.dataset.productId;
    const form = cell.querySelector('.quick-edit-form');
    const input = form.querySelector('.quick-edit-input');
    const newVal = parseInt(input.value, 10);
    
    if (isNaN(newVal) || newVal < 0) {
        alert(type === 'price' ? 'Harga tidak valid.' : 'Stok tidak valid.');
        return;
    }
    
    // Disable inputs during save
    input.disabled = true;
    btn.disabled = true;
    
    const formData = new FormData();
    formData.append('product_id', productId);
    formData.append('type', type);
    formData.append('value', newVal);
    formData.append('csrf_token', '<?= sanitizeOutput($csrfToken) ?>');
    
    fetch('product-quick-edit.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        input.disabled = false;
        btn.disabled = false;
        
        if (data.success) {
            // Update data attribute and display text
            cell.dataset[type] = newVal;
            
            const textSpan = cell.querySelector(type === 'price' ? '.price-text' : '.stock-text');
            const editBtn = cell.querySelector('.btn-quick-edit');
            
            if (type === 'price') {
                textSpan.textContent = formatRupiah(newVal);
            } else {
                textSpan.textContent = newVal;
                // Update stock status badge if present
                const statusCell = cell.closest('tr').querySelector('td:nth-child(6)');
                if (statusCell && data.status_badge) {
                    statusCell.innerHTML = data.status_badge;
                }
            }
            
            // Hide form and show text
            form.style.display = 'none';
            textSpan.style.display = '';
            editBtn.style.display = '';
            
            // Show toast message
            showToastMessage('Sukses memperbarui ' + (type === 'price' ? 'harga' : 'stok') + '!');
        } else {
            alert('Gagal: ' + data.message);
            cancelQuickEdit(btn, type);
        }
    })
    .catch(error => {
        input.disabled = false;
        btn.disabled = false;
        alert('Terjadi kesalahan koneksi.');
        cancelQuickEdit(btn, type);
    });
}

function showToastMessage(message) {
    let toast = document.getElementById('quick-edit-toast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'quick-edit-toast';
        document.body.appendChild(toast);
    }
    toast.innerHTML = '<span class="material-symbols-outlined" style="font-size: 18px;">check_circle</span>' + message;
    toast.classList.add('show');
    
    setTimeout(() => {
        toast.classList.remove('show');
    }, 2500);
}

function formatRupiah(number) {
    return 'Rp ' + Number(number).toLocaleString('id-ID');
}
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
