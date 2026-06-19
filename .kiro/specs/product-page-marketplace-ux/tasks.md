# Implementation Plan: Product Page Marketplace UX

## Overview

This plan implements marketplace-style UX enhancements across the Product Listing Page (`products.php`) and Product Detail Page (`product-detail.php`). The approach follows a bottom-up order: first add pure helper functions to `config/helpers.php`, then update the Product Listing Page query logic and UI, then update the Product Detail Page computed state and UI, and finally add the Mobile Sticky CTA bar with JS integration. All changes use existing PHP, Tailwind CSS CDN, vanilla JavaScript, and MySQL — no new dependencies are introduced.

## Tasks

- [ ] 1. Add reusable pure helper functions to `config/helpers.php`
  - [x] 1.1 Implement `generateSocialProof()` and `formatSoldCount()` functions
    - Add `generateSocialProof(array $product): array` that uses deterministic seeding from product ID to generate rating (4.0–5.0), review_count (5–200), sold_count (10–500), and sold_display string
    - Add `formatSoldCount(int $count): string` that returns marketplace-style bucket labels (e.g., "50+", "100+", "1rb+")
    - Both functions must be pure with no database side effects
    - _Requirements: 2.1, 2.2, 2.3, 2.5, 11.2, 12.5_

  - [x] 1.2 Write property tests for Social Proof Determinism and Bounds
    - **Property 2: Social Proof Determinism** — For any Product ID, repeated calls to `generateSocialProof()` with that Product ID should return identical rating, review count, sold count, and sold display values
    - **Validates: Requirements 2.2, 2.5**
    - **Property 3: Social Proof Bounds** — For any Product ID, `generateSocialProof()` should return a rating between 4.0 and 5.0 inclusive, a review count between 5 and 200 inclusive, and a sold count between 10 and 500 inclusive
    - **Validates: Requirements 2.3**

  - [x] 1.3 Implement `parseSpecification()` function
    - Add `parseSpecification(?string $specText): array` that parses specification text into `{parsed: [{key, value}], unparsed: string}`
    - Support delimiter formats: `Key: Value`, `Key - Value`, `Key = Value`, `Key | Value`
    - Handle null, empty, and mixed-format input gracefully
    - Lines without supported delimiters go to unparsed fallback text
    - _Requirements: 4.1, 4.2, 4.3, 4.8_

  - [x] 1.4 Write property tests for Specification Parser
    - **Property 4: Specification Supported Delimiter Parsing** — For any non-empty specification line that contains exactly one supported delimiter between a non-empty key and non-empty value, `parseSpecification()` should represent that line as exactly one parsed key-value row
    - **Validates: Requirements 4.1**
    - **Property 5: Specification Unparsed Line Preservation** — For any non-empty specification line without a supported delimiter format, `parseSpecification()` should preserve that line in the unparsed fallback text
    - **Validates: Requirements 4.2**
    - **Property 6: Specification Parse-Print Preservation** — For any valid Product specification text, parsing the text should preserve every non-empty input line either as a parsed key-value row or as unparsed fallback text
    - **Validates: Requirements 4.4, 4.8**

  - [x] 1.5 Implement `generatePaginationRange()` function
    - Add `generatePaginationRange(int $currentPage, int $totalPages, int $neighbors = 1): array` that returns array of page numbers and `'...'` ellipsis markers
    - Always include page 1 and last page when totalPages > 1
    - Show configured neighbors around current page
    - Handle edge cases: totalPages=0 returns empty, totalPages=1 returns [1]
    - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5, 10.6_

  - [x] 1.6 Write property tests for Pagination Range
    - **Property 12: Pagination First And Last Inclusion** — For any total page count greater than 1 and any current page, `generatePaginationRange()` should include page 1 and the final page
    - **Validates: Requirements 10.3**
    - **Property 13: Pagination Neighbor Inclusion** — For any current page, total page count, and non-negative neighbor count, `generatePaginationRange()` should include every configured neighbor page that exists within the valid page range
    - **Validates: Requirements 10.4**
    - **Property 14: Pagination Ellipsis Correctness** — Ellipsis markers should appear only between non-consecutive page numbers and should not appear at the beginning or end of the range
    - **Validates: Requirements 10.5**
    - **Property 15: Pagination Element Bounds** — Every element returned should be either an integer in the valid page range or the ellipsis marker string
    - **Validates: Requirements 10.6**

  - [x] 1.7 Implement `validateQuickFilter()` and `applyQuickFilterToWhereClause()` functions
    - Add `validateQuickFilter(string $filter): string` that returns only `''`, `'ready'`, `'promo'`, or `'new'`
    - Add `applyQuickFilterToWhereClause(string $quickFilter, array $where, array $params): array` that appends hardcoded SQL fragments for validated filter values
    - Never concatenate raw user input into SQL
    - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5, 9.6_

  - [x] 1.8 Write property test for Quick Filter Validation Safety
    - **Property 11: Quick Filter Validation Safety** — For any raw filter query value, `validateQuickFilter()` should return only `''`, `ready`, `promo`, or `new`; values outside the allowed set should return `''`
    - **Validates: Requirements 9.1, 9.2**

