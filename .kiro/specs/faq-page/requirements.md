# Requirements Document

## Introduction

This document specifies the requirements for the FAQ Page feature of the Steven IT Shop (TC Komputer) e-commerce platform. The feature provides a public-facing FAQ page where customers can browse categorized questions and answers with an interactive accordion UI and client-side search filtering, as well as an admin panel interface for managing FAQ entries and FAQ categories with full CRUD operations. The system integrates with the existing PHP native architecture, using PDO prepared statements, Tailwind CSS for the storefront, custom admin.css for the admin panel, and session-based CSRF protection.

## Glossary

- **FAQ_System**: The complete FAQ feature including the public FAQ page and admin management interfaces
- **Public_FAQ_Page**: The customer-facing page (`faq.php`) that displays categorized FAQ entries with accordion UI
- **Admin_FAQ_Panel**: The set of admin pages for managing FAQ entries (`admin/faqs.php`, `admin/faq-add.php`, `admin/faq-edit.php`, `admin/faq-delete.php`)
- **Admin_FAQ_Category_Panel**: The set of admin pages for managing FAQ categories (`admin/faq-categories.php`, `admin/faq-category-add.php`, `admin/faq-category-edit.php`, `admin/faq-category-delete.php`)
- **FAQ_Entry**: A database record in the `faqs` table representing a single question-answer pair
- **FAQ_Category**: A database record in the `faq_categories` table representing a grouping of related FAQ entries
- **Accordion_UI**: An expand/collapse user interface pattern where clicking a question reveals or hides its answer
- **Search_Filter**: A client-side text input that filters displayed FAQ entries by matching question or answer content
- **Sort_Order**: An integer field (0-999) that determines the display sequence of categories and FAQ entries, with lower numbers appearing first
- **Active_Status**: A boolean field (`is_active`) that controls whether a category or FAQ entry is visible on the public page
- **CSRF_Token**: A session-based Cross-Site Request Forgery protection token validated on all admin POST submissions
- **sanitizeOutput**: The existing helper function that applies `htmlspecialchars` with `ENT_QUOTES` and UTF-8 encoding to prevent XSS

## Requirements

### Requirement 1: Display Public FAQ Page

**User Story:** As a customer, I want to view frequently asked questions organized by category, so that I can find answers to common questions without contacting customer support.

#### Acceptance Criteria

1. WHEN a customer navigates to the FAQ page, THE Public_FAQ_Page SHALL fetch and display all active FAQ entries grouped under their respective active FAQ categories
2. THE Public_FAQ_Page SHALL display only FAQ entries where both the FAQ_Entry `is_active` field and the associated FAQ_Category `is_active` field are set to 1
3. THE Public_FAQ_Page SHALL order FAQ categories by `sort_order` ascending
4. THE Public_FAQ_Page SHALL order FAQ entries within each category by `sort_order` ascending
5. THE Public_FAQ_Page SHALL hide categories that contain zero active FAQ entries
6. WHEN the FAQ page is loaded, THE Public_FAQ_Page SHALL display breadcrumb navigation showing "Beranda > FAQ"
7. THE Public_FAQ_Page SHALL display each category name with its associated Material Symbol icon when an icon is configured

### Requirement 2: FAQ Accordion Interaction

**User Story:** As a customer, I want to expand and collapse FAQ answers by clicking on questions, so that I can read only the answers I am interested in.

#### Acceptance Criteria

1. WHEN a customer clicks on a FAQ question, THE Accordion_UI SHALL toggle the visibility of the corresponding answer between expanded and collapsed states
2. WHEN a FAQ answer is expanded, THE Accordion_UI SHALL rotate the chevron indicator to visually indicate the expanded state
3. WHEN the FAQ page initially loads, THE Accordion_UI SHALL display all FAQ answers in the collapsed state
4. THE Accordion_UI SHALL render FAQ answers with preserved line breaks using `nl2br` conversion

