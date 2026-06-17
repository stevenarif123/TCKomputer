# Implementation Plan: Steven IT Shop

## Overview

A complete PHP native + MySQL e-commerce web application for selling computer products and IT accessories. Implementation follows an incremental approach: database schema first, then configuration/helpers, shared includes, buyer-facing pages, action handlers, admin panel, styling, and JavaScript interactivity.

## Tasks

- [x] 1. Database schema and seed data
  - [x] 1.1 Create database.sql with complete schema
    - Create all tables: admins, categories, products, shipping_areas, orders, order_items, store_settings, banners
    - Define ENUM types for product status (ready, po, habis), condition_type (new, used), payment_method (cod, transfer, pay_on_delivery), payment_status, order_status, shipping_option
    - Add all indexes on slug, category_id, status, order_code, buyer_phone fields
    - Add foreign key constraints between tables
    - _Requirements: All data models from design, 4.5, 4.14, 8.1, 9.1, 10.1, 11.1, 12.1, 13.1_

  - [x] 1.2 Add seed data to database.sql
    - Insert default admin account (email: admin@stevenitshop.com, bcrypt-hashed password)
    - Insert sample categories (Laptop Accessories, Phone Accessories, Cables & Converters, Peripherals, Storage, Printers & Ink, Service Tools)
    - Insert sample products across categories with varied stock statuses
    - Insert sample shipping areas with costs
    - Insert default store settings
    - Insert sample banners
    - _Requirements: 7.2, 9.1, 11.1, 12.1, 13.1_

- [x] 2. Configuration and helper files
  - [x] 2.1 Create config/db.php - Database connection
    - Implement getDBConnection() returning singleton PDO instance
    - Set PDO error mode to EXCEPTION, fetch mode to ASSOC, disable emulated prepares
    - Handle connection errors with generic error message (no credentials exposed)
    - _Requirements: 16.1, 16.5_

  - [x] 2.2 Create config/helpers.php - Utility functions
    - Implement formatRupiah(int $amount): string - dot as thousands separator, "Rp X.XXX" format
    - Implement generateSlug(string $text): string - lowercase, alphanumeric + hyphens, no consecutive/leading/trailing hyphens
    - Implement generateOrderCode(PDO $pdo): string - SIT-YYYYMMDD-XXXX format
    - Implement uploadImage(array $file, string $targetDir): string|false - MIME validation, extension check, size check, PHP content scan
    - Implement deleteImage(string $filename, string $targetDir): bool
    - Implement validateCSRFToken(string $token): bool
    - Implement generateCSRFToken(): string - 32+ bytes cryptographically random
    - Implement sanitizeOutput(string $text): string - htmlspecialchars with ENT_QUOTES, UTF-8
    - Implement truncateText(string $text, int $length): string
    - Implement getStockStatusBadge(string $status, int $stock): string
    - Implement isValidPhoneNumber(string $phone): bool - Indonesian format validation
    - Implement redirect(string $url, string $message, string $type): void
    - Implement getFlashMessage(): ?array
    - Implement paginate() helper
    - _Requirements: 6.1, 6.2, 14.1, 14.2, 15.1-15.5, 16.2, 17.1-17.4, 20.1-20.3_

  - [x] 2.3 Write property test for formatRupiah
    - **Property 11: Rupiah Formatting**
    - Test that any non-negative integer produces "Rp " followed by dot-separated thousands, zero produces "Rp 0"
    - **Validates: Requirements 6.1, 6.2**

  - [x] 2.4 Write property test for generateSlug
    - **Property 10: Slug Generation Correctness**
    - Test that output is lowercase, contains only [a-z0-9-], no consecutive hyphens, no leading/trailing hyphens
    - **Validates: Requirements 17.1, 17.2, 17.3, 17.4, 17.5**

  - [x] 2.5 Write property test for uploadImage validation
    - **Property 9: Upload Safety**
    - Test MIME validation, extension check, size limit, PHP content rejection
    - **Validates: Requirements 15.1, 15.2, 15.3, 15.4**

  - [x] 2.6 Create config/admin-auth.php - Admin authentication guard
    - Implement requireAdmin(): void - redirect to login if not authenticated
    - Implement isAdminLoggedIn(): bool
    - Implement getAdminData(PDO $pdo): ?array
    - Implement adminLogin(PDO $pdo, string $email, string $password): bool - password_verify with bcrypt
    - Implement adminLogout(): void - session destroy
    - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5_

  - [x] 2.7 Write property test for CSRF token validation
    - **Property 8: CSRF Protection**
    - Test that generated tokens are validated correctly, and mismatched/missing tokens are rejected
    - **Validates: Requirements 14.1, 14.2, 14.3**

