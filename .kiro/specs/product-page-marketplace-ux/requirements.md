# Requirements Document

## Introduction

This feature improves the buyer-facing Product_Listing_Page and Product_Detail_Page with marketplace-style UX patterns while preserving the existing PHP, PDO, Tailwind CDN, vanilla JavaScript, MySQL, cart, checkout, flash sale, and wishlist behavior. The feature adds mobile-first purchase actions, social proof, trust indicators, structured product information, quick filters, compact pagination, and enhanced product cards without introducing new dependencies or database schema changes.

## Glossary

- **Product_Experience_System**: The buyer-facing product browsing and product detail experience implemented by `products.php`, `product-detail.php`, shared helpers, and related page scripts.
- **Product_Listing_Page**: The `products.php` page that displays searchable and filterable product cards.
- **Product_Detail_Page**: The `product-detail.php` page that displays one active product, images, price, description, specifications, purchase controls, and related products.
- **Product**: A row from the `products` table with fields used for display, pricing, stock, promotion, category, and specification data.
- **Purchasable_Product**: A Product whose status and stock allow add-to-cart or buy-now actions.
- **Promo_Product**: A Product whose existing promotion rules make the promo price active for display.
- **Social_Proof_Data**: Deterministic runtime values containing rating, review count, sold count, and sold display label for a Product.
- **Specification_Parser**: The parsing logic that transforms plain-text product specifications into parsed key-value rows and unparsed fallback text.
- **Pretty_Printer**: The rendering logic that formats parsed specification rows and unparsed specification text into safe Product_Detail_Page HTML.
- **Quick_Filter**: A validated Product_Listing_Page shortcut filter value from the set `ready`, `promo`, `new`, or empty string.
- **Quick_Filter_Chips**: Horizontally scrollable Product_Listing_Page controls that apply Quick_Filter values or category shortcuts through URL query parameters.
- **Smart_Pagination_Range**: A generated list of page numbers and ellipsis markers used to render compact pagination.
- **Mobile_Sticky_CTA_Bar**: A mobile-only fixed bottom action bar on the Product_Detail_Page that displays price summary and purchase actions.
- **Trust_Badge_Strip**: A prominent Product_Detail_Page badge strip communicating warranty, safe packing, consultation, and trusted shipping.
- **Quick_Benefit_Summary**: A Product_Detail_Page chip strip communicating short buyer benefits near the price area.
- **Enhanced_Product_Card**: A Product_Listing_Page product card that includes compact rating and sold-count information.
- **Existing_Cart_Form_Contract**: The existing add-to-cart POST field names and action target: `csrf_token`, `product_id`, `quantity`, and `actions/cart-add`.

## Requirements

### Requirement 1: Mobile Sticky Purchase Actions

**User Story:** As a mobile buyer, I want purchase actions to remain visible on product detail pages, so that I can add products to my cart or buy immediately without scrolling back to the purchase box.

#### Acceptance Criteria

1. WHEN the Product_Detail_Page renders a Purchasable_Product on a viewport below the `lg` breakpoint, THE Product_Experience_System SHALL display exactly one Mobile_Sticky_CTA_Bar fixed to the bottom edge of the viewport and visible without vertical scrolling.
2. WHEN the Product_Detail_Page renders on a viewport at or above the `lg` breakpoint, THE Product_Experience_System SHALL not display the Mobile_Sticky_CTA_Bar.
3. WHEN the Product_Detail_Page renders a Product with status `habis`, THE Product_Experience_System SHALL display the Mobile_Sticky_CTA_Bar without add-to-cart, buy-now, or other purchase submission buttons.
4. WHEN the buyer changes quantity in the Product_Detail_Page purchase controls to a valid quantity, THE Product_Experience_System SHALL update the Mobile_Sticky_CTA_Bar price summary to equal the active unit price multiplied by the selected quantity before the next purchase action can be submitted.
5. WHEN the buyer submits the Mobile_Sticky_CTA_Bar cart action, THE Product_Experience_System SHALL submit the Existing_Cart_Form_Contract with the currently selected quantity from the Product_Detail_Page purchase controls.
6. WHILE the Mobile_Sticky_CTA_Bar is visible, THE Product_Experience_System SHALL reserve bottom page spacing at least equal to the displayed height of the Mobile_Sticky_CTA_Bar so page content and footer content are not visually overlapped by the Mobile_Sticky_CTA_Bar.

### Requirement 2: Product Detail Social Proof