- [x] 2. Checkpoint - Ensure all helper functions are correct
  - Ensure all tests pass, ask the user if questions arise.

- [x] 3. Update Product Listing Page query logic and UI (`products.php`)
  - [x] 3.1 Update per-page count, quick filter parsing, and query building
    - Change `$perPage = 12` to `$perPage = 24`
    - Add `$quickFilter = validateQuickFilter($_GET['filter'] ?? '')` near existing query parameter parsing
    - Call `applyQuickFilterToWhereClause()` to add filter conditions to `$where` and `$params`
    - Override `$orderBy` to `'p.created_at DESC'` when `$quickFilter === 'new'`
    - Add `$quickFilter` to preserved `$queryParams` for pagination URL generation
    - Clamp `$page` when it exceeds `$totalPages`
    - _Requirements: 7.1, 7.2, 7.3, 7.4, 9.3, 9.4, 9.5, 9.6_

  - [x] 3.2 Write property test for Product Listing Page Clamp
    - **Property 9: Product Listing Page Clamp** — For any requested page number and any total page count, the normalized page should be within the available page range when pages exist, and should be page 1 when no pages are available
    - **Validates: Requirements 7.4**

  - [x] 3.3 Add Quick Filter Chips UI to Product Listing Page
    - Add horizontally scrollable chip strip above the product grid (after filter form, before empty state/grid)
    - Include `Semua`, `Ready Stock`, and `Terbaru` chips always
    - Include `Promo` chip only when `$isGlobalFlashSaleActive` is true
    - Render category shortcut chips from fetched `$categories` (limit to first 6)
    - Highlight active chip with `bg-secondary text-white border-secondary`
    - Generate chip URLs using `http_build_query()` preserving existing search, category, status, sort params
    - Use `overflow-x-auto` with `hide-scrollbar` for mobile horizontal scrolling
    - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5, 8.6, 8.7, 8.8, 13.4_

  - [x] 3.4 Write property test for Quick Filter URL Preservation
    - **Property 10: Quick Filter URL Preservation** — For any valid combination of search, category, status, sort, and selected Quick_Filter values, the generated Quick_Filter_Chip URL should preserve existing query parameters while applying the selected filter parameter
    - **Validates: Requirements 8.6**

  - [x] 3.5 Add Enhanced Product Card social proof and Smart Pagination
    - Inside the product card loop, call `generateSocialProof($product)` for each product
    - Add compact social proof display (`★ 4.8 | Terjual 50+`) in `text-[10px]` after stock/status row, using sanitized output
    - Replace existing `for ($i = 1; $i <= $totalPages; $i++)` pagination loop with `generatePaginationRange()` rendering
    - Render ellipsis markers as non-clickable spans, current page as highlighted span, other pages as anchor links
    - Preserve all existing query parameters (including `filter`) in pagination URLs using `http_build_query()`
    - Optionally add `loading="lazy"` to product card images
    - _Requirements: 10.7, 11.1, 11.2, 11.3, 11.4, 12.4, 13.6_

- [x] 4. Checkpoint - Verify Product Listing Page changes
  - Ensure all tests pass, ask the user if questions arise.

