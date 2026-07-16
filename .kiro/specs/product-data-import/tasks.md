# Implementation Plan: Product Data Import

## Overview

Implement a PHP admin-only CSV product import flow using native PHP parsing, existing auth/CSRF/helper/database patterns, per-row validation, image matching/copying, preview session storage, and confirm-time database insertion.

## Tasks

- [x] 1. Add the admin import page shell and request routing
  - [x] 1.1 Create `admin/product-import.php` with admin guard, shared includes, upload form, CSRF validation, and preview/confirm action branching
    - Use existing `requireAdmin()`, `validateCSRFToken()`, `sanitizeOutput()`, admin header/footer, and PDO setup patterns
    - Do not insert database rows during preview
    - _Requirements: 1.5, 4.1, 5.1, 6.1, 8.1, 8.3, 9.1_

  - [x] 1.2 Add navigation entry or admin link to reach the import page
    - Wire the new page into the existing admin product management flow without changing unrelated pages
    - _Requirements: 9.1_

- [x] 2. Implement CSV parsing and row validation helpers
  - [x] 2.1 Implement `parseImportCSV()` in the import page or a minimal included helper file
    - Parse semicolon-delimited CSV line-by-line with native PHP
    - Strip UTF-8 BOM, normalize headers to trimmed lowercase names with spaces replaced by underscores, and throw `RuntimeException` for unreadable/headerless files
    - _Requirements: 1.1, 1.2, 1.3, 1.4_

  - [x] 2.2 Write assertion tests for CSV parsing
    - Cover semicolon parsing, BOM stripping, header normalization, empty header failure, and unreadable file failure
    - _Requirements: 1.1, 1.2, 1.3, 1.4_

  - [x] 2.3 Implement `validateAndMapRow()` with category lookup and product column mapping
    - Skip rows whose CSV `status` is not `completed`
    - Validate `nama`, `kategori_id`, and `harga_jual`; map fields; set product defaults; derive promo fields
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 2.7_

  - [x] 2.4 Write property test for completed-row filtering
    - **Property 1: Only Completed Rows Imported**
    - Generate or loop through sample statuses and assert only `completed` rows can become valid import candidates
    - **Validates: Requirements 2.1, 5.1**

  - [x] 2.5 Write property test for product status defaults
    - **Property 2: Product Status Always Ready**
    - Assert every valid mapped row has product `status='ready'` regardless of CSV status input once accepted
    - **Validates: Requirements 2.4**

  - [x] 2.6 Write property test for promo derivation
    - **Property 3: Promo Fields Correctly Derived**
    - Assert `promo_price > 0` sets active promo fields from stock, and missing/zero promo clears them
    - **Validates: Requirements 2.5, 2.6**

  - [x] 2.7 Write property test for invalid-row errors
    - **Property 6: Invalid Rows Have Errors**
    - Assert every validation failure returns at least one specific error message
    - **Validates: Requirements 2.7**

- [x] 3. Implement image folder validation, matching, and copying
  - [x] 3.1 Implement safe image folder canonicalization for import requests
    - Use `realpath()` and verify the selected folder stays under the allowed base directory
    - Allow preview to continue with image warnings when the folder is not accessible
    - _Requirements: 3.1, 3.2, 8.4_

  - [x] 3.2 Implement `matchImageFile()` for main and additional image names
    - Perform case-insensitive matching in the selected folder
    - Return not-found for empty filenames without raising errors
    - Split `semua_gambar` by comma and match each filename individually
    - _Requirements: 3.1, 3.2, 3.3_

  - [x] 3.3 Implement `copyImportImage()` using existing image validation rules
    - Validate MIME type as jpeg/png/webp, enforce 2 MB limit, generate unique `img_` filename, copy to `uploads/products/`, and preserve the source file
    - Clean up partial target files on failure
    - _Requirements: 3.4, 3.5, 3.6, 3.7_

  - [x] 3.4 Write property test for image validation
    - **Property 5: Image Validation**
    - Assert only allowed MIME types and files ≤2 MB can be copied
    - **Validates: Requirements 3.4, 3.5**

  - [x] 3.5 Write property test for source image preservation
    - **Property 8: Source Images Preserved**
    - Assert successful image import copies the file and leaves the original source path intact
    - **Validates: Requirements 3.7**