- [x] 3. Shared includes (header, footer, navigation)
  - [x] 3.1 Create includes/header.php - Buyer header
    - HTML5 doctype, meta viewport for mobile-first
    - Link to assets/css/style.css
    - Store name/logo from settings
    - Sticky navigation with links: Home, Products, Categories, Cart (with badge), Track Order
    - Hamburger menu toggle for mobile (below 768px)
    - Flash message display area
    - _Requirements: 18.1, 18.3, 18.4, 19.2, 19.3, 20.2, 20.3_

  - [x] 3.2 Create includes/footer.php - Buyer footer
    - Store contact info, footer text from settings
    - Link to assets/js/main.js
    - _Requirements: 13.3_

  - [x] 3.3 Create includes/admin-header.php - Admin header/sidebar
    - Admin navigation: Dashboard, Products, Categories, Orders, Shipping Areas, Banners, Settings, Logout
    - Admin user display
    - Flash message display area
    - _Requirements: 7.1, 20.4_

  - [x] 3.4 Create includes/admin-footer.php - Admin footer
    - Link to assets/js/admin.js
    - Close HTML structure

- [x] 4. Checkpoint - Core infrastructure verification
  - Ensure database schema imports cleanly, config files load without errors, includes render properly. Ask the user if questions arise.

- [x] 5. Buyer-facing pages
  - [x] 5.1 Create index.php - Homepage
    - Hero section with store headline
    - Active banners carousel/section ordered by sort_order
    - Category navigation grid (active categories)
    - Featured products section (is_featured=1, is_active=1, limit 8, newest first)
    - Newest products section (is_active=1, limit 8, newest first)
    - Store advantages section
    - _Requirements: 19.1_

  - [x] 5.2 Create products.php - All products listing
    - Paginated product grid (12 per page, newest first default)
    - Search by keyword (name, brand, description)
    - Category filter dropdown
    - Status filter (Ready, PO)
    - Sort options (newest, cheapest, expensive)
    - Product cards with image, name, price, category, status badge
    - Pagination controls
    - "No products found" message when empty
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7, 1.8, 1.9_

  - [x] 5.3 Write property test for product filtering
    - **Property 13: Search and Filter Correctness**
    - Test that all returned products satisfy all active filters, only active products appear
    - **Validates: Requirements 1.2, 1.3, 1.4**

  - [x] 5.4 Write property test for pagination bounds
    - **Property 14: Pagination Bounds**
    - Test each page has at most 12 products, total across pages equals total matching
    - **Validates: Requirements 1.1, 1.7**

  - [x] 5.5 Create category.php - Category page
    - Display category info (name, description, image)
    - Filtered product grid for selected category
    - Same filter/sort/pagination as products.php
    - 404 if category slug not found or inactive
    - _Requirements: 1.3, 9.6, 19.4_

  - [x] 5.6 Create product-detail.php - Product detail page
    - Display product image (or placeholder), name, price, brand, model, description, specification, condition, warranty, status
    - "Add to Cart" button for Ready/PO products
    - Disabled "Habis" button for sold out products
    - 404 page if slug not found or product inactive
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5_

  - [x] 5.7 Create cart.php - Shopping cart page
    - Display all cart items with images, names, current prices, quantities, subtotals
    - Quantity update form (+ / - or input)
    - Remove item button
    - Cart total display
    - "Proceed to Checkout" button
    - Empty cart message with link to products
    - _Requirements: 3.5, 3.6, 3.7, 3.8, 3.9_

  - [x] 5.8 Create checkout.php - Checkout form
    - Redirect to cart if cart empty
    - Order summary section
    - Form: buyer name, phone, address, shipping area dropdown, payment method, shipping option, order notes
    - Dynamic shipping cost display
    - Total calculation (subtotal + shipping)
    - CSRF token hidden field
    - Display bank account/COD/shipping info from settings
    - _Requirements: 4.1, 4.2, 4.3, 4.10, 4.11, 4.15, 13.3, 14.1_

  - [x] 5.9 Create order-success.php - Order confirmation page
    - Display order code
    - Success message with next steps
    - Links to track order and continue shopping
    - _Requirements: 4.8_

  - [x] 5.10 Create track-order.php - Order tracking page
    - Form: order code input, phone number input
    - Validation before DB lookup (format check)
    - Display order details: status, payment status, items, shipping, totals, admin notes
    - "Pesanan tidak ditemukan" if no match
    - _Requirements: 5.1, 5.2, 5.3, 5.4_

