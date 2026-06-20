# Requirements Document

## Introduction

The Homepage_Marketplace_UX feature redesigns the TC Komputer homepage into a compact, trustworthy, product-focused marketplace landing page. The feature preserves the existing PHP, PDO, Tailwind CDN, and vanilla JavaScript stack; uses existing database rows and store settings as the source of rendered marketplace content; and improves above-the-fold discovery through a compact hero cluster, promo shortcuts, category discovery, flash sale shelf, reusable product rails, and a compact trust strip.

## Glossary

- **Homepage_Marketplace_UX**: The redesigned `index.php` homepage experience for TC Komputer.
- **Homepage_Renderer**: The server-rendered PHP homepage code that queries data, builds homepage sections, and outputs HTML.
- **Dynamic_Homepage_Section**: A homepage section backed by database rows or store settings, including banners, promo shortcuts, categories, popular searches, flash sale products, featured products, and newest products.
- **Real_Source_Data**: Existing database rows, existing store settings, uploaded assets, existing placeholder image assets used only as image fallback, or approved existing static trust copy.
- **Mock_Marketplace_Data**: Fabricated products, categories, ratings, reviews, sold counts, stock percentages, testimonials, promotional claims, or default search keywords that do not originate from Real_Source_Data.
- **Hero_Cluster**: The compact top homepage area containing the banner carousel and, when configured, the promo shortcut grid.
- **Promo_Shortcut_Grid**: The compact set of 2 to 3 promotional shortcut cards rendered from `promo_banner_*` store settings.
- **Discovery_Rail**: The homepage section that renders active category links and popular search chips.
- **Flash_Sale_Shelf**: The compact promotional product shelf rendered only for an active flash sale with real promo products.
- **Product_Rail**: A reusable homepage product section for featured products or newest products.
- **Compact_Trust_Strip**: The concise homepage trust message section containing safe delivery, official warranty, competitive price, and friendly service messages.
- **Flash_Sale_State**: The active/inactive flash sale status and positive countdown remaining time derived from existing store settings.
- **Sanitized_Output**: Text, URLs, and attribute values escaped with `sanitizeOutput()` or an existing equivalent before entering HTML.

## Requirements

### Requirement 1: Preserve Real Marketplace Data Integrity

**User Story:** As a shopper, I want homepage content to reflect real TC Komputer products, categories, promotions, and store information, so that I can trust the marketplace experience.

#### Acceptance Criteria

1. THE Homepage_Renderer SHALL render product, category, banner, promo shortcut, popular search, flash sale, price, stock, and image values only from Real_Source_Data that matches current TC Komputer marketplace records available at render time.
2. THE Homepage_Renderer SHALL exclude Mock_Marketplace_Data, sample labels, sample prices, sample stock values, sample images, and placeholder marketplace records from Dynamic_Homepage_Section output.
3. IF Real_Source_Data for a Dynamic_Homepage_Section is unavailable at render time, THEN THE Homepage_Renderer SHALL render that Dynamic_Homepage_Section with zero cards sourced from the unavailable data and without substituting Mock_Marketplace_Data.
4. WHEN a Dynamic_Homepage_Section has an empty backing collection at render time, THE Homepage_Renderer SHALL render zero cards for that Dynamic_Homepage_Section and without substituting Mock_Marketplace_Data.
5. IF a product, category, or banner image value is empty, cannot be resolved to an image asset, or fails to load, THEN THE Homepage_Renderer SHALL use an existing placeholder image asset only for that image and SHALL preserve the associated Real_Source_Data text, price, stock, and category values.

### Requirement 2: Render a Compact Hero Cluster

**User Story:** As a shopper, I want the first screen to show the main promotion without taking over the page, so that I can reach categories and products faster.

#### Acceptance Criteria

1. THE Hero_Cluster SHALL render with a height no greater than 360 pixels when the viewport width is 768 pixels or greater and no greater than 220 pixels when the viewport width is less than 768 pixels.
2. WHEN uploaded banner content exceeds the applicable Hero_Cluster height bound, THE Hero_Cluster SHALL keep the rendered Hero_Cluster height within the applicable bound even if the full banner content is not visible.
3. WHERE Promo_Shortcut_Grid data exists and the viewport width is 768 pixels or greater, THE Hero_Cluster SHALL render the banner carousel beside the Promo_Shortcut_Grid within the same first-screen cluster.
4. WHERE Promo_Shortcut_Grid data exists and the viewport width is less than 768 pixels, THE Hero_Cluster SHALL render the banner carousel and SHALL render promo shortcut access immediately below the banner carousel within the same Hero_Cluster section.
5. IF no active banner data exists, THEN THE Homepage_Renderer SHALL render only existing static store introduction fallback content that is marked approved before the render request and SHALL exclude fabricated campaign banners.

### Requirement 3: Preserve Promotional Accuracy and Discovery Shortcuts