### Requirement 3: FAQ Client-Side Search and Filter

**User Story:** As a customer, I want to search through FAQ questions and answers, so that I can quickly find the specific information I need.

#### Acceptance Criteria

1. THE Public_FAQ_Page SHALL display a search input field with a search icon and placeholder text "Cari pertanyaan..."
2. WHEN a customer types in the search input field, THE Search_Filter SHALL filter the displayed FAQ entries to show only those whose question or answer text contains the search term (case-insensitive)
3. WHEN the search term matches no FAQ entries, THE Search_Filter SHALL hide all FAQ entries and their category sections
4. WHEN the search input field is cleared, THE Search_Filter SHALL restore all FAQ entries and category sections to their original visible state

### Requirement 4: Admin FAQ Entry Management — List View

**User Story:** As an administrator, I want to view all FAQ entries in a table, so that I can review and manage the FAQ content.

#### Acceptance Criteria

1. WHEN an authenticated admin navigates to the FAQ list page, THE Admin_FAQ_Panel SHALL display all FAQ entries in a table with columns: #, Pertanyaan, Kategori, Urutan, Aktif, Aksi
2. THE Admin_FAQ_Panel SHALL display FAQ entries ordered by category sort_order ascending, then by FAQ sort_order ascending
3. THE Admin_FAQ_Panel SHALL provide a "Tambah FAQ" button linking to the FAQ add form
4. THE Admin_FAQ_Panel SHALL provide a "Kelola Kategori FAQ" button linking to the FAQ categories list page
5. THE Admin_FAQ_Panel SHALL provide Edit and Hapus action buttons for each FAQ entry row

### Requirement 5: Admin FAQ Entry Management — Create

**User Story:** As an administrator, I want to add new FAQ entries with a question, answer, category, sort order, and active status, so that I can expand the FAQ content available to customers.

#### Acceptance Criteria

1. WHEN an admin submits the FAQ add form with valid data, THE Admin_FAQ_Panel SHALL insert a new FAQ_Entry into the database and redirect to the FAQ list page with a success flash message "FAQ berhasil ditambahkan"
2. THE Admin_FAQ_Panel SHALL validate that the question field is non-empty and does not exceed 500 characters
3. THE Admin_FAQ_Panel SHALL validate that the answer field is non-empty and does not exceed 5000 characters
4. THE Admin_FAQ_Panel SHALL validate that the selected FAQ category references an existing active FAQ_Category
5. THE Admin_FAQ_Panel SHALL validate that the sort_order field is an integer between 0 and 999
6. IF the CSRF token is missing or invalid, THEN THE Admin_FAQ_Panel SHALL reject the submission and redirect with an error message
7. IF validation fails, THEN THE Admin_FAQ_Panel SHALL re-display the form with all error messages and the previously entered data preserved
8. THE Admin_FAQ_Panel SHALL display a category dropdown populated with active FAQ categories

### Requirement 6: Admin FAQ Entry Management — Update

**User Story:** As an administrator, I want to edit existing FAQ entries, so that I can keep the FAQ content accurate and up to date.

#### Acceptance Criteria

1. WHEN an admin navigates to the FAQ edit page with a valid FAQ ID, THE Admin_FAQ_Panel SHALL pre-populate the form with the current FAQ_Entry values
2. IF the FAQ ID does not correspond to an existing FAQ_Entry, THEN THE Admin_FAQ_Panel SHALL redirect to the FAQ list page with an error message
3. WHEN an admin submits the FAQ edit form with valid data, THE Admin_FAQ_Panel SHALL update the FAQ_Entry in the database and redirect to the FAQ list page with a success flash message
4. THE Admin_FAQ_Panel SHALL apply the same validation rules for question, answer, category, and sort_order as specified in Requirement 5

### Requirement 7: Admin FAQ Entry Management — Delete

**User Story:** As an administrator, I want to delete FAQ entries that are no longer relevant, so that the FAQ page stays current and useful.