**User Story:** As a buyer, I want to see rating, review count, and sold count near the product title, so that I can judge product trustworthiness quickly.

#### Acceptance Criteria

1. WHEN the Product_Detail_Page renders an active Product with a Product ID, THE Product_Experience_System SHALL generate Social_Proof_Data containing exactly one rating, one review count, one sold count, and one sold display label from that Product ID.
2. WHEN Social_Proof_Data is generated for the same Product ID across repeated page requests, THE Product_Experience_System SHALL generate identical rating, review count, sold count, and sold display label values.
3. WHEN Social_Proof_Data is generated for any Product ID, THE Product_Experience_System SHALL generate a rating between 4.0 and 5.0 inclusive, a review count between 5 and 200 inclusive, and a sold count between 10 and 500 inclusive.
4. WHEN the Product_Detail_Page displays the product title, THE Product_Experience_System SHALL render rating stars, numeric rating, review count, and sold display directly below the product title and before the price section.
5. WHEN any Enhanced_Product_Card renders a Product with a Product ID, THE Product_Experience_System SHALL display the same Social_Proof_Data rating and sold display label generated for that Product ID on the Product_Detail_Page.
### Requirement 3: Enhanced Trust Badges

**User Story:** As a buyer, I want visible trust indicators near the price, so that I can understand warranty, packing, consultation, and shipping assurances before purchasing.

#### Acceptance Criteria

1. WHEN the Product_Detail_Page renders an active Product, THE Product_Experience_System SHALL display exactly one Trust_Badge_Strip immediately after the price section and before purchase controls, Quick_Benefit_Summary, or product description content.
2. WHEN the Trust_Badge_Strip is displayed, THE Product_Experience_System SHALL include exactly four trust badges with the visible texts `100% Ori & Garansi Resmi`, `Packing Aman`, `Bisa Konsultasi`, and `Pengiriman Terpercaya`.
3. IF the Trust_Badge_Strip and existing purchase box trust badges contain identical visible trust message text, THEN THE Product_Experience_System SHALL display that trust message in the Trust_Badge_Strip and omit the duplicate trust message from the existing purchase box trust badges.
4. IF the Trust_Badge_Strip and existing purchase box trust badges contain no identical visible trust message text, THEN THE Product_Experience_System SHALL preserve all existing purchase box trust badges unchanged.
5. WHILE the Product_Detail_Page is viewed on a viewport below the `lg` breakpoint, THE Product_Experience_System SHALL render each Trust_Badge_Strip badge as a compact pill-style element with the badge text visible on one line when the text fits within the viewport and with no purchase controls displaced outside the viewport.
### Requirement 4: Structured Specification Parsing and Rendering

**User Story:** As a buyer, I want product specifications displayed in a structured table when possible, so that I can compare product attributes more easily.

#### Acceptance Criteria

1. WHEN a Product specification contains non-empty lines using `Key: Value`, `Key - Value`, `Key = Value`, or `Key | Value` format, THE Specification_Parser SHALL parse each matching line into one key-value row.
2. WHEN a Product specification contains a non-empty line without a supported delimiter format, THE Specification_Parser SHALL preserve that line in unparsed fallback text.
3. WHEN the Specification_Parser receives null or empty specification text, THE Specification_Parser SHALL return an empty parsed row list and empty unparsed fallback text.
4. WHEN parsed specification rows exist, THE Pretty_Printer SHALL render the parsed rows in a table with keys in table header cells and values in table data cells.
5. WHEN parsed rows and unparsed fallback text both exist, THE Pretty_Printer SHALL render the parsed table and the unparsed fallback text.
6. WHEN no parsed rows exist and the Product specification is non-empty, THE Pretty_Printer SHALL render the original specification text as sanitized line-broken text.
7. WHEN no parsed rows exist and the Product specification is empty, THE Pretty_Printer SHALL render the fallback message `Tidak ada spesifikasi khusus.`
8. FOR ALL valid Product specification texts, parsing the text and pretty-printing the result SHALL preserve every non-empty input line either as a parsed key-value row or as unparsed fallback text.

### Requirement 5: Quick Benefit Summary

**User Story:** As a buyer, I want to see short benefit highlights near the product price, so that I can quickly identify stock, warranty, packing, and consultation advantages.

#### Acceptance Criteria

