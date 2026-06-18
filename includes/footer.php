<?php
/**
 * Buyer Footer
 * Displays store contact info, footer text from settings, and closes HTML structure.
 * Expects $storeSettings variable to be set (loaded in header.php).
 */
?>

</main>

<footer class="w-full py-6 md:py-xl px-4 md:px-margin-desktop bg-primary-container border-t border-outline-variant/10 text-white mt-lg relative overflow-hidden">
    <div class="absolute inset-0 bg-[linear-gradient(to_bottom,rgba(255,255,255,0.01)_1px,transparent_1px)] bg-[size:100%_4px] pointer-events-none"></div>
    <div class="max-w-max-width mx-auto grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-gutter mb-6 md:mb-xl relative z-10">
        <div class="col-span-1 space-y-md">
            <div class="flex items-center gap-2 select-none mb-2 md:mb-4">
                <div class="flex items-center justify-center bg-white/5 p-1.5 md:p-2 rounded-lg border border-white/10">
                    <span class="material-symbols-outlined text-white text-xl md:text-2xl" style="font-variation-settings: 'FILL' 1, 'wght' 600;">devices</span>
                </div>
                <div class="flex items-center font-black tracking-tighter text-lg md:text-2xl whitespace-nowrap">
                    <span class="text-white font-black">TC</span>
                    <span class="text-white ml-1 font-medium font-sans">Komputer</span>
                </div>
            </div>
            <?php if (!empty($storeSettings['footer_text'])): ?>
                <p class="text-[11px] text-on-primary-container pr-md leading-relaxed hidden md:block">
                    <?= sanitizeOutput($storeSettings['footer_text']) ?>
                </p>
            <?php else: ?>
                <p class="text-[11px] text-on-primary-container pr-md leading-relaxed hidden md:block">
                    Toko komputer dan aksesoris IT terlengkap dengan harga transparan, produk berkualitas, dan bergaransi resmi.
                </p>
            <?php endif; ?>
            <?php if (!empty($storeSettings['address'])): ?>
                <p class="text-[11px] text-on-primary-container leading-relaxed hidden md:block">
                    <span class="font-bold text-white block mb-1">Kantor Pusat:</span>
                    <?= nl2br(sanitizeOutput($storeSettings['address'])) ?>
                </p>
            <?php endif; ?>
        </div>
        <div>
            <h4 class="text-white font-bold mb-2 md:mb-md text-[11px] md:text-label-md uppercase tracking-wider">Jelajahi</h4>
            <ul class="space-y-1.5 md:space-y-3">
                <li><a class="text-on-primary-container hover:text-white text-[11px] md:text-body-sm transition-colors flex items-center gap-1.5" href="index.php"><span class="w-1 h-1 bg-secondary rounded-full"></span>Beranda</a></li>
                <li><a class="text-on-primary-container hover:text-white text-[11px] md:text-body-sm transition-colors flex items-center gap-1.5" href="products.php"><span class="w-1 h-1 bg-secondary rounded-full"></span>Produk</a></li>
                <li><a class="text-on-primary-container hover:text-white text-[11px] md:text-body-sm transition-colors flex items-center gap-1.5" href="categories.php"><span class="w-1 h-1 bg-secondary rounded-full"></span>Kategori</a></li>
                <li><a class="text-on-primary-container hover:text-white text-[11px] md:text-body-sm transition-colors flex items-center gap-1.5" href="faq"><span class="w-1 h-1 bg-secondary rounded-full"></span>FAQ</a></li>
            </ul>
        </div>
        <div>
            <h4 class="text-white font-bold mb-2 md:mb-md text-[11px] md:text-label-md uppercase tracking-wider">Hubungi Kami</h4>
            <ul class="space-y-1.5 md:space-y-3 text-[11px] md:text-body-sm text-on-primary-container">
                <?php if (!empty($storeSettings['phone'])): ?>
                    <li class="flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[15px]">phone</span>
                        <span><?= sanitizeOutput($storeSettings['phone']) ?></span>
                    </li>
                <?php endif; ?>
                <?php if (!empty($storeSettings['email'])): ?>
                    <li class="flex items-center gap-1.5 hidden md:flex">
                        <span class="material-symbols-outlined text-[15px]">mail</span>
                        <span><?= sanitizeOutput($storeSettings['email']) ?></span>
                    </li>
                <?php endif; ?>
                <li><a class="hover:text-white transition-colors flex items-center gap-1.5" href="track-order.php"><span class="w-1 h-1 bg-secondary rounded-full"></span>Lacak Pesanan</a></li>
            </ul>
        </div>
        <div class="space-y-2 md:space-y-md col-span-2 md:col-span-1">
            <h4 class="text-white font-bold text-[11px] md:text-label-md uppercase tracking-wider">Ikuti Kami</h4>
            <p class="text-[11px] text-on-primary-container leading-relaxed hidden md:block">Dapatkan update hardware terbaru dan promo eksklusif melalui media sosial kami.</p>
            <div class="flex gap-2 md:gap-sm pt-1 md:pt-2">
                <a class="w-8 h-8 md:w-10 md:h-10 rounded-full bg-white/5 flex items-center justify-center hover:bg-[#E1306C] transition-colors text-white" href="https://instagram.com/tckomputer_toraja" target="_blank" title="Instagram">
                    <svg class="w-4 h-4 md:w-5 md:h-5 fill-current" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                </a>
                <a class="w-8 h-8 md:w-10 md:h-10 rounded-full bg-white/5 flex items-center justify-center hover:bg-[#000000] transition-colors text-white" href="https://tiktok.com/@tc.computer_toraja" target="_blank" title="TikTok">
                    <svg class="w-4 h-4 md:w-5 md:h-5 fill-current" viewBox="0 0 24 24"><path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-5.2 1.74 2.89 2.89 0 012.31-4.64 2.93 2.93 0 01.88.13V9.4a6.84 6.84 0 00-1-.05A6.33 6.33 0 005 15.68a6.34 6.34 0 006.33 6.33 6.34 6.34 0 006.33-6.33V8.84a8.4 8.4 0 004.34 1.2V6.58a4.8 4.8 0 01-2.41-.67z"/></svg>
                </a>
                <a class="w-8 h-8 md:w-10 md:h-10 rounded-full bg-white/5 flex items-center justify-center hover:bg-[#25D366] transition-colors text-white" href="https://wa.me/6282293924242" target="_blank" title="WhatsApp">
                    <svg class="w-4 h-4 md:w-5 md:h-5 fill-current" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946C.06 5.348 5.397.01 12.008.01c3.202.001 6.212 1.246 8.477 3.514 2.266 2.268 3.507 5.28 3.505 8.484-.004 6.657-5.34 11.997-11.953 11.997-2.005-.001-3.973-.502-5.724-1.458L0 24zm6.59-4.846c1.6.95 3.188 1.449 4.825 1.451 5.436 0 9.86-4.37 9.864-9.799.002-2.63-1.023-5.101-2.885-6.965C16.59 1.978 14.12 .952 11.5 1.052c-5.437 0-9.862 4.371-9.866 9.8-.001 1.762.483 3.486 1.398 5.024l-.993 3.628 3.738-.98c1.512.825 3.203 1.25 4.908 1.25zm5.716-11.111c.29-.116.48-.194.596-.388.116-.194.116-.1.058-.214-.058-.116-.483-1.162-.662-1.59-.174-.42-.35-.362-.483-.37l-.407-.008c-.135 0-.358.05-.546.254-.188.204-.717.7-.717 1.709 0 1.008.733 1.977.836 2.113.103.136 1.443 2.203 3.496 3.088.488.21 1.02.34 1.378.373.358.034.685.016.942-.022.286-.042.88-.36 1.004-.708.125-.348.125-.646.088-.708-.037-.062-.135-.098-.285-.174-.15-.076-.88-.435-1.016-.484-.136-.05-.236-.076-.336.076-.1.15-.388.484-.476.584-.088.1-.176.11-.326.035-.15-.075-.632-.233-1.204-.74-.445-.395-.745-.884-.833-1.034-.088-.15-.01-.23.066-.305.068-.067.15-.175.225-.262.075-.088.1-.15.15-.25.05-.1.025-.188-.013-.263l-.448-1.077c-.12-.29-.25-.25-.34-.25z"/></svg>
                </a>
            </div>
            <div class="pt-1 md:pt-4 text-on-primary-container text-[10px] md:text-xs">
                <div class="flex items-center gap-1.5 mb-1">
                    <span class="font-semibold text-white w-16 md:w-20">Instagram</span> 
                    <span>@tckomputer_toraja</span>
                </div>
                <div class="flex items-center gap-1.5 mb-1">
                    <span class="font-semibold text-white w-16 md:w-20">TikTok</span> 
                    <span>@tc.computer_toraja</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="font-semibold text-white w-16 md:w-20">WhatsApp</span> 
                    <span>082293924242</span>
                </div>
            </div>
        </div>
    </div>
    <div class="max-w-max-width mx-auto pt-lg border-t border-white/10 flex flex-col md:flex-row justify-between items-center gap-sm relative z-10">
        <?php 
        $cleanStoreName = preg_replace('/^PT\.?\s+/i', '', sanitizeOutput($storeName));
        ?>
        <p class="text-[12px] text-on-primary-container">© <?= date('Y') ?> <?= $cleanStoreName ?>. Semua hak dilindungi.</p>
        <div class="flex gap-md">
            <img alt="Secure Payment" class="h-6 w-auto object-contain opacity-80 hover:opacity-100 transition-opacity" src="https://lh3.googleusercontent.com/aida-public/AB6AXuDjKkM3XwLYkuyyy3GA76lpZE08CgjSdzU1euAukXRdAWD753apsGygYuX4Su-f2P9dorBFBTYsvsEUk1LY1hmwrX93wXoKinJefOhbTJEyKLdWuLy_MOOrwpcHLbAk2hYQGddh8_8MiZzry5sCIXZDqWIiS6mnAO5xCGDVyYEcSAx-IDy8fPtmNdpby2jDTRrTvn0oQVOAWm-BbLmK2Bb9JfkKFHqwZwI-4azOvyIuwcRNEz1EzCmxWbOrS_AV-4R3Y1iLGEZpLW8"/>
        </div>
    </div>