#### Acceptance Criteria

1. WHEN an admin submits a delete request for a FAQ_Entry with a valid CSRF token, THE Admin_FAQ_Panel SHALL delete the FAQ_Entry from the database and redirect to the FAQ list page with a success flash message "FAQ berhasil dihapus"
2. IF the CSRF token is missing or invalid, THEN THE Admin_FAQ_Panel SHALL reject the deletion and redirect with an error message
3. THE Admin_FAQ_Panel SHALL only accept delete requests via POST method
4. THE Admin_FAQ_Panel SHALL display a browser confirmation dialog before submitting the delete request

### Requirement 8: Admin FAQ Category Management — List View

**User Story:** As an administrator, I want to view all FAQ categories, so that I can manage how FAQ entries are organized.

#### Acceptance Criteria

1. WHEN an authenticated admin navigates to the FAQ categories page, THE Admin_FAQ_Category_Panel SHALL display all FAQ categories in a table with management actions
2. THE Admin_FAQ_Category_Panel SHALL provide a "Tambah Kategori" button linking to the category add form
3. THE Admin_FAQ_Category_Panel SHALL provide Edit and Hapus action buttons for each category row

### Requirement 9: Admin FAQ Category Management — Create

**User Story:** As an administrator, I want to create new FAQ categories with a name, description, icon, sort order, and active status, so that I can organize FAQ entries into meaningful groups.

#### Acceptance Criteria

1. WHEN an admin submits the FAQ category add form with valid data, THE Admin_FAQ_Category_Panel SHALL insert a new FAQ_Category into the database and redirect to the categories list with a success flash message
2. THE Admin_FAQ_Category_Panel SHALL validate that the category name is non-empty, does not exceed 100 characters, and is unique
3. THE Admin_FAQ_Category_Panel SHALL validate that the description does not exceed 500 characters (optional field)
4. THE Admin_FAQ_Category_Panel SHALL validate that the icon field contains a valid Material Symbol name (optional field)
5. THE Admin_FAQ_Category_Panel SHALL validate that the sort_order is an integer between 0 and 999
6. IF the CSRF token is missing or invalid, THEN THE Admin_FAQ_Category_Panel SHALL reject the submission and redirect with an error message

### Requirement 10: Admin FAQ Category Management — Update

**User Story:** As an administrator, I want to edit existing FAQ categories, so that I can rename, reorder, or reconfigure category properties.

#### Acceptance Criteria

1. WHEN an admin navigates to the FAQ category edit page with a valid category ID, THE Admin_FAQ_Category_Panel SHALL pre-populate the form with the current FAQ_Category values
2. IF the category ID does not correspond to an existing FAQ_Category, THEN THE Admin_FAQ_Category_Panel SHALL redirect to the categories list with an error message "Kategori FAQ tidak ditemukan"
3. WHEN an admin submits the FAQ category edit form with valid data, THE Admin_FAQ_Category_Panel SHALL update the FAQ_Category in the database and redirect to the categories list with a success flash message

### Requirement 11: Admin FAQ Category Management — Delete

**User Story:** As an administrator, I want to delete FAQ categories that are no longer needed, so that the FAQ organization stays clean and relevant.

#### Acceptance Criteria

1. WHEN an admin submits a delete request for a FAQ_Category that has zero associated FAQ entries, THE Admin_FAQ_Category_Panel SHALL delete the FAQ_Category and redirect with a success flash message "Kategori FAQ berhasil dihapus"
2. IF a FAQ_Category has one or more associated FAQ entries, THEN THE Admin_FAQ_Category_Panel SHALL reject the deletion and redirect with an error message indicating the number of associated FAQs
3. IF the category ID does not correspond to an existing FAQ_Category, THEN THE Admin_FAQ_Category_Panel SHALL redirect with an error message "Kategori FAQ tidak ditemukan"
4. IF the CSRF token is missing or invalid, THEN THE Admin_FAQ_Category_Panel SHALL reject the deletion and redirect with an error message
5. THE Admin_FAQ_Category_Panel SHALL only accept delete requests via POST method