- [x] 5. Update Product Detail Page computed state and UI (`product-detail.php`)
  - [x] 5.1 Add computed state for social proof, parsed specs, and savings amount
    - Call `generateSocialProof($product)` after product fetch to get `$socialProof`
    - Call `parseSpecification($product['specification'] ?? '')` to get `$parsedSpec`
    - Calculate `$savingsAmount = $isPromo ? max(0, (int)$product['selling_price'] - (int)$product['promo_price']) : 0`
    - Calculate `$discountPercentage` for promo products (move existing inline calculation to a variable)
    - No additional database queries beyond existing product, category, image, shipping, and related queries
    - _Requirements: 2.1, 4.1, 6.1, 12.5_

  - [x] 5.2 Add Social Proof section below product title
    - Render star icons using Material Symbols `star` with `FILL` variation for filled/unfilled
    - Display numeric rating, review count link, and sold display label (`Terjual X+`)
    - Position directly after `<h1>` product name and before the price section
    - Use `sanitizeOutput()` for sold_display value
    - _Requirements: 2.4_

  - [x] 5.3 Add Savings Amount badge to promo price block
    - Add green `Hemat Rp X` badge next to existing red `-X%` discount badge
    - Display only when `$isPromo` is true
    - Format savings amount with `formatRupiah()`
    - _Requirements: 6.1, 6.2, 6.3, 6.4_

  - [x] 5.4 Write property test for Savings Amount Non-Negativity
    - **Property 8: Savings Amount Non-Negativity** — For any Promo_Product, the savings amount should equal `max(0, selling_price - promo_price)` and should never be negative
    - **Validates: Requirements 6.1**

  - [x] 5.5 Add Enhanced Trust Badge strip and Quick Benefit Summary
    - Add Trust_Badge_Strip immediately after the price section with 4 badges: `100% Ori & Garansi Resmi`, `Packing Aman`, `Bisa Konsultasi`, `Pengiriman Terpercaya`
    - Use Material Symbols icons and compact pill-style styling with responsive layout
    - Deduplicate trust messages that already appear in the existing purchase box trust badges
    - Add Quick_Benefit_Summary below the trust badges: conditional `Ready Stock` (only when status='ready' and stock > 0), plus `Garansi Resmi`, `Packing Aman`, `Konsultasi Gratis`
    - Mobile: 2-column grid (`grid-cols-2`); Desktop: inline flex (`sm:flex sm:flex-wrap`)
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 5.1, 5.2, 5.3, 5.4, 5.5, 5.6_

  - [x] 5.6 Write property test for Ready Stock Benefit State
    - **Property 7: Ready Stock Benefit State** — For any Product, the Quick_Benefit_Summary should include `Ready Stock` if and only if the Product status is `ready` and Product stock is greater than 0
    - **Validates: Requirements 5.2, 5.3**

  - [x] 5.7 Replace specification tab with structured rendering
    - Replace existing `nl2br(sanitizeOutput($product['specification']))` in `#spesifikasi-tab` with structured rendering
    - If parsed rows exist: render zebra-striped table with `<th>` keys and `<td>` values, both sanitized
    - If parsed rows and unparsed text both exist: render table followed by unparsed text
    - If no parsed rows but specification is non-empty: render original text with `nl2br(sanitizeOutput(...))`
    - If specification is empty: render fallback message `Tidak ada spesifikasi khusus.`
    - _Requirements: 4.4, 4.5, 4.6, 4.7, 13.2_