- [x] 6. Checkpoint - Buyer pages verification
  - Ensure all buyer-facing pages render without PHP errors, database queries execute properly. Ask the user if questions arise.

- [x] 7. Action handlers
  - [x] 7.1 Create actions/cart-add.php
    - Validate CSRF token
    - Validate product exists, is active, is purchasable (Ready or PO)
    - For Ready products: cap quantity at available stock
    - Add to session cart or increment existing quantity
    - Flash message feedback
    - Redirect back
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 14.2_

  - [x] 7.2 Write property test for cart add logic
    - **Property 1: Cart Consistency**
    - **Property 6: Purchase Constraint**
    - **Property 18: Cart Quantity Cap for Ready Products**
    - Test that cart items always have qty > 0, product exists/active, stock cap enforced for Ready products, Habis products rejected
    - **Validates: Requirements 3.1, 3.2, 2.3**

  - [x] 7.3 Create actions/cart-update.php
    - Validate CSRF token
    - Validate quantity >= 1
    - For Ready products: cap at available stock
    - For PO products: no stock limit
    - Update session cart
    - Flash message feedback
    - Redirect to cart
    - _Requirements: 3.6, 3.7, 14.2_

  - [x] 7.4 Create actions/cart-remove.php
    - Validate CSRF token
    - Remove item from session cart
    - Flash message confirmation
    - Redirect to cart
    - _Requirements: 3.8, 14.2_

  - [x] 7.5 Create actions/checkout-process.php
    - Validate CSRF token
    - Validate all inputs (name, phone, address, shipping area, payment method, shipping option)
    - Re-validate cart items are still purchasable
    - Check stock sufficiency for Ready products
    - Begin database transaction
    - Generate unique order code
    - Insert order record
    - Insert order items
    - Decrease stock for Ready products
    - Auto-set status to 'habis' if stock reaches 0
    - Set initial payment status based on payment method
    - Set initial order status to 'menunggu_konfirmasi'
    - Commit transaction, clear cart, redirect to success
    - On error: rollback, preserve cart, show error
    - _Requirements: 4.2, 4.3, 4.4, 4.5, 4.6, 4.7, 4.8, 4.9, 4.10, 4.11, 4.12, 4.13, 4.14, 4.16, 14.2_

  - [x] 7.6 Write property test for order total integrity
    - **Property 2: Order Total Integrity**
    - Test that order total = subtotal + shipping, subtotal = sum of item subtotals
    - **Validates: Requirements 4.3, 6.3**

  - [x] 7.7 Write property test for order code uniqueness
    - **Property 3: Order Code Uniqueness and Format**
    - Test that generated codes match SIT-YYYYMMDD-XXXX format and are unique
    - **Validates: Requirements 4.5**

  - [x] 7.8 Write property test for stock non-negativity
    - **Property 4: Stock Non-Negativity**
    - **Property 5: Stock-Status Consistency**
    - Test that stock never goes below zero after orders, and status transitions correctly when stock hits zero
    - **Validates: Requirements 4.6, 4.7**

  - [x] 7.9 Write property test for checkout input validation
    - **Property 15: Checkout Input Validation**
    - Test all validation rules: name 3-100 chars, phone format, address min 10, valid payment/shipping options
    - **Validates: Requirements 4.2, 4.10, 4.11**