### Requirement 12: Database Schema and Seed Data

**User Story:** As a developer, I want the FAQ database tables and seed data to be properly defined, so that the FAQ system has a reliable data foundation.

#### Acceptance Criteria

1. THE FAQ_System SHALL create a `faq_categories` table with columns: id (INT UNSIGNED AUTO_INCREMENT), name (VARCHAR 100 NOT NULL), description (TEXT NULL), icon (VARCHAR 100 NULL), sort_order (INT NOT NULL DEFAULT 0), is_active (TINYINT(1) NOT NULL DEFAULT 1), created_at (TIMESTAMP), updated_at (TIMESTAMP)
2. THE FAQ_System SHALL create a `faqs` table with columns: id (INT UNSIGNED AUTO_INCREMENT), faq_category_id (INT UNSIGNED NOT NULL), question (VARCHAR 500 NOT NULL), answer (TEXT NOT NULL), sort_order (INT NOT NULL DEFAULT 0), is_active (TINYINT(1) NOT NULL DEFAULT 1), created_at (TIMESTAMP), updated_at (TIMESTAMP)
3. THE FAQ_System SHALL define a foreign key constraint on `faqs.faq_category_id` referencing `faq_categories.id` with ON DELETE RESTRICT and ON UPDATE CASCADE
4. THE FAQ_System SHALL create indexes on `faqs.faq_category_id` and `faqs.sort_order` for efficient querying
5. THE FAQ_System SHALL include seed data with 5 FAQ categories (Pemesanan, Pengiriman, Pembayaran, Produk & Garansi, Akun & Keamanan) and 13 FAQ entries distributed across those categories

### Requirement 13: Security and Authentication

**User Story:** As a system administrator, I want all admin FAQ operations to be protected by authentication and security measures, so that unauthorized users cannot modify FAQ content.

#### Acceptance Criteria

1. THE Admin_FAQ_Panel SHALL require admin authentication via `requireAdmin()` on all admin FAQ pages
2. THE Admin_FAQ_Panel SHALL validate a CSRF token on every POST form submission (add, edit, delete operations)
3. THE FAQ_System SHALL use PDO prepared statements for all database queries to prevent SQL injection
4. THE FAQ_System SHALL pass all dynamic output values through the sanitizeOutput function before rendering in HTML to prevent XSS attacks
5. THE Public_FAQ_Page SHALL render FAQ answers using `nl2br(sanitizeOutput())` to preserve line breaks without allowing raw HTML

### Requirement 14: Navigation Integration

**User Story:** As a customer, I want to find the FAQ page through the site navigation, so that I can easily access help information from any page.

#### Acceptance Criteria

1. THE FAQ_System SHALL add a FAQ navigation link in the storefront header navigation
2. THE FAQ_System SHALL add a FAQ navigation link in the storefront footer navigation
3. THE FAQ_System SHALL add a FAQ Categories and FAQ management link in the admin sidebar navigation
4. THE FAQ_System SHALL highlight the active FAQ navigation link when the customer is on the FAQ page

### Requirement 15: Responsive Design and Styling

**User Story:** As a customer, I want the FAQ page to be visually consistent with the rest of the storefront and work well on mobile devices, so that I have a seamless browsing experience.

#### Acceptance Criteria

1. THE Public_FAQ_Page SHALL use Tailwind CSS classes consistent with the existing storefront design system
2. THE Public_FAQ_Page SHALL render correctly on mobile devices with a responsive layout
3. THE Admin_FAQ_Panel SHALL use the existing admin.css styling consistent with other admin pages (banners, categories, shipping areas)
4. THE Public_FAQ_Page SHALL include the standard storefront header and footer via `includes/header.php` and `includes/footer.php`