1. WHEN the Product_Detail_Page renders an active Product, THE Product_Experience_System SHALL display a Quick_Benefit_Summary below the price section.
2. WHEN the Product has status `ready` and stock greater than 0, THE Product_Experience_System SHALL include a `Ready Stock` benefit in the Quick_Benefit_Summary.
3. WHEN the Product does not have status `ready` or stock greater than 0, THE Product_Experience_System SHALL omit the `Ready Stock` benefit from the Quick_Benefit_Summary.
4. THE Quick_Benefit_Summary SHALL include `Garansi Resmi`, `Packing Aman`, and `Konsultasi Gratis` benefits.
5. WHILE the Product_Detail_Page is viewed on mobile, THE Product_Experience_System SHALL render the Quick_Benefit_Summary in a two-column grid layout.
6. WHILE the Product_Detail_Page is viewed on desktop, THE Product_Experience_System SHALL render the Quick_Benefit_Summary as an inline flexible row.

### Requirement 6: Savings Amount Display

**User Story:** As a buyer, I want to see the exact rupiah savings during promotions, so that I can understand the value of the discount.

#### Acceptance Criteria

1. WHEN the Product_Detail_Page renders a Promo_Product, THE Product_Experience_System SHALL calculate savings amount as `max(0, selling_price - promo_price)`.
2. WHEN the Product_Detail_Page renders a Promo_Product, THE Product_Experience_System SHALL display a `Hemat Rp X` badge next to the discount percentage badge.
3. WHEN the Product_Detail_Page renders a non-promo Product, THE Product_Experience_System SHALL omit the savings amount badge.
4. THE Product_Experience_System SHALL format the savings amount with `formatRupiah()`.

### Requirement 7: Product Listing Quantity and Query Safety

**User Story:** As a buyer, I want to browse more products per listing page, so that I can compare products with fewer page changes.

#### Acceptance Criteria

1. WHEN the Product_Listing_Page queries products, THE Product_Experience_System SHALL request 24 products per page.
2. WHEN Product_Listing_Page GET parameters are missing or invalid, THE Product_Experience_System SHALL normalize search, category, status, sort, Quick_Filter, and page values before building the product query.
3. WHEN the Product_Listing_Page applies filters, THE Product_Experience_System SHALL use validated values and prepared statement parameters for dynamic query values.
4. IF the requested Product_Listing_Page page number exceeds the available page count, THEN THE Product_Experience_System SHALL render the last available page, or page 1 when no pages are available.

### Requirement 8: Quick Filter Chips

**User Story:** As a buyer, I want one-tap product filter shortcuts, so that I can quickly view ready stock, promos, new items, and common categories.

#### Acceptance Criteria

1. WHEN the Product_Listing_Page renders product browsing controls, THE Product_Experience_System SHALL display Quick_Filter_Chips above the product grid.
2. THE Quick_Filter_Chips SHALL include `Semua`, `Ready Stock`, and `Terbaru` chips.
3. WHILE the global flash sale is active, THE Product_Experience_System SHALL include a `Promo` Quick_Filter_Chip.
4. WHILE the global flash sale is inactive, THE Product_Experience_System SHALL omit the `Promo` Quick_Filter_Chip.
5. WHEN active categories are available, THE Product_Experience_System SHALL render category shortcut chips from active categories.
6. WHEN a buyer selects a Quick_Filter_Chip, THE Product_Experience_System SHALL navigate to the Product_Listing_Page with the selected `filter` query parameter and preserved search, category, status, and sort parameters.
7. WHEN the selected Quick_Filter is active, THE Product_Experience_System SHALL render the selected chip with active styling.
8. WHILE the Product_Listing_Page is viewed on mobile, THE Product_Experience_System SHALL allow horizontal scrolling of Quick_Filter_Chips.

### Requirement 9: Quick Filter Semantics

**User Story:** As a buyer, I want quick filters to return the intended product sets, so that shortcut browsing is predictable.

#### Acceptance Criteria

1. WHEN the raw Quick_Filter query value is `ready`, `promo`, or `new`, THE Product_Experience_System SHALL accept the raw Quick_Filter value.
2. WHEN the raw Quick_Filter query value is not `ready`, `promo`, or `new`, THE Product_Experience_System SHALL replace the raw Quick_Filter value with an empty Quick_Filter value.
3. WHEN the accepted Quick_Filter is `ready`, THE Product_Experience_System SHALL filter products to status `ready` with stock greater than 0.
4. WHEN the accepted Quick_Filter is `promo`, THE Product_Experience_System SHALL filter products to active promo products with promo stock greater than 0.
5. WHEN the accepted Quick_Filter is `new`, THE Product_Experience_System SHALL order products by creation time descending.
6. THE Product_Experience_System SHALL add only hardcoded SQL fragments for Quick_Filter conditions.