</footer>

<!-- Toast Notifications UI -->
<div id="toast" class="fixed top-6 left-1/2 -translate-x-1/2 w-[90%] md:w-[360px] md:left-auto md:right-6 md:transform-none z-[200] bg-slate-800 text-white px-5 py-3.5 rounded-xl shadow-lg border border-white/10 flex items-center gap-3.5 toast-notification">
    <div id="toast-icon-container" class="w-8 h-8 rounded-full bg-secondary flex items-center justify-center shrink-0">
        <span id="toast-icon" class="material-symbols-outlined text-white text-md">done</span>
    </div>
    <div class="flex-grow">
        <p class="text-body-sm font-bold text-white leading-tight" id="toast-title">Berhasil!</p>
        <p class="text-[11px] text-white/70 mt-0.5 leading-snug" id="toast-msg">Aksi berhasil dilakukan.</p>
    </div>
</div>

<script>
    // Smooth navbar shadow on scroll
    window.addEventListener('scroll', () => {
        const header = document.querySelector('header');
        if (window.scrollY > 10) {
            header.classList.add('scrolled');
        } else {
            header.classList.remove('scrolled');
        }
    });

    // Toast Manager
    let toastTimeout;
    function showToast(title, message, type = '') {
        const toast = document.getElementById('toast');
        const toastTitle = document.getElementById('toast-title');
        const toastMsg = document.getElementById('toast-msg');
        const toastIconContainer = document.getElementById('toast-icon-container');
        const toastIcon = document.getElementById('toast-icon');

        if (!toast || !toastTitle || !toastMsg || !toastIconContainer || !toastIcon) return;

        toastTitle.textContent = title;
        toastMsg.textContent = message;

        // Auto-detect type if not provided
        if (!type) {
            const lowerTitle = title.toLowerCase();
            const lowerMsg = message.toLowerCase();
            if (lowerTitle.includes('error') || lowerTitle.includes('gagal') || lowerTitle.includes('salah') || lowerTitle.includes('tidak') || lowerMsg.includes('gagal') || lowerMsg.includes('salah') || lowerMsg.includes('tidak cocok')) {
                type = 'error';
            } else if (lowerTitle.includes('sukses') || lowerTitle.includes('berhasil') || lowerTitle.includes('masuk') || lowerTitle.includes('pendaftaran') || lowerTitle.includes('salin')) {
                type = 'success';
            } else if (lowerTitle.includes('favorit')) {
                type = 'favorite';
            } else if (lowerTitle.includes('proses') || lowerTitle.includes('tunggu')) {
                type = 'processing';
            } else {
                type = 'info';
            }
        }

        // Reset class lists
        toast.className = "fixed top-6 left-1/2 -translate-x-1/2 w-[90%] md:w-[360px] md:left-auto md:right-6 md:transform-none z-[200] text-white px-5 py-3.5 rounded-xl shadow-lg border flex items-center gap-3.5 toast-notification";
        toastIconContainer.className = "w-8 h-8 rounded-full flex items-center justify-center shrink-0";
        
        // Apply type-specific styles
        if (type === 'error') {
            toast.classList.add('bg-red-600', 'border-red-500/20');
            toastIconContainer.classList.add('bg-white/20');
            toastIcon.textContent = 'error';
        } else if (type === 'success') {
            toast.classList.add('bg-emerald-600', 'border-emerald-500/20');
            toastIconContainer.classList.add('bg-white/20');
            toastIcon.textContent = 'check_circle';
        } else if (type === 'favorite') {
            toast.classList.add('bg-rose-600', 'border-rose-500/20');
            toastIconContainer.classList.add('bg-white/20');
            toastIcon.textContent = 'favorite';
        } else if (type === 'processing') {
            toast.classList.add('bg-blue-600', 'border-blue-500/20');
            toastIconContainer.classList.add('bg-white/20');
            toastIcon.textContent = 'sync';
            toastIcon.classList.add('animate-spin');
        } else {
            toast.classList.add('bg-slate-800', 'border-white/10');
            toastIconContainer.classList.add('bg-secondary');
            toastIcon.textContent = 'info';
        }

        if (type !== 'processing') {
            toastIcon.classList.remove('animate-spin');
        }

        toast.classList.add('show');
        clearTimeout(toastTimeout);
        toastTimeout = setTimeout(() => {
            toast.classList.remove('show');
        }, 4000);
    }
