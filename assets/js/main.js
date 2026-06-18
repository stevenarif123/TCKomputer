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

    /**
     * FAQ Client-Side Search and Filter
     * Filters FAQ items and categories based on search input value.
     * Requirements: 3.1, 3.2, 3.3, 3.4
     */
    function initFaqSearch() {
        var searchInput = document.getElementById('faq-search');
        if (!searchInput) return;

        searchInput.addEventListener('input', function (e) {
            var searchVal = e.target.value.toLowerCase().trim();
            var faqItems = document.querySelectorAll('.faq-item');
            var categories = document.querySelectorAll('.faq-category-section');

            // 1. Loop through all .faq-item elements.
            for (var i = 0; i < faqItems.length; i++) {
                var item = faqItems[i];
                var questionEl = item.querySelector('button span');
                var answerEl = item.querySelector('.faq-answer');
                
                var questionText = questionEl ? questionEl.textContent.toLowerCase() : '';
                var answerText = answerEl ? answerEl.textContent.toLowerCase() : '';

                if (searchVal === '' || questionText.indexOf(searchVal) !== -1 || answerText.indexOf(searchVal) !== -1) {
                    item.classList.remove('hidden');
                } else {
                    item.classList.add('hidden');
                }
            }

            // 2. Loop through all .faq-category-section elements.
            for (var j = 0; j < categories.length; j++) {
                var cat = categories[j];
                var activeItems = cat.querySelectorAll('.faq-item:not(.hidden)');

                if (searchVal === '' || activeItems.length > 0) {
                    cat.classList.remove('hidden');
                } else {
                    cat.classList.add('hidden');
                }
            }
        });
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
        initFaqSearch();
    }
})();

/**
 * FAQ Accordion Toggle
 * Toggles the visibility of the corresponding answer and rotates the chevron.
 * Accessible globally via onclick="toggleFaq(this)".
 * Requirements: 2.1, 2.2, 2.3
 */
window.toggleFaq = function (button) {
    if (!button) return;

    // Find the corresponding answer element (which is the next sibling element in the DOM)
    var faqAnswer = button.nextElementSibling;
    if (faqAnswer && faqAnswer.classList.contains('faq-answer')) {
        faqAnswer.classList.toggle('hidden');
    }

    // Find the chevron icon inside the button and rotate it
    var faqChevron = button.querySelector('.faq-chevron');
    if (faqChevron) {
        faqChevron.classList.toggle('rotate-180');
    }
};