- [x] 6. Checkpoint - Verify Product Detail Page changes
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 7. Add Mobile Sticky CTA Bar and JS integration
  - [x] 7.1 Add Mobile Sticky CTA Bar HTML to Product Detail Page
    - Add fixed-bottom CTA bar with `lg:hidden` class (hidden on desktop, visible on mobile)
    - Include price summary (`Total` label and formatted price) and two action buttons (`Keranjang` and `Beli Sekarang`)
    - Show CTA bar only when product is purchasable (status is `ready` or `po`)
    - Hide purchase buttons when product status is `habis` (show price-only bar or hide entirely)
    - Use `z-40` for proper stacking context
    - Add bottom padding (`pb-24 lg:pb-lg`) to main page container to prevent footer content overlap
    - Use existing `$csrfToken`, `$product['id']`, and quantity from purchase controls
    - _Requirements: 1.1, 1.2, 1.3, 1.6, 13.1, 13.3_

  - [x] 7.2 Add Mobile CTA JavaScript for price sync and form submission
    - Add `updateMobileCtaPrice()` function that updates `#mobile-cta-price` text to `formatCurrency(currentPrice * qty)`
    - Add `submitCartFromMobile()` function that sets `#form-quantity` value to current `qty` and submits `#add-to-cart-form`
    - Update existing `incrementQty()` and `decrementQty()` to call `updateMobileCtaPrice()` after `updateTotal()`
    - Initialize mobile CTA price on page load to match current active price
    - Ensure the Mobile_Sticky_CTA_Bar submits the Existing_Cart_Form_Contract (csrf_token, product_id, quantity to actions/cart-add)
    - _Requirements: 1.4, 1.5, 13.1_

  - [x] 7.3 Write property test for Mobile CTA Price Total
    - **Property 1: Mobile CTA Price Total** — For any active unit price and any valid selected quantity, updating the quantity controls should make the Mobile_Sticky_CTA_Bar price summary equal the active unit price multiplied by the selected quantity
    - **Validates: Requirements 1.4**

  - [x] 7.4 Write property test for Existing Cart Form Contract
    - **Property 16: Existing Cart Form Contract** — For any add-to-cart action rendered from the Product_Listing_Page, Product_Detail_Page purchase box, or Mobile_Sticky_CTA_Bar, the submitted form should include `csrf_token`, `product_id`, and `quantity` fields targeting the existing cart-add endpoint
    - **Validates: Requirements 1.5, 13.1**

- [x] 8. Backward compatibility verification and final polish
  - [x] 8.1 Verify backward compatibility of all existing behaviors
    - Verify existing image gallery still switches thumbnails and opens lightbox
    - Verify existing quantity selector still updates subtotal in purchase box
    - Verify existing shipping estimator still calculates and displays shipping cost
    - Verify existing flash sale timer still runs and displays correctly
    - Verify existing wishlist toggle button on product cards still works
    - Verify existing cart-add form field names (`csrf_token`, `product_id`, `quantity`) remain unchanged on listing page, detail page purchase box, and mobile CTA
    - Verify existing search, category filter, status filter, sort dropdown, and pagination navigation all still work
    - Ensure all new components use `sanitizeOutput()` for database/user text and cast IDs/quantities to integers
    - Ensure all generated URLs use `http_build_query()` and are sanitized before rendering
    - Ensure no new database tables, migrations, JS libraries, external API calls, or build tools are introduced
    - _Requirements: 12.1, 12.2, 12.3, 13.1, 13.2, 13.3, 13.4, 13.5, 13.6_

- [x] 9. Final checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests validate universal correctness properties from the design document
- Unit tests validate specific examples and edge cases
- All helper functions are pure (no database side effects) and can be tested independently
- The design uses PHP exclusively — no language selection was needed
- All new code uses existing Tailwind CSS CDN classes and vanilla JavaScript
- No new database schema changes, JS libraries, or external dependencies are introduced

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1", "1.3", "1.5", "1.7"] },
    { "id": 1, "tasks": ["1.2", "1.4", "1.6", "1.8"] },
    { "id": 2, "tasks": ["3.1"] },
    { "id": 3, "tasks": ["3.2", "3.3"] },
    { "id": 4, "tasks": ["3.4", "3.5"] },
    { "id": 5, "tasks": ["5.1"] },
    { "id": 6, "tasks": ["5.2", "5.3", "5.5", "5.7"] },
    { "id": 7, "tasks": ["5.4", "5.6"] },
    { "id": 8, "tasks": ["7.1"] },
    { "id": 9, "tasks": ["7.2"] },
    { "id": 10, "tasks": ["7.3", "7.4"] },
    { "id": 11, "tasks": ["8.1"] }
  ]
}
```
