# Requirements Document

## Introduction

Steven IT Shop is a complete e-commerce web application for selling computer products, laptop accessories, phone accessories, cables, converters, peripherals, storage devices, printers, ink, and service tools. The application serves buyers who browse and purchase products (typically arriving from WhatsApp and social media links) and administrators who manage the store's inventory, orders, and settings. The system is built with PHP native and MySQL, prioritizes mobile-first responsive design, and uses Indonesian language interface with Rupiah currency formatting.

## Glossary

- **System**: The Steven IT Shop web application
- **Buyer**: A visitor who browses, searches, and purchases products through the storefront
- **Admin**: An authenticated administrator who manages products, orders, categories, and settings
- **Cart**: A session-based temporary storage of products selected by the Buyer for purchase
- **Order**: A record of a completed purchase including buyer details, items, shipping, and payment information
- **Order_Code**: A unique identifier for each order in format SIT-YYYYMMDD-XXXX
- **Product**: An item available for sale with associated pricing, stock, and metadata
- **Category**: A grouping of related products used for navigation and filtering
- **Shipping_Area**: A predefined geographic zone with an associated delivery cost
- **Stock_Status**: One of three product availability states: Ready (in stock), PO (pre-order), or Habis (sold out)
- **CSRF_Token**: A session-bound token used to prevent cross-site request forgery attacks
- **Slug**: A URL-safe string derived from a product or category name, used in URLs
- **PDO**: PHP Data Objects, the database access abstraction layer used for all queries
- **Flash_Message**: A session-stored message displayed once to the user after a redirect

## Requirements

### Requirement 1: Product Browsing and Display

**User Story:** As a Buyer, I want to browse products with filtering and sorting options, so that I can find the computer accessories I need quickly.

#### Acceptance Criteria

1. WHEN a Buyer visits the products page, THE System SHALL display active products in a paginated grid with 12 products per page, sorted by creation date descending (newest first) as the default order
2. WHEN a Buyer searches by keyword, THE System SHALL return active products where the keyword appears as a case-insensitive substring match against the product name, brand, or description fields
3. WHEN a Buyer selects a category filter, THE System SHALL display only active products belonging to the selected Category
4. WHEN a Buyer selects a status filter, THE System SHALL display only active products matching the selected Stock_Status (Ready or PO)
5. WHEN a Buyer applies multiple filters simultaneously (keyword, category, and/or status), THE System SHALL display only active products matching all selected filter criteria combined
6. WHEN a Buyer selects a sort option, THE System SHALL order products by the chosen criterion: newest (by creation date descending), cheapest (by price ascending), or most expensive (by price descending)
7. THE System SHALL display each product card with the product image (or a placeholder image if none exists), name, selling price formatted in Rupiah, category name, and Stock_Status badge
8. WHEN the total matching products exceed 12, THE System SHALL render pagination controls allowing navigation between pages
9. IF no products match the current search and filter criteria, THEN THE System SHALL display a message indicating no products were found and retain the active filter selections

### Requirement 2: Product Detail View

**User Story:** As a Buyer, I want to view detailed product information, so that I can make an informed purchase decision.

#### Acceptance Criteria

1. WHEN a Buyer navigates to a product detail page using a product Slug, THE System SHALL display the product information including: name, image, selling price formatted in Indonesian Rupiah (e.g., "Rp 1.500.000"), brand, model, description, specification, condition type (new or used), warranty note, and Stock_Status
2. WHEN a Buyer navigates to a product detail page AND the product has Stock_Status of "Ready" or "PO", THE System SHALL display an enabled "Add to Cart" button
3. WHEN a Buyer navigates to a product detail page AND the product has Stock_Status of "Habis", THE System SHALL display a disabled "Habis" button and SHALL NOT allow the product to be added to the cart
4. IF a product Slug does not match any active product, THEN THE System SHALL display a 404 error page with a navigation link back to the products listing page
5. IF the product image path is missing or the image file cannot be loaded, THEN THE System SHALL display a default placeholder image in place of the product image

### Requirement 3: Shopping Cart Management