- [x] 4. Build preview processing and rendering
  - [x] 4.1 Implement preview processing that parses CSV, loads active categories, validates rows, matches images, computes stats, and stores import data in `$_SESSION['import_data']`
    - Track total rows, skipped rows, valid rows, invalid rows, images matched, and images missing
    - _Requirements: 1.5, 2.1, 3.1, 3.3, 4.2, 4.3_

  - [x] 4.2 Render the preview table and summary on `admin/product-import.php`
    - Show validation status, mapped field values, row errors, main image status, additional image status, and confirm form only when import data exists
    - Escape all dynamic output with `sanitizeOutput()`
    - _Requirements: 4.1, 4.2, 8.3_

  - [x] 4.3 Write property test for preview stats consistency
    - **Property 7: Stats Consistency**
    - Assert `skipped_not_completed + valid + invalid === total_csv_rows` for processed CSV previews
    - **Validates: Requirements 4.3**

  - [x] 4.4 Write integration-style assertion test for preview processing
    - Use a temporary CSV and fixture category map to verify valid, skipped, invalid, matched-image, and missing-image rows appear in session-shaped output
    - _Requirements: 1.5, 4.1, 4.2, 4.3_

- [x] 5. Implement confirm import and database writes
  - [x] 5.1 Implement confirm handling that loads session data, rejects missing sessions, imports only valid rows, clears session, and redirects with a summary flash message
    - Display the re-upload error when session data is missing or expired
    - _Requirements: 5.1, 5.6, 6.1, 8.1_

  - [x] 5.2 Implement per-row product insertion with unique slug generation and prepared statements
    - Insert mapped product fields into `products`
    - Append a timestamp/count suffix when a generated slug collides
    - Use PDO prepared statements only
    - _Requirements: 5.1, 5.2, 8.2_

  - [x] 5.3 Implement per-product transactions, row failure handling, and image database wiring
    - Wrap each valid row in its own transaction, roll back/log failed rows, continue remaining rows, copy main images, and insert `product_images` records with sequential sort order
    - _Requirements: 5.3, 5.4, 5.5_

  - [x] 5.4 Write property test for slug uniqueness
    - **Property 4: Slug Uniqueness**
    - Assert generated/imported slugs remain unique when product names collide with existing or same-batch names
    - **Validates: Requirements 5.2**

  - [x] 5.5 Write integration-style assertion test for confirm import
    - Use a test database or transaction-wrapped fixture to verify valid rows insert, invalid rows are ignored, row failures do not stop later rows, image records are created, and session is cleared
    - _Requirements: 5.1, 5.3, 5.4, 5.5, 5.6, 6.1_

- [x] 6. Add the folder browser endpoint
  - [x] 6.1 Create `admin/browse-folders.php` returning JSON for subdirectories and image files
    - Validate CSRF token from a request header, reject `..`, restrict resolved paths to the allowed base directory, and mark image files
    - _Requirements: 7.1, 7.2, 7.3_

  - [x] 6.2 Add minimal browser UI wiring on the import page
    - Let admins request folder listings and select a folder path for the preview form
    - Keep the selected path constrained by the endpoint response
    - _Requirements: 7.1, 7.2, 7.3, 8.4_

  - [x] 6.3 Write assertion tests for folder browser path security
    - Cover valid base paths, `..` rejection, outside-base rejection, CSRF rejection, and image-file filtering
    - _Requirements: 7.1, 7.2, 7.3_

- [x] 7. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Use PHP native functions and existing project helpers; no new dependencies
- Keep helper functions in the fewest files practical, preferably alongside the import page unless reuse is needed
- Property tests are vanilla PHP assertion-based checks, matching the design's testing strategy

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1"] },
    { "id": 1, "tasks": ["1.2", "2.1", "3.1"] },
    { "id": 2, "tasks": ["2.2", "2.3", "3.2"] },
    { "id": 3, "tasks": ["2.4", "2.5", "2.6", "2.7", "3.3", "4.1", "6.1"] },
    { "id": 4, "tasks": ["3.4", "3.5", "4.2", "5.1", "6.2"] },
    { "id": 5, "tasks": ["4.3", "4.4", "5.2", "6.3"] },
    { "id": 6, "tasks": ["5.3"] },
    { "id": 7, "tasks": ["5.4", "5.5"] }
  ]
}
```