- [x] 8. Checkpoint - Buyer flow verification
  - Ensure complete buyer flow works: browse → add to cart → checkout → order success → track order. Ask the user if questions arise.

- [x] 9. Admin authentication
  - [x] 9.1 Create admin/login.php
    - Login form with email, password, CSRF token
    - POST handler: validate CSRF, authenticate with password_verify
    - On success: regenerate session ID, set admin_id in session, redirect to dashboard
    - On failure: error message without revealing which field is wrong
    - _Requirements: 7.1, 7.2, 7.4, 7.5, 7.6, 14.1_

  - [x] 9.2 Write property test for admin session guard
    - **Property 7: Admin Session Guard**
    - Test that requests without valid admin session are redirected to login
    - **Validates: Requirements 7.1**

  - [x] 9.3 Create admin/logout.php
    - Destroy session, redirect to login page
    - _Requirements: 7.3_

- [x] 10. Admin dashboard and product management
  - [x] 10.1 Create admin/index.php - Dashboard
    - Summary stats: total products, total orders, pending orders, total revenue
    - Recent orders list (last 5)
    - Quick links to main admin sections
    - _Requirements: 7.1_

  - [x] 10.2 Create admin/products.php - Product list
    - Paginated list (10 per page) with name, price, stock, status, category
    - Search/filter functionality
    - Action links: Add, Edit, Delete
    - _Requirements: 8.8_

  - [x] 10.3 Create admin/product-add.php - Add product form
    - Form with all product fields: name, category, SKU, brand, model, description, specification, purchase price, selling price, stock, status, condition, warranty, image, featured, active
    - Server-side validation (name required, price > 0, category required)
    - Auto-generate unique slug
    - Image upload with validation
    - CSRF token
    - Flash message feedback
    - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5, 8.9, 14.1, 17.6, 17.8_

  - [x] 10.4 Create admin/product-edit.php - Edit product form
    - Pre-populate form with existing product data
    - Update modified fields
    - Replace image if new one uploaded, keep old if not
    - CSRF token validation
    - _Requirements: 8.6, 14.2_

  - [x] 10.5 Create admin/product-delete.php - Delete product action
    - Validate CSRF token
    - Delete product record
    - Delete associated image file
    - Flash message confirmation
    - Redirect to product list
    - _Requirements: 8.7, 14.2_

- [x] 11. Admin category management
  - [x] 11.1 Create admin/categories.php - Category list
    - Display all categories with name, slug, product count, active status, sort order
    - Action links: Add, Edit, Delete
    - _Requirements: 9.1_

  - [x] 11.2 Create admin/category-add.php - Add category
    - Form: name, description, image, active status, sort order
    - Name uniqueness validation
    - Auto-generate slug with uniqueness handling
    - Image upload validation
    - CSRF token
    - _Requirements: 9.1, 9.3, 9.7, 14.1, 17.7_

  - [x] 11.3 Create admin/category-edit.php - Edit category
    - Pre-populate form with existing data
    - Update modified fields
    - Name uniqueness check (excluding current)
    - Image replacement if new uploaded
    - CSRF token validation
    - _Requirements: 9.2, 14.2_

  - [x] 11.4 Create admin/category-delete.php - Delete category
    - Check if category has assigned products
    - Reject deletion if products exist with error message
    - Delete category record and image if no products
    - CSRF token validation
    - _Requirements: 9.4, 9.5, 14.2_

- [x] 12. Admin order management
  - [x] 12.1 Create admin/orders.php - Order list
    - Paginated list (15 per page) with order code, buyer name, total, payment status, order status, date
    - Sorted by creation date descending
    - _Requirements: 10.1_

  - [x] 12.2 Create admin/order-detail.php - Order detail view
    - Display order code, buyer details, order items (quantities, prices), shipping area, payment/order status, dates, admin notes
    - Status update form
    - Admin notes update form
    - _Requirements: 10.2_

  - [x] 12.3 Create admin/order-update-status.php - Update order status
    - Validate CSRF token
    - Validate status transition (menunggu_konfirmasi → diproses → siap_diantar → dikirim → selesai, dibatalkan from any except selesai)
    - Reject invalid transitions with error message
    - Update order status, payment status, or admin notes
    - Update timestamp
    - Flash message feedback
    - _Requirements: 10.3, 10.4, 10.5, 10.6, 14.2_

  - [x] 12.4 Write property test for order status transitions
    - **Property 12: Order Status Transitions**
    - Test valid state machine transitions and rejection of invalid transitions
    - **Validates: Requirements 10.4**