**User Story:** As a Buyer, I want to manage items in my shopping cart, so that I can review and adjust my selections before checkout.

#### Acceptance Criteria

1. WHEN a Buyer adds a product to the Cart, THE System SHALL verify the product exists, is active, and has a Stock_Status of Ready or PO before adding it to the session
2. WHEN a Buyer adds a product with Stock_Status Ready, THE System SHALL limit the cart quantity to the available stock count
3. IF a Buyer attempts to add a quantity that would exceed available stock for a Ready product, THEN THE System SHALL set the cart quantity to the maximum available stock and display a message indicating the stock limit
4. WHEN a Buyer adds a product already in the Cart, THE System SHALL increment the existing quantity by the requested amount rather than creating a duplicate entry
5. WHEN a Buyer views the Cart page, THE System SHALL display all cart items with current prices fetched from the database, quantities, item subtotals, and a cart total
6. WHEN a Buyer updates a cart item quantity, THE System SHALL validate the new quantity is at least 1 and, for Ready products, does not exceed available stock; for PO products no stock limit SHALL be enforced
7. IF a Buyer submits a cart item quantity that fails validation, THEN THE System SHALL reject the update, retain the previous quantity, and display a message indicating the validation failure reason
8. WHEN a Buyer removes an item from the Cart, THE System SHALL delete that item from the session and update the cart display
9. THE System SHALL persist the Cart across page navigation within the same browser session
10. IF a product's Stock_Status becomes Habis or the product is deactivated between cart addition and checkout, THEN THE System SHALL inform the Buyer that the item is no longer available and prevent checkout of that item

### Requirement 4: Checkout and Order Creation

**User Story:** As a Buyer, I want to complete my purchase through a checkout form, so that I can place an order for delivery or pickup.

#### Acceptance Criteria

1. WHEN a Buyer accesses the checkout page with at least one item in the Cart, THE System SHALL display a form requesting buyer name, phone number, address, shipping area selection, payment method, shipping option, and optional order notes
2. WHEN a Buyer submits the checkout form, THE System SHALL validate that buyer name is 3-100 characters, phone matches Indonesian format (08xx or +628xx, 8-13 digits), and address is 10-500 characters
3. WHEN a Buyer selects a Shipping_Area, THE System SHALL calculate and display the shipping cost from the predefined area cost
4. WHEN checkout validation passes, THE System SHALL create the order within a database transaction including order record and all order items, calculating the subtotal as the sum of (product price × quantity) for all items and the total as subtotal plus shipping cost
5. WHEN an order is created, THE System SHALL generate a unique Order_Code in format SIT-YYYYMMDD-XXXX where XXXX is a zero-padded sequential number (0001-9999) for that date
6. WHEN an order contains products with Stock_Status Ready, THE System SHALL decrease the product stock by the ordered quantity
7. WHEN a product stock reaches zero after an order, THE System SHALL automatically update the product Stock_Status to Habis
8. WHEN an order is successfully created, THE System SHALL clear the Cart and redirect the Buyer to an order success page displaying the Order_Code
9. IF a database error occurs during checkout, THEN THE System SHALL rollback the transaction, preserve the Cart, and display an error message indicating that the order could not be processed
10. THE System SHALL accept payment methods: COD, Transfer, and Pay on Delivery
11. THE System SHALL accept shipping options: Self Pickup, Local Delivery, and Local Courier
12. WHEN payment method is COD, THE System SHALL set initial payment status to "cod"
13. WHEN payment method is not COD, THE System SHALL set initial payment status to "belum_dibayar"
14. THE System SHALL set the initial order status to "menunggu_konfirmasi" for all new orders
15. IF a Buyer accesses the checkout page with an empty Cart, THEN THE System SHALL redirect the Buyer to the Cart page and display an error message indicating that the cart is empty
16. IF the ordered quantity exceeds available stock for any product during checkout, THEN THE System SHALL reject the order, preserve the Cart, and display an error message indicating which product has insufficient stock

### Requirement 5: Order Tracking

**User Story:** As a Buyer, I want to track my order status, so that I can know when my purchase will be ready or delivered.

#### Acceptance Criteria

