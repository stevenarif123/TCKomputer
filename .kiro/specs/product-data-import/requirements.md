# Requirements Document

## Introduction

This document defines the requirements for the Product Data Import feature of the TCKomputer admin panel. The feature enables administrators to bulk-import products from a semicolon-delimited CSV file, optionally matching product images from a server-side folder, previewing parsed data, and confirming insertion into the database. The process is split into two phases: Parse & Preview, and Confirm & Import.

## Glossary

- **Import_Page**: The admin page (`product-import.php`) that handles bulk CSV product import
- **CSV_Parser**: The component that reads and parses semicolon-delimited CSV files into associative row arrays
- **Row_Validator**: The component that validates individual parsed CSV rows and maps them to database column schema
- **Image_Matcher**: The component that resolves image filenames from CSV against files in a server directory
- **Image_Copier**: The component that copies matched images to the `uploads/products/` directory after validation
- **Folder_Browser**: The AJAX endpoint that lists server-side subdirectories for image folder selection
- **Admin**: An authenticated administrator user with access to the admin panel
- **Preview_Table**: The HTML table displayed after CSV parsing, showing validated row data and status indicators
- **Import_Session**: The `$_SESSION['import_data']` structure holding parsed data between preview and confirm phases

## Requirements

### Requirement 1: CSV File Upload and Parsing

**User Story:** As an admin, I want to upload a semicolon-delimited CSV file, so that the system can parse product data for preview.

#### Acceptance Criteria

1. WHEN an Admin uploads a CSV file and submits the preview form, THE CSV_Parser SHALL parse the file using semicolon (`;`) as the delimiter and return an array of associative rows keyed by trimmed, lowercased header names
2. WHEN the CSV file contains a UTF-8 BOM, THE CSV_Parser SHALL strip the BOM bytes before processing the header row
3. WHEN a CSV header contains trailing whitespace or spaces, THE CSV_Parser SHALL trim whitespace and replace internal spaces with underscores
4. IF the uploaded file is unreadable or contains no header row, THEN THE CSV_Parser SHALL throw a RuntimeException with a descriptive error message
5. WHEN the CSV file is parsed successfully, THE Import_Page SHALL store the parsed and validated data in the Import_Session for use during the confirm phase

### Requirement 2: Row Validation and Column Mapping

**User Story:** As an admin, I want each CSV row validated and mapped to the product database schema, so that I can review correctness before importing.

#### Acceptance Criteria

1. WHEN a CSV row has a `status` column value other than `completed`, THE Row_Validator SHALL skip that row and increment the skipped counter
2. WHEN a CSV row has `status` equal to `completed`, THE Row_Validator SHALL validate required fields: `nama` (non-empty, ≤255 chars), `kategori_id` (exists in active categories), and `harga_jual` (greater than 0)
3. WHEN a CSV row passes validation, THE Row_Validator SHALL map CSV columns to database columns according to the defined column mapping table
4. THE Row_Validator SHALL set default values for every imported product: `status='ready'`, `condition_type='new'`, `is_active=1`, `is_featured=0`, `warranty_note=''`
5. WHEN `promo_price` is set and greater than 0 for a row, THE Row_Validator SHALL derive `promo_active=1`, `promo_stock=stock`, and `promo_stock_initial=stock`
6. WHEN `promo_price` is not set or is 0, THE Row_Validator SHALL set `promo_active=0`, `promo_stock=0`, and `promo_stock_initial=0`
7. IF a row fails validation, THEN THE Row_Validator SHALL mark it as invalid and provide at least one specific error message describing the failure

### Requirement 3: Image Matching and Validation

**User Story:** As an admin, I want the system to match image filenames from the CSV against files in a server folder, so that product images are automatically associated during import.

#### Acceptance Criteria

