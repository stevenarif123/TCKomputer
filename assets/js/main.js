/**
 * Steven IT Shop - Buyer Interactions
 * Handles hamburger menu toggle, flash message auto-dismiss, and cart quantity buttons.
 * Note: Shipping cost dynamic calculation is handled inline in checkout.php.
 */

(function () {
    'use strict';

    /**
     * Hamburger Menu Toggle
     * Toggles 'active' class on .main-nav and aria-expanded on .hamburger-menu.
     * Requirements: 18.3, 19.3
     */
    function initHamburgerMenu() {
        var hamburger = document.querySelector('.hamburger-menu');
        var mainNav = document.querySelector('.main-nav');

        if (!hamburger || !mainNav) return;

        hamburger.addEventListener('click', function () {
            var isExpanded = hamburger.getAttribute('aria-expanded') === 'true';

            hamburger.setAttribute('aria-expanded', String(!isExpanded));
            mainNav.classList.toggle('active');
            hamburger.classList.toggle('active');
        });

        // Close menu when clicking a nav link (mobile UX improvement)
        var navLinks = mainNav.querySelectorAll('.nav-link');
        for (var i = 0; i < navLinks.length; i++) {
            navLinks[i].addEventListener('click', function () {
                mainNav.classList.remove('active');
                hamburger.classList.remove('active');
                hamburger.setAttribute('aria-expanded', 'false');
            });
        }
    }

    /**
     * Flash Message Auto-Dismiss and Close Button
     * Optional auto-dismiss after 5 seconds; close button hides the flash.
     * Requirements: 4.3 (user feedback)
     */
    function initFlashMessages() {
        var flashMessage = document.querySelector('.flash-message');

        if (!flashMessage) return;

        // Close button functionality
        var closeBtn = flashMessage.querySelector('.flash-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', function () {
                flashMessage.style.opacity = '0';
                flashMessage.style.transition = 'opacity 0.3s ease';
                setTimeout(function () {
                    flashMessage.style.display = 'none';
                }, 300);
            });
        }

        // Auto-dismiss after 5 seconds
        setTimeout(function () {
            if (flashMessage && flashMessage.style.display !== 'none') {
                flashMessage.style.opacity = '0';
                flashMessage.style.transition = 'opacity 0.3s ease';
                setTimeout(function () {
                    flashMessage.style.display = 'none';
                }, 300);
            }
        }, 5000);
    }

    /**
     * Cart Quantity +/- Buttons Enhancement
     * Prevents form submission for +/- buttons and updates input value instead,
     * allowing the user to adjust quantity before manually submitting.
     * Falls back gracefully when JS is disabled (buttons submit the form directly).
     */
    function initCartQuantityButtons() {
        var quantityForms = document.querySelectorAll('.quantity-form');

        if (!quantityForms.length) return;

        for (var i = 0; i < quantityForms.length; i++) {
            (function (form) {
                var minusBtn = form.querySelector('.btn-minus');
                var plusBtn = form.querySelector('.btn-plus');
                var quantityInput = form.querySelector('.quantity-input');

                if (!minusBtn || !plusBtn || !quantityInput) return;

                minusBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    var currentVal = parseInt(quantityInput.value, 10) || 1;
                    if (currentVal > 1) {
                        quantityInput.value = currentVal - 1;
                        form.submit();
                    }
                });

                plusBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    var currentVal = parseInt(quantityInput.value, 10) || 1;
                    quantityInput.value = currentVal + 1;
                    form.submit();
                });
            })(quantityForms[i]);
        }
    }

    // Initialize all components when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function init() {
        initHamburgerMenu();
        initFlashMessages();
        initCartQuantityButtons();
    }
})();
