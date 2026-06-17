/**
 * Admin Panel JavaScript
 * Handles sidebar toggle, image preview, flash message auto-dismiss,
 * and client-side form validation feedback.
 */

document.addEventListener('DOMContentLoaded', function () {

    // =========================================================================
    // 1. Sidebar Toggle (for mobile)
    // =========================================================================
    var sidebarToggle = document.getElementById('sidebarToggle');
    var adminSidebar = document.querySelector('.admin-sidebar');

    if (sidebarToggle && adminSidebar) {
        sidebarToggle.addEventListener('click', function () {
            adminSidebar.classList.toggle('active');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function (e) {
            if (adminSidebar.classList.contains('active') &&
                !adminSidebar.contains(e.target) &&
                !sidebarToggle.contains(e.target)) {
                adminSidebar.classList.remove('active');
            }
        });
    }

    // =========================================================================
    // 2. Image Preview & Client-Side Cropping
    // =========================================================================
    var fileInputs = document.querySelectorAll('input[type="file"][accept*="image"], input[type="file"][data-crop="true"]');
 
    fileInputs.forEach(function (input) {
        input.addEventListener('change', function (e) {
            // Prevent recursive trigger when files are replaced programmatically
            if (input.dataset.isCropping) return;
 
            var file = e.target.files[0];
            if (!file) return;
 
            // Validate file type client-side
            var allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
            if (allowedTypes.indexOf(file.type) === -1) {
                showInputError(input, 'Format file harus JPG, PNG, atau WebP');
                input.value = '';
                removePreview(input);
                return;
            }
 
            // Validate file size (allow raw files up to 10MB before cropping)
            var maxRawSize = 10 * 1024 * 1024;
            if (file.size > maxRawSize) {
                showInputError(input, 'Ukuran file mentah maksimal 10MB sebelum dipotong');
                input.value = '';
                removePreview(input);
                return;
            }
 
            clearInputError(input);
 
            if (input.dataset.crop === 'true') {
                openCropModal(input, file);
            } else {
                showNormalPreview(input, file);
            }
        });
    });
 
    /**
     * Open Cropper.js Modal for Image Cropping & Compression
     */
    function openCropModal(input, file) {
        var reader = new FileReader();
        reader.onload = function (event) {
            var imageUrl = event.target.result;
 
            // Create crop modal DOM element if it doesn't exist
            var modalOverlay = document.getElementById('cropModalOverlay');
            if (!modalOverlay) {
                modalOverlay = document.createElement('div');
                modalOverlay.id = 'cropModalOverlay';
                modalOverlay.className = 'crop-modal-overlay';
                modalOverlay.innerHTML = `
                    <div class="crop-modal-container">
                        <div class="crop-modal-header">
                            <h3 style="display: flex; align-items: center; gap: 10px;">
                                Potong Gambar
                                <button type="button" class="btn" id="cropModalToggleRatio" style="display: none; font-size: 0.7rem; padding: 2px 8px; font-weight: 600; cursor: pointer; border: 1px solid var(--admin-border); border-radius: 4px; background-color: var(--admin-card-bg); color: var(--admin-text); transition: all 0.2s ease;">Bebaskan Rasio</button>
                            </h3>
                            <button type="button" class="crop-modal-close" id="cropModalCloseBtn">&times;</button>
                        </div>
                        <div class="crop-modal-body">
                            <div class="crop-area-wrapper">
                                <img id="cropModalImage" src="" alt="Crop Area">
                            </div>
                            <p class="crop-modal-info">Sesuaikan kotak pembatas di atas untuk memotong gambar. Klik "Bebaskan Rasio" jika ingin memotong dengan rasio bebas/kustom.</p>
                        </div>
                        <div class="crop-modal-footer">
                            <button type="button" class="btn btn-secondary" id="cropModalCancelBtn">Batal</button>
                            <button type="button" class="btn btn-primary" id="cropModalSaveBtn">Potong & Simpan</button>
                        </div>
                    </div>
                `;
                document.body.appendChild(modalOverlay);
            }
 
            var cropImg = document.getElementById('cropModalImage');
            cropImg.src = imageUrl;
 
            // Show overlay
            setTimeout(function() {
                modalOverlay.classList.add('active');
            }, 50);
 
            // Initialize Cropper.js
            var aspectRatioVal = parseFloat(input.dataset.aspectRatio);
            var isLocked = !isNaN(aspectRatioVal);
            
            var toggleRatioBtn = document.getElementById('cropModalToggleRatio');
            if (toggleRatioBtn) {
                if (isLocked) {
                    toggleRatioBtn.style.display = 'inline-block';
                    toggleRatioBtn.textContent = 'Bebaskan Rasio';
                    toggleRatioBtn.style.backgroundColor = 'var(--admin-card-bg)';
                    toggleRatioBtn.style.color = 'var(--admin-text)';
                } else {
                    toggleRatioBtn.style.display = 'none';
                }
            }
 
            var cropper = new Cropper(cropImg, {
                aspectRatio: isLocked ? aspectRatioVal : null,
                viewMode: 1,
                dragMode: 'move',
                autoCropArea: 0.9,
                restore: false,
                guides: true,
                center: true,
                highlight: false,
                cropBoxMovable: true,
                cropBoxResizable: true,
                toggleDragModeOnDblclick: false,
            });
 
            // Modal action buttons
            var saveBtn = document.getElementById('cropModalSaveBtn');
            var cancelBtn = document.getElementById('cropModalCancelBtn');
            var closeBtn = document.getElementById('cropModalCloseBtn');
 
            // Bind ratio toggle action
            if (toggleRatioBtn && isLocked) {
                toggleRatioBtn.addEventListener('click', handleToggleRatio);
            }
 
            function handleToggleRatio() {
                if (isLocked) {
                    cropper.setAspectRatio(NaN);
                    toggleRatioBtn.textContent = 'Kunci Rasio';
                    toggleRatioBtn.style.backgroundColor = 'var(--admin-primary)';
                    toggleRatioBtn.style.color = '#fff';
                    isLocked = false;
                } else {
                    cropper.setAspectRatio(aspectRatioVal);
                    toggleRatioBtn.textContent = 'Bebaskan Rasio';
                    toggleRatioBtn.style.backgroundColor = 'var(--admin-card-bg)';
                    toggleRatioBtn.style.color = 'var(--admin-text)';
                    isLocked = true;
                }
            }
 
            function cleanup() {
                modalOverlay.classList.remove('active');
                if (cropper) {
                    cropper.destroy();
                }
                saveBtn.removeEventListener('click', handleSave);
                cancelBtn.removeEventListener('click', handleCancel);
                closeBtn.removeEventListener('click', handleCancel);
                if (toggleRatioBtn) {
                    toggleRatioBtn.removeEventListener('click', handleToggleRatio);
                    // Reset styling just in case
                    toggleRatioBtn.style.backgroundColor = '';
                    toggleRatioBtn.style.color = '';
                }
            }
 
            function handleCancel() {
                input.value = '';
                removePreview(input);
                cleanup();
            }
 
            function handleSave() {
                var targetWidth = parseInt(input.dataset.width) || undefined;
                var targetHeight = parseInt(input.dataset.height) || undefined;
 
                var canvasOptions = {
                    imageSmoothingEnabled: true,
                    imageSmoothingQuality: 'high'
                };
                // Use maxWidth/maxHeight instead of forced width/height
                // to preserve custom aspect ratios and avoid stretching/letterboxing smaller images!
                if (targetWidth) canvasOptions.maxWidth = targetWidth;
                if (targetHeight) canvasOptions.maxHeight = targetHeight;
 
                var croppedCanvas = cropper.getCroppedCanvas(canvasOptions);
                if (!croppedCanvas) {
                    alert('Gagal memotong gambar.');
                    cleanup();
                    return;
                }
 
                var mimeType = file.type || 'image/jpeg';
                // Export cropped image as Blob compressed at 85% quality
                croppedCanvas.toBlob(function (blob) {
                    if (!blob) {
                        alert('Gagal memproses ekspor gambar.');
                        cleanup();
                        return;
                    }
 
                    // Create File object
                    var croppedFile = new File([blob], file.name, { type: mimeType });
 
                    // Replace file in input via DataTransfer
                    var dataTransfer = new DataTransfer();
                    dataTransfer.items.add(croppedFile);
 
                    input.dataset.isCropping = 'true';
                    input.files = dataTransfer.files;
                    delete input.dataset.isCropping;
 
                    // Update preview container inside form
                    updateCropPreview(input, croppedCanvas.toDataURL(mimeType), blob.size);
 
                    cleanup();
                }, mimeType, 0.85);
            }
 
            saveBtn.addEventListener('click', handleSave);
            cancelBtn.addEventListener('click', handleCancel);
            closeBtn.addEventListener('click', handleCancel);
        };
        reader.readAsDataURL(file);
    }
 
    /**
     * Show normal image preview for non-cropped files
     */
    function showNormalPreview(input, file) {
        var reader = new FileReader();
        reader.onload = function (event) {
            var preview = getOrCreatePreview(input);
            preview.src = event.target.result;
            preview.style.display = 'block';
 
            // Clear any crop preview if exists
            var containerId = 'crop-prev-container-' + (input.id || input.name);
            var container = document.getElementById(containerId);
            if (container) container.remove();
        };
        reader.readAsDataURL(file);
    }
 
    /**
     * Generate or update a premium cropped preview details display
     */
    function updateCropPreview(input, dataUrl, bytesSize) {
        removePreview(input); // Hide basic preview
 
        var containerId = 'crop-prev-container-' + (input.id || input.name);
        var container = document.getElementById(containerId);
 
        if (!container) {
            container = document.createElement('div');
            container.id = containerId;
            container.className = 'crop-preview-container';
            
            // Insert after the input element
            var sibling = input.nextElementSibling;
            if (sibling && sibling.tagName === 'SMALL') {
                sibling.parentNode.insertBefore(container, sibling.nextSibling);
            } else {
                input.parentNode.insertBefore(container, input.nextSibling);
            }
        }
 
        var kbSize = (bytesSize / 1024).toFixed(1);
        container.innerHTML = `
            <img class="crop-preview-thumb" src="${dataUrl}" alt="Cropped Preview">
            <div class="crop-preview-info">
                <span class="crop-preview-status">✓ Siap Upload (Terpotong)</span>
                <span class="crop-preview-size">Ukuran: ${kbSize} KB</span>
            </div>
        `;
    }
 
    /**
     * Get existing preview element or create one after the file input.
     */
    function getOrCreatePreview(input) {
        var previewId = 'preview-' + (input.id || input.name || 'file');
        var preview = document.getElementById(previewId);
 
        if (!preview) {
            preview = document.createElement('img');
            preview.id = previewId;
            preview.className = 'image-preview';
            preview.style.display = 'none';
            preview.style.maxWidth = '200px';
            preview.style.maxHeight = '200px';
            preview.style.marginTop = '8px';
            preview.style.borderRadius = '4px';
            preview.style.border = '1px solid #ddd';
            preview.alt = 'Preview';
 
            // Insert after the input (or after its help text if present)
            var sibling = input.nextElementSibling;
            if (sibling && sibling.tagName === 'SMALL') {
                sibling.parentNode.insertBefore(preview, sibling.nextSibling);
            } else {
                input.parentNode.insertBefore(preview, input.nextSibling);
            }
        }
 
        return preview;
    }
 
    /**
     * Remove the preview image for a given input.
     */
    function removePreview(input) {
        var previewId = 'preview-' + (input.id || input.name || 'file');
        var preview = document.getElementById(previewId);
        if (preview) {
            preview.style.display = 'none';
            preview.src = '';
        }
        
        var containerId = 'crop-prev-container-' + (input.id || input.name);
        var container = document.getElementById(containerId);
        if (container) {
            container.remove();
        }
    }

    // =========================================================================
    // 3. Flash Message Auto-Dismiss
    // =========================================================================
    var flashMessages = document.querySelectorAll('.flash-message');

    flashMessages.forEach(function (msg) {
        // Auto-dismiss after 5 seconds
        setTimeout(function () {
            msg.style.transition = 'opacity 0.3s ease';
            msg.style.opacity = '0';
            setTimeout(function () {
                msg.style.display = 'none';
            }, 300);
        }, 5000);

        // Allow manual dismiss on click
        msg.style.cursor = 'pointer';
        msg.addEventListener('click', function () {
            msg.style.transition = 'opacity 0.2s ease';
            msg.style.opacity = '0';
            setTimeout(function () {
                msg.style.display = 'none';
            }, 200);
        });
    });

    // =========================================================================
    // 4. Client-Side Form Validation Feedback
    // =========================================================================
    var adminForms = document.querySelectorAll('.admin-form');

    adminForms.forEach(function (form) {
        form.addEventListener('submit', function (e) {
            var isValid = true;

            // Clear previous validation errors
            var existingErrors = form.querySelectorAll('.field-error');
            existingErrors.forEach(function (el) { el.remove(); });

            // Validate required fields
            var requiredInputs = form.querySelectorAll('[required]');
            requiredInputs.forEach(function (input) {
                if (!input.value.trim()) {
                    isValid = false;
                    showInputError(input, 'Field ini wajib diisi');
                } else {
                    clearInputError(input);
                }
            });

            // Validate number fields with min attribute
            var numberInputs = form.querySelectorAll('input[type="number"][min]');
            numberInputs.forEach(function (input) {
                if (input.value !== '' && input.hasAttribute('required')) {
                    var min = parseFloat(input.getAttribute('min'));
                    var val = parseFloat(input.value);
                    if (isNaN(val) || val < min) {
                        isValid = false;
                        showInputError(input, 'Nilai minimal ' + min);
                    }
                }
            });

            if (!isValid) {
                e.preventDefault();
                // Scroll to first error
                var firstError = form.querySelector('.field-error');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });
    });

    /**
     * Display a validation error message below an input field.
     */
    function showInputError(input, message) {
        clearInputError(input);
        input.classList.add('input-error');

        var errorEl = document.createElement('span');
        errorEl.className = 'field-error';
        errorEl.textContent = message;
        errorEl.style.color = '#e74c3c';
        errorEl.style.fontSize = '0.85em';
        errorEl.style.display = 'block';
        errorEl.style.marginTop = '4px';

        input.parentNode.insertBefore(errorEl, input.nextSibling);
    }

    /**
     * Clear validation error for a given input.
     */
    function clearInputError(input) {
        input.classList.remove('input-error');
        var next = input.nextElementSibling;
        if (next && next.classList.contains('field-error')) {
            next.remove();
        }
    }

    // =========================================================================
    // 5. Admin Quick Notepad Auto-save
    // =========================================================================
    var notepad = document.getElementById('notepadTextarea');
    if (notepad) {
        // Load saved note
        notepad.value = localStorage.getItem('admin-notepad') || '';

        // Save note on input change
        notepad.addEventListener('input', function() {
            localStorage.setItem('admin-notepad', notepad.value);
        });
    }

});

// =========================================================================
// 6. Global Theme & Sidebar Toggle Functions
// =========================================================================
window.toggleTheme = function() {
    var isDark = document.body.classList.toggle('dark-mode');
    document.documentElement.classList.toggle('dark-mode', isDark);
    localStorage.setItem('admin-theme', isDark ? 'dark' : 'light');
    var themeIcon = document.getElementById('theme-icon');
    if (themeIcon) {
        themeIcon.textContent = isDark ? 'light_mode' : 'dark_mode';
    }
};

window.toggleSidebar = function() {
    var sidebar = document.getElementById('adminSidebar');
    var overlay = document.getElementById('adminOverlay');
    if (sidebar && overlay) {
        sidebar.classList.toggle('open');
        overlay.classList.toggle('active');
    }
};