1. WHEN an image filename is specified in the CSV `image` column, THE Image_Matcher SHALL perform case-insensitive filename matching against files in the selected image folder
2. WHEN an image filename is empty or null, THE Image_Matcher SHALL return a not-found result without raising an error
3. WHEN the `semua_gambar` column contains multiple filenames separated by commas, THE Image_Matcher SHALL match each filename individually against the image folder
4. WHEN an image file is matched and queued for import, THE Image_Copier SHALL validate that the file MIME type is one of `image/jpeg`, `image/png`, or `image/webp`
5. WHEN an image file is matched and queued for import, THE Image_Copier SHALL validate that the file size does not exceed 2 MB
6. WHEN copying an image, THE Image_Copier SHALL generate a unique filename with `img_` prefix, `uniqid`, and timestamp, and copy (not move) the source file to `uploads/products/`
7. THE Image_Copier SHALL preserve the original source image file after copying

### Requirement 4: Preview Phase

**User Story:** As an admin, I want to preview all parsed and validated product data in a table before committing, so that I can verify correctness and catch issues.

#### Acceptance Criteria

1. WHEN CSV parsing and validation are complete, THE Import_Page SHALL render a Preview_Table showing each processed row with its validation status, mapped field values, and image match status
2. WHEN displaying the preview, THE Import_Page SHALL show summary statistics: total CSV rows, rows skipped (status ≠ completed), valid rows, invalid rows, images matched, and images missing
3. THE Import_Page SHALL ensure that summary statistics are consistent: `skipped + valid + invalid` equals the total number of CSV rows processed

### Requirement 5: Confirm and Import Phase

**User Story:** As an admin, I want to confirm the import after preview, so that validated products are inserted into the database with their images.

#### Acceptance Criteria

1. WHEN an Admin confirms the import, THE Import_Page SHALL load the validated data from the Import_Session and insert each valid row into the `products` table
2. WHEN inserting a product, THE Import_Page SHALL generate a unique slug from the product name, appending a timestamp suffix if a collision is detected
3. WHEN inserting a product with matched images, THE Import_Page SHALL copy the main image and insert additional images into the `product_images` table with sequential sort order
4. THE Import_Page SHALL wrap each product insertion in its own database transaction, so that a failure in one row does not prevent other rows from importing
5. WHEN an individual row insertion fails, THE Import_Page SHALL roll back the transaction for that row, log the error, and continue processing remaining rows
6. WHEN the import is complete, THE Import_Page SHALL clear the Import_Session data and redirect the Admin to the products page with a summary flash message

### Requirement 6: Session Expiry Handling

**User Story:** As an admin, I want to be informed if my import session has expired, so that I know to re-upload the CSV file.

#### Acceptance Criteria

1. IF the Admin submits a confirm action and the Import_Session data is missing or expired, THEN THE Import_Page SHALL display an error message instructing the Admin to re-upload the CSV file

### Requirement 7: Folder Browser

**User Story:** As an admin, I want to browse server-side directories to select the image folder, so that I can easily specify where product images are stored.

#### Acceptance Criteria

1. WHEN an Admin requests a directory listing via the Folder_Browser, THE Folder_Browser SHALL return a JSON response listing subdirectories and image files at the specified path
2. THE Folder_Browser SHALL restrict browsing to a configurable base directory and reject any path containing `..` or resolving outside the allowed base
3. THE Folder_Browser SHALL validate the CSRF token sent via a request header before processing the request

### Requirement 8: Security

**User Story:** As an admin, I want all import operations protected against common web attacks, so that the system remains secure.

#### Acceptance Criteria

1. WHEN an import form is submitted (preview or confirm), THE Import_Page SHALL validate the CSRF token using the existing `validateCSRFToken()` function and reject the request if invalid
2. THE Import_Page SHALL use PDO prepared statements for all database queries to prevent SQL injection
3. THE Import_Page SHALL sanitize all output rendered in the Preview_Table using the existing `sanitizeOutput()` function to prevent XSS
4. WHEN the Admin provides an image folder path, THE Import_Page SHALL canonicalize it with `realpath()` and verify it starts with the allowed base path to prevent directory traversal

### Requirement 9: Access Control

**User Story:** As a system owner, I want only authenticated administrators to access the import page, so that unauthorized users cannot import products.

#### Acceptance Criteria

1. THE Import_Page SHALL require admin authentication using the existing `requireAdmin()` guard before processing any request

