# Implementation Plan: Homepage Marketplace UX

## Overview

Implement the homepage marketplace UX redesign in the existing PHP/PDO/Tailwind CDN/vanilla JavaScript stack. The plan refactors `index.php` into compact, data-backed homepage sections, preserves existing commerce behavior, and adds automated tests around the helper logic and correctness properties from the design.

## Tasks

- [ ] 1. Extract homepage data normalization helpers
  - [x] 1.1 Create PHP helper functions for homepage source data parsing
    - Add reusable functions for promo shortcut detection, promo shortcut extraction, popular search parsing, flash sale state normalization, active price selection, and promo stock percent calculation using only existing store settings and database row values.
    - Ensure helper functions create no fallback marketplace records, mock keywords, fake stock values, generated prices, or synthetic promo claims.
    - _Requirements: 1.1, 1.2, 1.3, 3.4, 3.5, 3.6, 3.7, 5.6_

  - [x] 1.2 Write unit tests for homepage helper functions
    - Test `parsePopularSearches()` with null, empty, whitespace-only, and comma-separated values while preserving trimmed token order.
    - Test promo shortcut extraction across configured and empty `promo_banner_*` settings.
    - Test `determineActivePrice()` and `calculatePromoStockPercent()` with active, inactive, invalid, overfull, zero, and negative values.
    - _Requirements: 3.4, 3.6, 3.7, 5.6_

  - [x] 1.3 Write property test for no fake marketplace data
    - **Property 1: No Fake Marketplace Data**
    - Generate varied store settings and product/category/banner collections and verify helper outputs contain only source-backed values or approved existing static trust copy.
    - **Validates: Requirements 1.1, 1.2, 1.3, 1.5, 3.5, 3.7**

  - [x] 1.4 Write property test for promo stock percent bounds
    - **Property 6: Promo Stock Percent Bounds**
    - Generate integer `promo_stock` and `promo_stock_initial` values and verify calculated percentages are integers between 0 and 100 inclusive and use only those inputs.
    - **Validates: Requirements 3.4**

  - [x] 1.5 Write property test for popular search source integrity
    - **Property 7: Popular Search Source Integrity**
    - Generate comma-separated popular search strings and verify rendered/searchable tokens are trimmed, non-empty, source-backed, and source ordered.
    - **Validates: Requirements 3.6**

- [ ] 2. Refactor homepage product card and rail rendering
  - [x] 2.1 Implement shared PHP product card renderer
    - Create a reusable homepage product card renderer that outputs sanitized product text, integer-cast identifiers, image fallback behavior, wishlist controls, and the existing add-to-cart form contract.
    - Preserve `actions/cart-add`, `actions/wishlist-toggle`, CSRF fields, quantity default `1`, existing helper usage, and existing Tailwind/Material Symbols conventions.
    - _Requirements: 1.1, 1.5, 5.1, 5.2, 5.3, 5.5, 5.6_

  - [x] 2.2 Implement shared PHP product rail renderer
    - Create a reusable rail renderer for featured and newest products that omits the rail when the backing collection is empty and renders up to the configured product limit when data exists.
    - Add lazy-loading behavior for product images after the first 4 product cards in each product listing section.
    - _Requirements: 4.4, 4.5, 4.6, 4.7, 5.4_

  - [x] 2.3 Write property test for cart contract preservation
    - **Property 5: Cart Contract Preservation**
    - Generate purchasable product rows and verify each rendered card contains the existing add-to-cart action, `post` method, non-empty `csrf_token`, integer `product_id`, and quantity `1`.
    - **Validates: Requirements 5.1**

  - [x] 2.4 Write property test for output escaping
    - **Property 8: Output Escaping**
    - Generate database and store-setting strings containing HTML-sensitive characters and verify rendered card and rail output escapes user-controlled text and attributes.
    - **Validates: Requirements 5.3**

  - [x] 2.5 Write property test for identifier normalization
    - **Property 10: Identifier Normalization**
    - Generate product and category identifiers with numeric and string-like inputs and verify rendered forms, URLs, and JavaScript calls use integer-cast values.
    - **Validates: Requirements 5.2**

