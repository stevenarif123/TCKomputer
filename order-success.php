<?php
/**
 * Order Success Page
 * Displays detailed order confirmation, invoice summary,
 * and next steps depending on payment method chosen.
 * Features: Copy bank account to clipboard, and Direct WhatsApp confirmation.
 */

// Redirect if no order code provided
if (!isset($_GET['code']) || empty(trim($_GET['code']))) {
    header('Location: index.php');
    exit;
}

$orderCode = trim($_GET['code']);

require_once __DIR__ . '/includes/header.php';

// Fetch order details from database using the verified code
$pdo = getDBConnection();
$stmt = $pdo->prepare("SELECT * FROM orders WHERE order_code = ?");
$stmt->execute([$orderCode]);
$order = $stmt->fetch();

if (!$order) {
    // Redirect to home if order doesn't exist
    header('Location: index.php');
    exit;
}

$formattedTotal = formatRupiah((int)$order['total']);

// Normalize WhatsApp Number from Settings
$rawPhone = $storeSettings['phone'] ?? '082293924242';
$cleanPhone = preg_replace('/[^0-9]/', '', $rawPhone);
if (strpos($cleanPhone, '0') === 0) {
    $cleanPhone = '62' . substr($cleanPhone, 1);
}
$waNumber = $cleanPhone;

// Parse Bank Accounts from Settings
$bankAccounts = [];
$rawBankText = $storeSettings['bank_account'] ?? '';
$lines = explode("\n", $rawBankText);
foreach ($lines as $line) {
    $line = trim($line);
    if (empty($line)) continue;
    
    $number = '';
    if (preg_match('/(\d{8,20})/', $line, $matches)) {
        $number = $matches[1];
    }
    
    $bankName = 'Bank Transfer';
    if (preg_match('/^([a-zA-Z\s0-9]+)(?::|\s)/', $line, $matches)) {
        $bankName = trim($matches[1]);
    }
    
    $accName = '';
    if (preg_match('/(?:a\.n\.|a\/n|A\.N\.|A\/N)\s*(.+)$/i', $line, $matches)) {
        $accName = trim($matches[1]);
    } else {
        $accName = trim(str_replace([$bankName, $number, ':', 'a.n.', 'a.n', 'a/n'], '', $line));
    }
    
    $bankAccounts[] = [
        'line_raw' => $line,
        'bank_name' => $bankName,
        'number' => $number,
        'holder' => $accName ?: ($storeSettings['store_name'] ?? 'TC Komputer')
    ];
}

if (empty($bankAccounts)) {
    $bankAccounts[] = [
        'line_raw' => 'BRI: 494201016901509 a.n. HERMANTO STEVEN LISU ALLO ARIF',
        'bank_name' => 'BRI',
        'number' => '494201016901509',
        'holder' => 'HERMANTO STEVEN LISU ALLO ARIF'
    ];
    $bankAccounts[] = [
        'line_raw' => 'Mandiri: 1700004770834 a.n. HERMANTO STEVEN LISU ALLO',
        'bank_name' => 'Mandiri',
        'number' => '1700004770834',
        'holder' => 'HERMANTO STEVEN LISU ALLO'
    ];
}
?>