1. WHEN a Buyer accesses the tracking page, THE System SHALL display a form requesting Order_Code (maximum 17 characters, format SIT-YYYYMMDD-XXXX) and phone number (maximum 15 digits)
2. IF the Buyer submits the tracking form with any empty field or an Order_Code that does not match the format SIT-YYYYMMDD-XXXX, THEN THE System SHALL display a validation error message indicating the invalid field and SHALL NOT perform a database lookup
3. WHEN a Buyer submits valid tracking credentials (Order_Code matching format SIT-YYYYMMDD-XXXX and non-empty phone number), THE System SHALL query the database and display the order status, payment status, shipping cost, subtotal, total, admin notes, and all order items (product name, price, quantity, and item subtotal)
4. IF the Order_Code and phone number combination does not match any order, THEN THE System SHALL display "Pesanan tidak ditemukan" without revealing whether the code or phone was incorrect

### Requirement 6: Currency and Price Formatting

**User Story:** As a Buyer, I want prices displayed in Indonesian Rupiah format, so that I can understand product costs in my local currency.

#### Acceptance Criteria

1. THE System SHALL format all monetary values in the pattern "Rp XX.XXX" using a dot as the thousands separator, no decimal places, and a space between "Rp" and the numeric value
2. THE System SHALL display zero amounts as "Rp 0"
3. THE System SHALL compute and display order totals as the sum of subtotal (sum of each item's unit price multiplied by its quantity) plus shipping cost, where the result is a non-negative integer
4. THE System SHALL accept only integer monetary values in the range 0 to 999,999,999,999 for formatting
5. IF a monetary value is negative, THEN THE System SHALL display it as "Rp 0" and treat it as an invalid amount

### Requirement 7: Admin Authentication

**User Story:** As an Admin, I want secure login access to the admin panel, so that only authorized users can manage store data.

#### Acceptance Criteria

1. WHEN an unauthenticated user accesses any admin page, THE System SHALL redirect to the admin login page
2. WHEN an Admin submits valid credentials (email and password), THE System SHALL authenticate using password_verify against the stored bcrypt hash, create an admin session storing the admin ID, and redirect to the admin dashboard
3. WHEN an Admin logs out, THE System SHALL destroy the admin session and redirect to the login page
4. WHEN an Admin successfully logs in, THE System SHALL regenerate the session ID to prevent session fixation
5. IF an Admin submits invalid credentials (non-matching email or password), THEN THE System SHALL display an error message indicating that the credentials are invalid without revealing which field is incorrect, and SHALL NOT create an admin session
6. IF a login form submission contains an invalid or missing CSRF token, THEN THE System SHALL reject the request, display an error message indicating an invalid form submission, and SHALL NOT process the credentials

### Requirement 8: Admin Product Management

**User Story:** As an Admin, I want to create, edit, and delete products, so that I can maintain the store's inventory.

#### Acceptance Criteria

1. WHEN an Admin creates a product, THE System SHALL require a product name (maximum 255 characters), category, and selling price greater than zero
2. WHEN an Admin creates a product, THE System SHALL auto-generate a unique Slug from the product name by converting it to lowercase and replacing spaces with hyphens
3. IF a generated Slug already exists, THEN THE System SHALL append a timestamp suffix to ensure uniqueness
4. WHEN an Admin uploads a product image, THE System SHALL validate the file is jpg, jpeg, png, or webp format and does not exceed 2MB
5. IF the uploaded image fails format or size validation, THEN THE System SHALL reject the upload and display an error message indicating the specific validation failure
6. WHEN an Admin edits a product, THE System SHALL update all modified fields and replace the image if a new one is uploaded
7. WHEN an Admin deletes a product, THE System SHALL remove the product record and delete the associated image file from storage
8. THE System SHALL display the admin product list with pagination of 10 products per page, showing product name, price, stock, status, and category
9. IF the Admin submits the product creation form with missing required fields or invalid selling price, THEN THE System SHALL display an error message indicating which fields failed validation and preserve the entered data

### Requirement 9: Admin Category Management

**User Story:** As an Admin, I want to manage product categories, so that I can organize products for easy buyer navigation.

#### Acceptance Criteria

1. WHEN an Admin creates a category, THE System SHALL require a category name (1-100 characters, unique across all categories) and auto-generate a slug by converting the name to lowercase, replacing spaces with hyphens, and appending a numeric suffix if the slug already exists
2. WHEN an Admin edits a category, THE System SHALL allow updating the name, description (max 500 characters), image, active status, and sort order (integer between 0 and 999)
3. WHEN an Admin uploads a category image, THE System SHALL accept only JPEG, PNG, or WebP files with a maximum size of 2 MB and store the file in the uploads/categories/ directory
4. IF an Admin attempts to delete a category that has products assigned to it, THEN THE System SHALL reject the deletion and display an error message indicating the category still contains products
5. WHEN an Admin deletes a category with no assigned products, THE System SHALL remove the category record and delete its associated image file from the uploads/categories/ directory
6. THE System SHALL display only categories with active status, ordered by sort_order in ascending order, on the buyer-facing storefront
7. IF an Admin submits a category name that already exists, THEN THE System SHALL reject the submission and display an error message indicating the name is already in use

### Requirement 10: Admin Order Management

**User Story:** As an Admin, I want to view and update order statuses, so that I can process and fulfill customer orders.

#### Acceptance Criteria

1. WHEN an Admin views the orders list, THE System SHALL display orders with pagination of 15 items per page, showing order code, buyer name, total, payment status, order status, and creation date, sorted by creation date descending
2. WHEN an Admin views an order detail, THE System SHALL display order code, buyer details, order items with quantities and prices, shipping area, payment status, order status, creation date, and admin notes
3. WHEN an Admin updates an order, THE System SHALL validate the CSRF_Token and save the updated order status, payment status, or admin notes with an updated timestamp, and display a success notification to the Admin
4. THE System SHALL support order status transitions: menunggu_konfirmasi → diproses → siap_diantar → dikirim → selesai, and dibatalkan from any state except selesai
5. IF an Admin attempts an invalid order status transition, THEN THE System SHALL reject the update and display an error message indicating the transition is not allowed
6. IF the CSRF_Token validation fails during an order update, THEN THE System SHALL reject the update and redirect the Admin to the order detail page without applying changes

### Requirement 11: Admin Shipping Area Management

**User Story:** As an Admin, I want to manage shipping areas and costs, so that buyers can calculate accurate delivery fees.

#### Acceptance Criteria

1. WHEN an Admin creates a Shipping_Area, THE System SHALL require an area name (1 to 100 characters) and a cost amount (integer in Rupiah, minimum 0, maximum 1,000,000)
2. WHEN an Admin edits a Shipping_Area, THE System SHALL allow updating the area name (1 to 100 characters), cost (integer in Rupiah, minimum 0, maximum 1,000,000), and active status
3. WHEN an Admin deactivates a Shipping_Area, THE System SHALL exclude it from the buyer checkout shipping options
4. THE System SHALL only display active Shipping_Areas to Buyers during checkout
5. IF an Admin submits a Shipping_Area with an empty area name or a cost amount outside the range of 0 to 1,000,000, THEN THE System SHALL reject the submission and display an error message indicating which field is invalid

### Requirement 12: Admin Banner Management

**User Story:** As an Admin, I want to manage homepage banners, so that I can promote products and announcements to buyers.

#### Acceptance Criteria

1. WHEN an Admin creates a banner, THE System SHALL require a title (max 255 characters) and image, and optionally accept a description (max 1000 characters), link URL (max 2048 characters), sort order (integer 0-9999, default 0), and active status (default inactive)
2. WHEN an Admin uploads a banner image, THE System SHALL validate the file format is jpg, jpeg, png, or webp and the file size does not exceed 2MB
3. IF a banner image upload fails validation, THEN THE System SHALL reject the submission, retain all entered form data, and display an error message indicating the validation failure reason
4. THE System SHALL display active banners on the homepage ordered by sort_order in ascending order
5. WHEN an Admin edits a banner, THE System SHALL allow updating title, description, image, link URL, sort order, and active status while preserving existing values for unchanged fields
6. WHEN an Admin deletes a banner, THE System SHALL remove the banner record and its associated image file from uploads/banners/

### Requirement 13: Admin Store Settings

**User Story:** As an Admin, I want to configure store information, so that buyers see accurate contact and payment details.

#### Acceptance Criteria

1. WHEN an Admin submits the store settings form, THE System SHALL validate and save the following fields: store name (required, max 255 characters), phone (required, valid Indonesian phone format), address (required), email (required, valid email format), logo (optional, jpg/jpeg/png/webp, max 2MB), bank account info (text), COD info (text), shipping info (text), and footer text (text)
2. IF any required store settings field fails validation, THEN THE System SHALL display an error message indicating which field is invalid and preserve the previously entered values in the form
3. THE System SHALL display the configured store name, logo, phone, and email in the buyer-facing header, the footer text and contact details in the footer, and the bank account info, COD info, and shipping info on the checkout page
4. WHEN an Admin uploads a new logo image, THE System SHALL replace the previous logo file and display the updated logo on buyer-facing pages

### Requirement 14: Security - CSRF Protection

**User Story:** As a system operator, I want all state-changing forms protected against CSRF attacks, so that malicious sites cannot perform actions on behalf of authenticated users.

#### Acceptance Criteria

1. THE System SHALL generate a cryptographically random CSRF_Token of at least 32 bytes per session and embed it as a hidden field in all state-changing forms (admin login, product create/edit/delete, category create/edit/delete, order status update, shipping area create/edit/delete, banner create/edit/delete, store settings update, and buyer checkout)
2. WHEN a form is submitted, THE System SHALL validate the submitted CSRF_Token matches the session-stored token before processing any state-changing operation
3. IF the submitted CSRF_Token is missing, empty, or does not match the session-stored token, THEN THE System SHALL reject the request without processing the state change, redirect the user to the referring form page, and display the error message "Permintaan tidak valid, silakan coba lagi"
4. IF the session has expired when a form is submitted, THEN THE System SHALL reject the request without processing the state change and redirect the user to the login page

### Requirement 15: Security - File Upload Validation

**User Story:** As a system operator, I want file uploads validated securely, so that malicious files cannot be uploaded to the server.

#### Acceptance Criteria

1. WHEN a file is uploaded, THE System SHALL validate the MIME type using finfo (not just the file extension) against allowed types: image/jpeg, image/png, image/webp
2. WHEN a file is uploaded, THE System SHALL validate the file extension is one of: jpg, jpeg, png, webp
3. WHEN a file is uploaded, THE System SHALL reject any file exceeding 2MB in size
4. WHEN a file is uploaded, THE System SHALL scan file contents and reject any file containing PHP opening tags (<?php or <?=)
5. WHEN a file passes all validations, THE System SHALL generate a unique filename using uniqid and timestamp, then move it to the target directory
6. IF any file upload validation fails, THEN THE System SHALL not save the file, return an error indication to the calling function, and preserve the form state for re-submission

### Requirement 16: Security - Input Validation and Output Sanitization

**User Story:** As a system operator, I want all user inputs validated and outputs sanitized, so that the application is protected against injection attacks.

#### Acceptance Criteria

1. THE System SHALL use PDO prepared statements with parameterized values for all database queries including SELECT, INSERT, UPDATE, and DELETE operations, with no raw string concatenation of user input into SQL
2. THE System SHALL escape all user-generated content displayed in HTML using htmlspecialchars with ENT_QUOTES and UTF-8 encoding before rendering it in the browser
3. THE System SHALL validate all form inputs server-side regardless of client-side validation, verifying data type, maximum length, required presence, and allowed character format
4. IF server-side validation fails for any form input, THEN THE System SHALL reject the request, preserve the user's previously entered form data, and display an error indication identifying which field failed validation
5. IF a database operation fails (connection error, query error, or constraint violation), THEN THE System SHALL log the technical details via error_log and display a generic error message to the user without exposing SQL statements, connection details, file paths, or credentials
6. THE System SHALL reject any form submission containing input values that exceed the maximum length defined for the corresponding database column

### Requirement 17: Slug Generation

**User Story:** As a system operator, I want URL-safe slugs generated from names, so that products and categories have clean, readable URLs.

#### Acceptance Criteria

1. WHEN generating a Slug from a name, THE System SHALL convert the text to lowercase
2. WHEN generating a Slug, THE System SHALL retain only lowercase letters (a-z), digits (0-9), and hyphens, replacing any other character (spaces, punctuation, symbols) with a hyphen
3. WHEN generating a Slug, THE System SHALL remove consecutive hyphens, leaving only single hyphens between words
4. WHEN generating a Slug, THE System SHALL remove leading and trailing hyphens
5. WHEN generating a Slug, THE System SHALL truncate the result to a maximum of 255 characters, trimming at the last complete word boundary and removing any trailing hyphen produced by the truncation
6. THE System SHALL ensure each product Slug is unique across all products
7. THE System SHALL ensure each category Slug is unique across all categories
8. IF a generated Slug already exists within the same entity type (product or category), THEN THE System SHALL append a numeric suffix in the format "-N" (where N starts at 2 and increments by 1) until the Slug is unique
9. IF the source name results in an empty Slug after processing (e.g., name contains only special characters), THEN THE System SHALL reject the input and return an error message indicating that a valid name is required

### Requirement 18: Mobile-First Responsive Design

**User Story:** As a Buyer arriving from WhatsApp or social media on a mobile device, I want the store to display properly on my phone, so that I can browse and purchase products comfortably.

#### Acceptance Criteria

1. THE System SHALL render all buyer-facing pages using a mobile-first responsive layout with a base design for viewports below 768px
2. THE System SHALL display the product grid in a single column on viewports below 768px, two columns on viewports between 768px and 1024px, and three or four columns on viewports above 1024px
3. WHILE the viewport width is below 768px, THE System SHALL collapse the navigation links into a hamburger menu that expands on tap
4. THE System SHALL display the cart item count as a numeric badge in the navigation header visible at all viewport sizes
5. THE System SHALL ensure no horizontal overflow on viewports as narrow as 320px

### Requirement 19: Homepage and Navigation

**User Story:** As a Buyer, I want an informative homepage with clear navigation, so that I can quickly find products or categories of interest.

#### Acceptance Criteria

1. WHEN a Buyer visits the homepage, THE System SHALL display the following sections in order: a hero section with the store headline, active banners ordered by sort_order ascending, a category navigation grid showing all active categories, featured products (is_featured=1 and is_active=1) limited to the 8 most recently created, newest products (is_active=1) limited to the 8 most recently created, and a store advantages section
2. THE System SHALL provide a sticky header navigation visible at all scroll positions containing links to: homepage, all products, categories, cart (with item count badge), and order tracking
3. WHILE the viewport width is below 768px, THE System SHALL collapse the navigation links into a hamburger menu that expands on tap
4. WHEN a Buyer clicks a category in the navigation or category grid, THE System SHALL display the category page with filtered products belonging to that category

### Requirement 20: Flash Messages and User Feedback

**User Story:** As a Buyer, I want clear feedback after performing actions, so that I know whether my actions succeeded or failed.

#### Acceptance Criteria

1. WHEN a user action completes (cart add, cart update, cart remove, checkout, login, or logout), THE System SHALL store a Flash_Message in the session with a type of "success", "warning", or "error" and a message string of no more than 255 characters describing the outcome
2. WHEN a page is rendered and a Flash_Message exists in the session, THE System SHALL display the Flash_Message and immediately remove it from the session so it appears only on a single page load
3. WHEN a Flash_Message is displayed, THE System SHALL visually distinguish it by type: "success" messages with a green indicator, "warning" messages with a yellow indicator, and "error" messages with a red indicator
4. WHEN an Admin performs a create, update, or delete action, THE System SHALL redirect to the relevant listing page with a Flash_Message of type "success" if the operation succeeded or type "error" if the operation failed
5. IF an action produces a Flash_Message while a previous Flash_Message already exists in the session, THEN THE System SHALL replace the previous message with the new Flash_Message