- [x] 13. Admin shipping area management
  - [x] 13.1 Create admin/shipping-areas.php - Shipping area list and CRUD
    - Display all shipping areas with name, cost, active status
    - Inline add/edit form or separate pages
    - _Requirements: 11.1, 11.2_

  - [x] 13.2 Create admin/shipping-area-add.php - Add shipping area
    - Form: area name (1-100 chars), cost (0-1,000,000), active status
    - Validation and CSRF token
    - _Requirements: 11.1, 11.5, 14.1_

  - [x] 13.3 Create admin/shipping-area-edit.php - Edit shipping area
    - Pre-populate form, update fields
    - Validation and CSRF token
    - _Requirements: 11.2, 11.5, 14.2_

  - [x] 13.4 Create admin/shipping-area-delete.php - Delete shipping area
    - CSRF token validation
    - Delete record
    - Flash message
    - _Requirements: 14.2_

  - [x] 13.5 Write property test for active-only shipping display
    - **Property 19: Active-Only Display for Shipping Areas**
    - Test that inactive shipping areas never appear in buyer checkout options
    - **Validates: Requirements 11.3, 11.4**

- [x] 14. Admin banner management
  - [x] 14.1 Create admin/banners.php - Banner list
    - Display all banners with title, image preview, sort order, active status
    - Action links: Add, Edit, Delete
    - _Requirements: 12.1_

  - [x] 14.2 Create admin/banner-add.php - Add banner
    - Form: title, description, image (required), link URL, sort order, active status
    - Image upload validation (jpg/jpeg/png/webp, max 2MB)
    - CSRF token
    - _Requirements: 12.1, 12.2, 12.3, 14.1_

  - [x] 14.3 Create admin/banner-edit.php - Edit banner
    - Pre-populate form, update modified fields
    - Preserve existing image if no new upload
    - CSRF token validation
    - _Requirements: 12.5, 14.2_

  - [x] 14.4 Create admin/banner-delete.php - Delete banner
    - CSRF token validation
    - Delete banner record and image file
    - Flash message
    - _Requirements: 12.6, 14.2_

- [x] 15. Admin store settings
  - [x] 15.1 Create admin/settings.php - Store settings form
    - Form: store name, phone, address, email, logo upload, bank account info, COD info, shipping info, footer text
    - Validate required fields (store name, phone, address, email)
    - Email format validation
    - Logo upload with image validation
    - Replace old logo on new upload
    - CSRF token
    - _Requirements: 13.1, 13.2, 13.4, 14.1_

- [x] 16. Checkpoint - Admin panel verification
  - Ensure all admin CRUD operations work, auth guard protects pages, flash messages display. Ask the user if questions arise.

- [x] 17. CSS styling
  - [x] 17.1 Create assets/css/style.css - Buyer styles
    - CSS reset/normalize
    - Mobile-first responsive layout (base < 768px)
    - Responsive breakpoints: 768px (tablet), 1024px (desktop)
    - Product grid: 1 col mobile, 2 cols tablet, 3-4 cols desktop
    - Sticky header navigation
    - Hamburger menu for mobile
    - Cart badge styling
    - Product cards, badges (green/yellow/red for status)
    - Form styling (checkout, tracking)
    - Flash message styles (green success, yellow warning, red error)
    - Pagination controls
    - Buttons (primary, outline, disabled)
    - No horizontal overflow on 320px viewport
    - _Requirements: 18.1, 18.2, 18.3, 18.4, 18.5, 20.3_

  - [x] 17.2 Create assets/css/admin.css - Admin panel styles
    - Admin layout with sidebar navigation
    - Tables for product/order/category lists
    - Form styling for CRUD operations
    - Dashboard cards for stats
    - Responsive admin layout
    - _Requirements: 8.8, 10.1_