- [ ] 3. Build compact hero cluster and promo shortcut rendering
  - [x] 3.1 Implement compact hero marketplace cluster in `index.php`
    - Replace the current oversized hero area with a bounded-height PHP-rendered hero cluster using active banner rows or approved existing static store introduction fallback only.
    - Use aspect-ratio wrappers, `object-contain`, responsive height caps, existing carousel controls, and no fabricated campaign banner content.
    - _Requirements: 1.1, 1.2, 2.1, 2.2, 2.5, 5.3, 5.6_

  - [x] 3.2 Implement promo shortcut grid inside the hero cluster
    - Render 2 to 3 distinct promo shortcut cards from existing `promo_banner_*` store settings beside the hero on desktop and directly below it on mobile.
    - Remove or bypass the old duplicate full-width promo row so each configured promo appears no more than once in the homepage top area.
    - _Requirements: 2.3, 2.4, 3.1, 5.3, 5.6_

  - [x] 3.3 Write property test for hero height bound
    - **Property 2: Hero Height Bound**
    - Verify rendered hero markup applies breakpoint-specific constraints that keep desktop height at or below 360px and mobile height at or below 220px while preserving the selected aspect-ratio container.
    - **Validates: Requirements 2.1, 2.2**

  - [x] 3.4 Write property test for no promo duplication
    - **Property 3: No Promo Duplication**
    - Generate configured promo banner settings and render states, then verify each configured promo shortcut appears at most once in the homepage top area.
    - **Validates: Requirements 2.3, 2.4, 3.1**

- [ ] 4. Implement compact discovery rail and flash sale shelf
  - [x] 4.1 Implement category discovery rail and popular search chips
    - Render exactly one compact horizontally scrollable discovery container with active category cards, up to 12 category rows, plus popular search chips parsed from `storeSettings['popular_searches']`.
    - Render zero category cards when no active categories exist and do not output empty-card placeholders or invented category counts.
    - _Requirements: 3.6, 3.7, 4.1, 4.2, 4.3, 5.2, 5.3_

  - [x] 4.2 Implement flash sale shelf eligibility and rendering
    - Render the flash sale shelf only when flash sale state is active, countdown remaining time is positive, and at least one real promo product row exists.
    - Use real promo product rows, real promo prices, real `promo_stock` and `promo_stock_initial` progress where valid, and omit fake sold counts or artificial urgency values.
    - _Requirements: 1.1, 1.2, 3.2, 3.3, 3.4, 3.5, 5.3_

  - [x] 4.3 Write property test for empty dynamic section omission
    - **Property 4: Empty Dynamic Section Omission**
    - Generate empty backing collections for dynamic sections and verify renderers output no product, category, banner, promo, or empty placeholder cards from those collections.
    - **Validates: Requirements 1.4, 3.3, 4.5, 4.7**

  - [x] 4.4 Write property test for flash sale shelf eligibility
    - **Property 9: Flash Sale Shelf Eligibility**
    - Generate flash sale active/inactive states, countdown values, and promo product collections and verify the shelf renders if and only if all eligibility conditions are satisfied.
    - **Validates: Requirements 3.2, 3.3**

- [ ] 5. Wire final homepage order and trust strip
  - [x] 5.1 Reorder `index.php` homepage sections into the marketplace flow
    - Wire the final order as running ticker, compact hero cluster, discovery rail, popular searches, flash sale shelf, featured products, newest products, and compact trust strip.
    - Ensure each implemented renderer is integrated into the page with no orphaned helper output or duplicate legacy homepage section markup.
    - _Requirements: 2.1, 2.3, 3.1, 4.1, 4.4, 4.6, 4.8_

  - [x] 5.2 Implement compact trust strip
    - Render exactly one compact trust strip with four visible messages: safe delivery, official warranty, competitive price, and friendly service.
    - Keep the section visually concise and avoid repeating trust claims already visible in hero or promo content.
    - _Requirements: 4.8_

  - [x] 5.3 Write homepage integration tests for populated and empty datasets
    - Render the homepage with populated real-like datasets and verify section order, card limits, cart forms, wishlist controls, carousel controls, countdown hooks, and sanitized values.
    - Render the homepage with empty banners, promo shortcuts, categories, featured products, newest products, and flash sale products to verify omission behavior and absence of mock data.
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 2.5, 3.2, 3.3, 4.2, 4.3, 4.5, 4.7, 5.1, 5.5_

  - [x] 5.4 Write responsive markup regression tests for compact layout constraints
    - Verify desktop and mobile class contracts for hero height bounds, promo shortcut placement, horizontally scrollable discovery rail, product rail density, and below-fold image lazy loading.
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 4.1, 5.4_

- [x] 6. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [x] 7. Final checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP.
- Each task references specific requirements for traceability.
- Checkpoints ensure incremental validation.
- Property tests validate universal correctness properties from the design document.
- Unit and integration tests validate concrete PHP rendering examples and edge cases.
- Implementation must not add new libraries, external APIs, migrations, mock data providers, or seed data.

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1"] },
    { "id": 1, "tasks": ["1.2", "1.3", "1.4", "1.5"] },
    { "id": 2, "tasks": ["2.1", "3.1", "4.1"] },
    { "id": 3, "tasks": ["2.2", "3.2", "4.2"] },
    { "id": 4, "tasks": ["2.3", "2.4", "2.5", "3.3", "3.4", "4.3", "4.4", "5.1"] },
    { "id": 5, "tasks": ["5.2"] },
    { "id": 6, "tasks": ["5.3", "5.4"] }
  ]
}
```