</script>

<!-- Floating WhatsApp Support Widget (Only visible on Home, Catalog, Categories, and Product Detail pages) -->
<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$allowedPages = ['index.php', 'product-detail.php', 'products.php', 'category.php', 'categories.php'];
if (in_array($currentPage, $allowedPages)):
    $rawPhone = $storeSettings['phone'] ?? '082293924242';
    $cleanPhone = preg_replace('/[^0-9]/', '', $rawPhone);
    if (strpos($cleanPhone, '0') === 0) {
        $cleanPhone = '62' . substr($cleanPhone, 1);
    }
    $waNumber = $cleanPhone;
?>
<a href="https://wa.me/<?= $waNumber ?>?text=Halo%20TC%20Komputer,%20saya%20ingin%20bertanya%20terkait%20produk%20IT/Hardware" target="_blank" class="fixed bottom-6 right-6 z-[90] bg-[#25D366] text-white px-4 py-2.5 rounded-full shadow-lg hover:bg-[#20ba59] transition-all flex items-center gap-2 select-none group border border-white/10" title="Hubungi CS WhatsApp">
    <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24">
        <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946C.06 5.348 5.397.01 12.008.01c3.202.001 6.212 1.246 8.477 3.514 2.266 2.268 3.507 5.28 3.505 8.484-.004 6.657-5.34 11.997-11.953 11.997-2.005-.001-3.973-.502-5.724-1.458L0 24zm6.59-4.846c1.6.95 3.188 1.449 4.825 1.451 5.436 0 9.86-4.37 9.864-9.799.002-2.63-1.023-5.101-2.885-6.965C16.59 1.978 14.12 .952 11.5 1.052c-5.437 0-9.862 4.371-9.866 9.8-.001 1.762.483 3.486 1.398 5.024l-.993 3.628 3.738-.98c1.512.825 3.203 1.25 4.908 1.25zm5.716-11.111c.29-.116.48-.194.596-.388.116-.194.116-.1.058-.214-.058-.116-.483-1.162-.662-1.59-.174-.42-.35-.362-.483-.37l-.407-.008c-.135 0-.358.05-.546.254-.188.204-.717.7-.717 1.709 0 1.008.733 1.977.836 2.113.103.136 1.443 2.203 3.496 3.088.488.21 1.02.34 1.378.373.358.034.685.016.942-.022.286-.042.88-.36 1.004-.708.125-.348.125-.646.088-.708-.037-.062-.135-.098-.285-.174-.15-.076-.88-.435-1.016-.484-.136-.05-.236-.076-.336.076-.1.15-.388.484-.476.584-.088.1-.176.11-.326.035-.15-.075-.632-.233-1.204-.74-.445-.395-.745-.884-.833-1.034-.088-.15-.01-.23.066-.305.068-.067.15-.175.225-.262.075-.088.1-.15.15-.25.05-.1.025-.188-.013-.263l-.448-1.077c-.12-.29-.25-.25-.34-.25z"/>
    </svg>
    <span class="text-xs font-black tracking-tight">Tanya CS</span>
</a>
<?php endif; ?>

<!-- Main JavaScript -->
<script src="assets/js/main.js?v=2.4"></script>
</body>
</html>