- [x] 18. JavaScript
  - [x] 18.1 Create assets/js/main.js - Buyer interactions
    - Hamburger menu toggle
    - Shipping cost dynamic calculation on checkout (fetch area cost, update display)
    - Flash message auto-dismiss (optional)
    - Cart quantity +/- buttons
    - _Requirements: 4.3, 18.3, 19.3_

  - [x] 18.2 Create assets/js/admin.js - Admin interactions
    - Delete confirmation dialogs
    - Image preview on file select
    - Form validation feedback (client-side supplement)
    - _Requirements: 8.7, 9.5_

- [x] 19. Flash message system and output sanitization integration
  - [x] 19.1 Integrate flash messages across all action handlers
    - Verify all cart actions, checkout, admin CRUD set appropriate flash messages
    - Verify flash messages display once and are cleared from session
    - Verify type-based styling (success/warning/error)
    - _Requirements: 20.1, 20.2, 20.3, 20.4, 20.5_

  - [x] 19.2 Write property test for flash message single-use
    - **Property 16: Flash Message Single-Use**
    - Test that flash messages are removed after retrieval
    - **Validates: Requirements 20.2**

  - [x] 19.3 Write property test for output sanitization
    - **Property 17: Output Sanitization**
    - Test that HTML special characters are properly escaped in all user-generated content output
    - **Validates: Requirements 16.2**

- [x] 20. Upload directories and file structure
  - [x] 20.1 Create upload directory structure and placeholder files
    - Create uploads/products/.gitkeep
    - Create uploads/categories/.gitkeep
    - Create uploads/banners/.gitkeep
    - Create uploads/logo/.gitkeep
    - Create a default placeholder image for products without images
    - _Requirements: 2.5, 15.5_

- [x] 21. README documentation
  - [x] 21.1 Create README.md
    - Project description and features
    - System requirements (PHP >= 7.4, MySQL >= 5.7, Apache/Nginx)
    - Installation steps (import database.sql, configure db.php, create upload dirs)
    - Default admin credentials
    - Project structure overview
    - _Requirements: All_

- [x] 22. Final checkpoint - Full application verification
  - Ensure all tests pass, complete buyer flow and admin flow work end-to-end. Ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests validate universal correctness properties from the design document
- All code uses PHP native with PDO prepared statements (no frameworks)
- Currency stored as integers in Rupiah (no decimals)
- Mobile-first approach: styles designed for small screens first, enhanced for larger
- All user input is validated server-side; client-side validation is supplementary only
- Image uploads validated by MIME type (finfo), extension, size, and content scanning

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1"] },
    { "id": 1, "tasks": ["1.2", "2.1"] },
    { "id": 2, "tasks": ["2.2", "2.6"] },
    { "id": 3, "tasks": ["2.3", "2.4", "2.5", "2.7", "3.1", "3.2", "3.3", "3.4"] },
    { "id": 4, "tasks": ["5.1", "5.2", "5.5", "5.6", "5.7", "5.8", "5.9", "5.10", "20.1"] },
    { "id": 5, "tasks": ["5.3", "5.4", "7.1", "7.3", "7.4", "7.5"] },
    { "id": 6, "tasks": ["7.2", "7.6", "7.7", "7.8", "7.9", "9.1", "9.3"] },
    { "id": 7, "tasks": ["9.2", "10.1", "10.2", "10.3", "10.4", "10.5"] },
    { "id": 8, "tasks": ["11.1", "11.2", "11.3", "11.4", "12.1", "12.2", "12.3"] },
    { "id": 9, "tasks": ["12.4", "13.1", "13.2", "13.3", "13.4", "13.5"] },
    { "id": 10, "tasks": ["14.1", "14.2", "14.3", "14.4", "15.1"] },
    { "id": 11, "tasks": ["17.1", "17.2", "18.1", "18.2"] },
    { "id": 12, "tasks": ["19.1", "19.2", "19.3", "21.1"] }
  ]
}
```