**User Story:** As a shopper, I want promo shortcuts, popular searches, and flash sale information to be accurate and easy to scan, so that I can quickly find relevant deals without misleading urgency.

#### Acceptance Criteria

1. WHEN promo banner settings are configured, THE Homepage_Renderer SHALL render each distinct Promo_Shortcut_Grid item no more than once in the homepage top area.
2. WHEN Flash_Sale_State is active, countdown remaining time is greater than 0 seconds, and at least 1 real promo product row exists, THE Homepage_Renderer SHALL render the Flash_Sale_Shelf using only real promo product rows.
3. IF Flash_Sale_State is inactive, countdown remaining time is less than or equal to 0 seconds, or 0 real promo product rows exist, THEN THE Homepage_Renderer SHALL omit the Flash_Sale_Shelf.
4. WHEN THE Homepage_Renderer renders a Flash_Sale_Shelf product with `promo_stock_initial` greater than 0, THE Homepage_Renderer SHALL calculate promo stock progress as `promo_stock` divided by `promo_stock_initial` multiplied by 100 and render a percentage between 0 and 100 inclusive.
5. IF a Flash_Sale_Shelf product has `promo_stock_initial` less than or equal to 0 or `promo_stock` is missing, THEN THE Homepage_Renderer SHALL omit promo stock progress for that product.
6. THE Homepage_Renderer SHALL exclude fake sold counts, fake urgency labels, artificial scarcity values, and generated promo stock values from the Flash_Sale_Shelf.
7. WHEN `storeSettings['popular_searches']` contains tokens, THE Discovery_Rail SHALL render popular search chips only from trimmed tokens with length greater than 0 characters while preserving source order.
8. THE Discovery_Rail SHALL render active categories from existing category rows without inventing category names or counts.

### Requirement 4: Render Dynamic Sections Only When Backed by Data

**User Story:** As a shopper, I want homepage sections to appear only when useful content exists, so that the page remains clean and product-focused.

#### Acceptance Criteria

1. THE Homepage_Renderer SHALL render exactly one horizontally scrollable Discovery_Rail container that is visually shorter in height than each Product_Rail on the same homepage.
2. WHEN one or more active category rows exist, THE Homepage_Renderer SHALL render one category card inside the Discovery_Rail for each active category row, up to a maximum of 12 category cards.
3. IF zero active category rows exist, THEN THE Homepage_Renderer SHALL render the Discovery_Rail container with zero category cards and without any empty-card placeholder.
4. WHEN one or more featured product rows exist, THE Homepage_Renderer SHALL render exactly one Product_Rail for featured products containing one product card for each featured product row, up to a maximum of 12 product cards.
5. IF zero featured product rows exist, THEN THE Homepage_Renderer SHALL omit the featured Product_Rail and any featured-product empty-state placeholder from the homepage.
6. WHEN one or more newest product rows exist, THE Homepage_Renderer SHALL render exactly one Product_Rail for newest products containing one product card for each newest product row, up to a maximum of 12 product cards.
7. IF zero newest product rows exist, THEN THE Homepage_Renderer SHALL omit the newest Product_Rail and any newest-product empty-state placeholder from the homepage.
8. THE Homepage_Renderer SHALL render exactly one Compact_Trust_Strip containing four visible messages: safe delivery, official warranty, competitive price, and friendly service.

### Requirement 5: Preserve Existing Commerce Behavior and Security

**User Story:** As a shopper, I want homepage product cards and interactions to keep working securely, so that I can add products to my cart and browse without regressions.

#### Acceptance Criteria

1. IF a homepage product card represents a purchasable product with an integer product identifier greater than 0, THEN THE Homepage_Renderer SHALL render an add-to-cart form with action `actions/cart-add`, method `post`, one non-empty `csrf_token` field, one `product_id` field equal to the product identifier, and one `quantity` field with default value `1`.
2. WHEN THE Homepage_Renderer renders product or category identifiers in forms, URLs, or JavaScript calls, THE Homepage_Renderer SHALL cast each identifier to an integer before outputting the identifier.
3. WHEN THE Homepage_Renderer renders database, store setting, or user-controlled values in HTML text content or HTML attributes, THE Homepage_Renderer SHALL apply Sanitized_Output before outputting each value.
4. WHEN THE Homepage_Renderer renders a homepage product image after the first 4 product cards in a product listing section, THE Homepage_Renderer SHALL add lazy-loading behavior to that image.
5. IF a homepage product card includes a wishlist control, THEN THE Homepage_Renderer SHALL preserve the existing wishlist toggle behavior by rendering a control that submits the product identifier and CSRF token to `actions/wishlist-toggle` without changing the add-to-cart form behavior.
6. THE Homepage_Renderer SHALL use existing Tailwind CDN classes, Material Symbols, vanilla JavaScript, PHP helpers, and database tables without adding new libraries, external APIs, migrations, mock data providers, or seed data.