<div class="max-w-xl mx-auto px-4 py-4 md:py-12 animate-fade-in-up">
    <!-- Success Card -->
    <div class="bg-white rounded-xl border border-outline-variant/40 overflow-hidden">
        <!-- Header Banner -->
        <div class="bg-emerald-600 px-6 py-6 md:py-10 text-center text-white relative">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-white/20 mb-4 ">
                <span class="material-symbols-outlined text-4xl" style="font-variation-settings: 'FILL' 1;">check_circle</span>
            </div>
            <h1 class="text-2xl font-black tracking-tight">Pesanan Berhasil Dibuat!</h1>
            <p class="text-sm opacity-90 mt-1">Terima kasih telah berbelanja di TC Komputer</p>
        </div>

        <div class="p-4 md:p-8 space-y-4 md:space-y-6">
            <!-- Order Details Overview -->
            <div class="bg-surface-container-low border border-outline-variant/30 rounded-lg p-4 space-y-3">
                <div class="flex justify-between items-center pb-2 border-b border-outline-variant/30">
                    <span class="text-xs font-bold text-on-surface-variant uppercase tracking-wider">Kode Pesanan</span>
                    <span class="text-sm font-black text-secondary font-mono tracking-wide"><?= sanitizeOutput($order['order_code']) ?></span>
                </div>
                <div class="flex justify-between items-center pt-1">
                    <span class="text-xs text-on-surface-variant font-medium">Metode Pembayaran</span>
                    <span class="text-xs font-bold px-3 py-1 rounded-full <?= $order['payment_method'] === 'transfer' ? 'bg-blue-100 text-blue-800' : 'bg-orange-100 text-orange-800' ?>">
                        <?= $order['payment_method'] === 'transfer' ? 'Transfer Bank' : 'COD (Bayar di Tempat)' ?>
                    </span>
                </div>
                <div class="flex justify-between items-center pt-1">
                    <span class="text-xs text-on-surface-variant font-medium">Total Tagihan</span>
                    <span class="text-base font-black text-primary"><?= $formattedTotal ?></span>
                </div>
            </div>

            <!-- Dynamic Payment Instructions Panel -->
            <?php if ($order['payment_method'] === 'transfer'): ?>
                <!-- Transfer Payment Details -->
                <div class="space-y-4">
                    <div class="border border-blue-200 bg-blue-50/20 rounded-lg p-5 space-y-4">
                        <div class="flex items-center gap-2 text-blue-900 border-b border-blue-200/50 pb-2">
                            <span class="material-symbols-outlined text-blue-600" style="font-variation-settings: 'FILL' 1;">account_balance</span>
                            <h3 class="font-extrabold text-sm uppercase tracking-wider">Rekening Tujuan Transfer</h3>
                        </div>
                        
                        <div class="space-y-4">
                            <?php foreach ($bankAccounts as $index => $acc): ?>
                            <div class="border border-outline-variant/40 bg-white rounded-lg p-3 space-y-2">
                                <div class="flex flex-col md:flex-row md:items-center justify-between gap-2">
                                    <div>
                                        <span class="text-[10px] font-bold text-on-surface-variant uppercase block">Nomor Rekening <?= sanitizeOutput($acc['bank_name']) ?></span>
                                        <span class="text-base font-black text-on-surface font-mono tracking-wide"><?= sanitizeOutput($acc['number']) ?></span>
                                    </div>
                                    <?php if (!empty($acc['number'])): ?>
                                    <button onclick="copyToClipboard('<?= sanitizeOutput($acc['number']) ?>', this, '<?= sanitizeOutput($acc['bank_name']) ?>')" class="inline-flex items-center justify-center gap-1.5 bg-secondary hover:bg-secondary-container text-white text-xs font-bold px-3 py-2 rounded-lg transition-all active:scale-95">
                                        <span class="material-symbols-outlined text-sm">content_copy</span>
                                        <span class="copy-text">Salin</span>
                                    </button>
                                    <?php endif; ?>
                                </div>
                                <div class="grid grid-cols-2 gap-3 pt-2 border-t border-gray-100">
                                    <div>
                                        <span class="text-[10px] font-bold text-on-surface-variant uppercase block">Atas Nama</span>
                                        <span class="text-xs font-extrabold text-on-surface truncate block"><?= sanitizeOutput($acc['holder']) ?></span>
                                    </div>
                                    <div>
                                        <span class="text-[10px] font-bold text-on-surface-variant uppercase block">Nama Bank</span>
                                        <span class="text-xs font-extrabold text-on-surface font-sans block"><?= sanitizeOutput($acc['bank_name']) ?></span>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Warning Alert Box -->
                    <div class="bg-red-50 border border-red-200/50 text-red-950 p-4 rounded-lg flex items-start gap-3">
                        <span class="material-symbols-outlined text-red-600 mt-0.5" style="font-variation-settings: 'FILL' 1;">warning</span>
                        <div class="flex-1 text-xs leading-relaxed">
                            <p class="font-bold text-red-900">PENTING! Konfirmasi Pembayaran Wajib</p>
                            <p class="text-red-800 mt-1">Setelah melakukan transfer ke salah satu rekening di atas, Anda <strong>wajib</strong> mengonfirmasi pembayaran dengan menekan tombol hijau di bawah ini untuk mengirimkan bukti transfer via WhatsApp.</p>
                        </div>
                    </div>

                    <!-- WhatsApp CTA Button -->
                    <?php
                    $messageText = "Halo TC Komputer, saya ingin mengonfirmasi pembayaran untuk pesanan saya.\n\n"
                                 . "*Detail Pesanan*:\n"
                                 . "- Kode Pesanan: *" . $order['order_code'] . "*\n"
                                 . "- Nama Pembeli: *" . $order['buyer_name'] . "*\n"
                                 . "- Total Tagihan: *" . $formattedTotal . "*\n"
                                 . "- Metode Pembayaran: *Transfer Bank*\n\n"
                                 . "Berikut saya lampirkan bukti transfernya.";
                    $waUrl = "https://wa.me/" . $waNumber . "?text=" . urlencode($messageText);
                    ?>
                    <a href="<?= $waUrl ?>" target="_blank" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white py-3 rounded-lg font-extrabold text-sm transition-colors flex items-center justify-center gap-2.5 group">
                        <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24">
                            <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946C.06 5.348 5.397.01 12.008.01c3.202.001 6.212 1.246 8.477 3.514 2.266 2.268 3.507 5.28 3.505 8.484-.004 6.657-5.34 11.997-11.953 11.997-2.005-.001-3.973-.502-5.724-1.458L0 24zm6.59-4.846c1.6.95 3.188 1.449 4.825 1.451 5.436 0 9.86-4.37 9.864-9.799.002-2.63-1.023-5.101-2.885-6.965C16.59 1.978 14.12 .952 11.5 1.052c-5.437 0-9.862 4.371-9.866 9.8-.001 1.762.483 3.486 1.398 5.024l-.993 3.628 3.738-.98c1.512.825 3.203 1.25 4.908 1.25zm5.716-11.111c.29-.116.48-.194.596-.388.116-.194.116-.1.058-.214-.058-.116-.483-1.162-.662-1.59-.174-.42-.35-.362-.483-.37l-.407-.008c-.135 0-.358.05-.546.254-.188.204-.717.7-.717 1.709 0 1.008.733 1.977.836 2.113.103.136 1.443 2.203 3.496 3.088.488.21 1.02.34 1.378.373.358.034.685.016.942-.022.286-.042.88-.36 1.004-.708.125-.348.125-.646.088-.708-.037-.062-.135-.098-.285-.174-.15-.076-.88-.435-1.016-.484-.136-.05-.236-.076-.336.076-.1.15-.388.484-.476.584-.088.1-.176.11-.326.035-.15-.075-.632-.233-1.204-.74-.445-.395-.745-.884-.833-1.034-.088-.15-.01-.23.066-.305.068-.067.15-.175.225-.262.075-.088.1-.15.15-.25.05-.1.025-.188-.013-.263l-.448-1.077c-.12-.29-.25-.25-.34-.25z"/>
                        </svg>
                        Kirim Bukti Transfer ke WhatsApp
                    </a>
                </div>
            <?php else: ?>
                <!-- COD Payment Details -->
                <div class="space-y-4">
                    <div class="border border-orange-200 bg-orange-50/20 rounded-lg p-5 space-y-3">
                        <div class="flex items-center gap-2 text-orange-950 border-b border-orange-200/50 pb-2">
                            <span class="material-symbols-outlined text-orange-600" style="font-variation-settings: 'FILL' 1;">local_shipping</span>
                            <h3 class="font-extrabold text-sm uppercase tracking-wider">Instruksi Pembayaran COD</h3>
                        </div>
                        <p class="text-xs text-orange-900 leading-relaxed">
                            Pesanan Anda dengan metode pembayaran <strong>COD (Bayar di Tempat)</strong> telah dicatat. Kurir internal kami akan segera menyiapkan produk dan mengirimkannya ke alamat Anda.
                        </p>
                        <p class="text-xs text-orange-900 leading-relaxed font-semibold">
                            Mohon siapkan uang tunai sebesar <strong><?= $formattedTotal ?></strong> untuk diserahkan ke kurir saat barang tiba.
                        </p>
                    </div>

                    <!-- Alternate WhatsApp CTA for support -->
                    <?php
                    $waSupportMessage = "Halo TC Komputer, saya ingin menanyakan status pesanan COD saya dengan Kode Pesanan: *" . $order['order_code'] . "*";
                    $waSupportUrl = "https://wa.me/" . $waNumber . "?text=" . urlencode($waSupportMessage);
                    ?>
                    <a href="<?= $waSupportUrl ?>" target="_blank" class="w-full bg-slate-800 hover:bg-slate-900 text-white py-3 rounded-lg font-bold text-xs transition-colors flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined text-sm">chat</span>
                        Hubungi WhatsApp Jika Ada Pertanyaan
                    </a>
                </div>
            <?php endif; ?>

            <!-- Action Buttons -->
            <div class="grid grid-cols-2 gap-3 pt-4 border-t border-outline-variant/30">
                <a href="track-order?order_code=<?= urlencode($order['order_code']) ?>&buyer_phone=<?= urlencode($order['buyer_phone']) ?>" class="w-full bg-secondary hover:bg-secondary-container text-white py-3 rounded-lg font-bold text-xs transition-colors text-center flex items-center justify-center gap-1.5">
                    <span class="material-symbols-outlined text-sm">local_shipping</span>
                    Lacak Pesanan
                </a>
                <a href="products" class="w-full bg-surface-container-high hover:bg-surface-container-highest text-on-surface-variant py-3 rounded-lg font-bold text-xs transition-colors text-center flex items-center justify-center gap-1.5">
                    <span class="material-symbols-outlined text-sm">shopping_bag</span>
                    Lanjut Belanja
                </a>
            </div>
        </div>
    </div>