### Requirement 10: Smart Pagination

**User Story:** As a buyer, I want compact pagination with ellipsis, so that large product result sets remain easy to navigate.

#### Acceptance Criteria

1. WHEN total pages equals 0, THE Product_Experience_System SHALL generate an empty Smart_Pagination_Range.
2. WHEN total pages equals 1, THE Product_Experience_System SHALL generate a Smart_Pagination_Range containing only page 1.
3. WHEN total pages is greater than 1, THE Product_Experience_System SHALL include page 1 and the last page in the Smart_Pagination_Range.
4. WHEN neighbor pages exist around the current page, THE Product_Experience_System SHALL include the configured number of neighbor pages on each side of the current page.
5. WHEN gaps exist between displayed page numbers, THE Product_Experience_System SHALL place ellipsis markers only between non-consecutive page numbers.
6. THE Product_Experience_System SHALL generate Smart_Pagination_Range values containing only integers within the valid page range and ellipsis markers.
7. WHEN rendering Smart_Pagination_Range links, THE Product_Experience_System SHALL preserve existing query parameters in pagination URLs.

### Requirement 11: Enhanced Product Cards

**User Story:** As a buyer, I want product cards to show compact social proof, so that I can compare trust signals while browsing product listings.

#### Acceptance Criteria

1. WHEN the Product_Listing_Page renders each Enhanced_Product_Card, THE Product_Experience_System SHALL display a star icon, numeric rating, and sold display below the stock information.
2. WHEN the Product_Listing_Page renders each Enhanced_Product_Card, THE Product_Experience_System SHALL generate Social_Proof_Data using the Product ID.
3. THE Enhanced_Product_Card social proof display SHALL use compact text that does not significantly increase product card height.
4. WHEN the Product_Listing_Page renders existing card actions, THE Product_Experience_System SHALL preserve existing cart and wishlist form behavior.

### Requirement 12: Mobile-First Responsiveness and Performance

**User Story:** As a buyer, I want the improved product pages to remain responsive and fast, so that browsing and purchasing works well on mobile and desktop.

#### Acceptance Criteria

1. WHILE rendering new Product_Detail_Page and Product_Listing_Page components, THE Product_Experience_System SHALL use responsive Tailwind CSS utility classes compatible with the existing Tailwind CDN setup.
2. THE Product_Experience_System SHALL implement new interactive behavior with vanilla JavaScript and no new JavaScript libraries.
3. THE Product_Experience_System SHALL avoid new database tables, database migrations, new JavaScript libraries, external API calls, build tools, and bundlers.
4. WHEN rendering Product_Listing_Page product images, THE Product_Experience_System SHALL preserve existing image rendering behavior and may add browser-native lazy loading attributes.
5. WHEN computing Social_Proof_Data, Specification_Parser output, Quick_Filter values, and Smart_Pagination_Range values, THE Product_Experience_System SHALL perform the computations in PHP without additional database queries beyond existing product, category, image, shipping, and related product queries.

### Requirement 13: Backward Compatibility and Security

**User Story:** As a store operator, I want the marketplace UX enhancements to preserve existing commerce behavior and security controls, so that current cart, checkout, wishlist, flash sale, and sanitization flows continue working.

#### Acceptance Criteria

1. WHEN rendering add-to-cart actions from the Product_Listing_Page, Product_Detail_Page purchase box, or Mobile_Sticky_CTA_Bar, THE Product_Experience_System SHALL preserve the Existing_Cart_Form_Contract.
2. WHEN rendering database or user-controlled text, THE Product_Experience_System SHALL sanitize output with the existing `sanitizeOutput()` helper or equivalent existing escaping behavior.
3. WHEN rendering product IDs and quantities in forms, THE Product_Experience_System SHALL cast product IDs and quantities to integers.
4. WHEN generating URLs from query parameters, THE Product_Experience_System SHALL build URLs with `http_build_query()` and sanitize the final URL before rendering.
5. WHEN the Product_Detail_Page renders existing image gallery, quantity selector, shipping estimator, flash sale timer, related products, and wishlist behavior, THE Product_Experience_System SHALL preserve the existing behavior.
6. WHEN the Product_Listing_Page renders existing search, category, status, sort, pagination navigation, cart action, and wishlist action behavior, THE Product_Experience_System SHALL preserve the existing behavior.