</div>

<script>
    // Copy account number helper
    function copyToClipboard(text, button, bankName) {
        if (!navigator.clipboard) {
            // Fallback for older browsers
            const textarea = document.createElement('textarea');
            textarea.value = text;
            document.body.appendChild(textarea);
            textarea.select();
            try {
                document.execCommand('copy');
                showCopySuccess(button, bankName);
            } catch (err) {
                console.error('Failed to copy text', err);
            }
            document.body.removeChild(textarea);
            return;
        }
        
        navigator.clipboard.writeText(text).then(() => {
            showCopySuccess(button, bankName);
        }).catch(err => {
            console.error('Failed to copy: ', err);
        });
    }

    function showCopySuccess(button, bankName) {
        const copyText = button.querySelector('.copy-text');
        const copyIcon = button.querySelector('.material-symbols-outlined');
        
        const originalText = copyText.textContent;
        const originalIcon = copyIcon.textContent;
        
        copyText.textContent = 'Tersalin!';
        copyIcon.textContent = 'check';
        button.classList.remove('bg-secondary', 'hover:bg-secondary-container');
        button.classList.add('bg-green-600', 'hover:bg-green-700');
        
        if (typeof showToast === 'function') {
            showToast('Sukses', 'Nomor rekening ' + (bankName || 'Bank') + ' berhasil disalin.');
        }
        
        setTimeout(() => {
            copyText.textContent = originalText;
            copyIcon.textContent = originalIcon;
            button.classList.remove('bg-green-600', 'hover:bg-green-700');
            button.classList.add('bg-secondary', 'hover:bg-secondary-container');
        }, 2000);
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
