# Requirements Document

## Introduction

The Promotion & Discount System adds a rule-driven promotion engine to the TC Komputer (Steven IT Shop) PHP e-commerce application and repairs a flash-sale price expiry defect. The engine supports four configurable promotion types — free shipping over a subtotal threshold, category-specific discounts, buy-X-get-free-item, and regular cart discounts (percentage or fixed amount) — and evaluates them deterministically at the cart and checkout layers. All monetary values are non-negative integers in Rupiah.

The flash-sale fix unifies price resolution across every storefront surface so that a product is charged its promo price only while the global flash-sale window is open, the per-product promo is active, and promo stock remains; otherwise the regular selling price applies. The system also provides admin CRUD for promotions, persists discount outcomes on orders for auditability, and exposes pure evaluation functions suitable for property-based testing.

These requirements are derived from the approved design document at `.kiro/specs/promotion-discount-system/design.md` and preserve the existing stack conventions: PHP native, PDO with prepared statements, integer Rupiah currency, session-based cart, server-rendered pages, CSRF-protected admin forms, and output sanitization.

## Glossary

- **Price_Resolver**: The component (`getEffectiveProductPrice`, `isFlashSaleWindowActive`, `isPromoPriceActive` in `config/promotions.php`) that determines a product's effective unit price based on the flash-sale window and per-product promo state.
- **Promotion_Engine**: The pure evaluation function (`evaluatePromotions` in `config/promotions.php`) that computes discounts, free shipping, gift items, and final totals for a cart.
- **Promotion_Repository**: The data-access function (`loadActivePromotions` in `config/promotions.php`) that loads active, in-window promotions and their targets from the database.
- **Promotion_Admin**: The admin handlers (`admin/promotions.php`, `promotion-add.php`, `promotion-edit.php`, `promotion-delete.php`, `promotion-toggle.php`) that provide CRUD management of promotions.
- **Checkout_Processor**: The checkout flow (`checkout.php`, `actions/checkout-process.php`) that recomputes prices and promotions server-side and persists the order.
- **Storefront_Surface**: Any page that displays product prices: `index.php`, `products.php`, `category.php`, `product-detail.php`, `cart.php`, `actions/cart-add.php`, `actions/cart-update.php`.
- **Flash_Sale_Window**: The global flash-sale period defined by `store_settings.flash_sale_active` and `store_settings.flash_sale_end`.
- **Effective_Price**: The unit price a product is displayed and charged at, in integer Rupiah, after applying the flash-sale gating rule.
- **PromotionResult**: The structured output of the Promotion_Engine containing `subtotal`, `discount_total`, `free_shipping`, `shipping_cost`, `total`, `item_discounts`, `gift_items`, and `applied_promotions`.
- **Subtotal**: The sum of `Effective_Price × quantity` over all paid cart items, in integer Rupiah.
- **Subtotal_After_Discounts**: The subtotal reduced by `discount_total`, clamped to be non-negative.
- **Category_Discount**: A promotion of type `category_discount` that reduces the line price of items in targeted categories.
- **Cart_Discount**: A promotion of type `cart_discount` that reduces the cart subtotal (percent or fixed).
- **Free_Shipping_Promotion**: A promotion of type `free_shipping_threshold` that waives shipping cost when the subtotal meets a threshold.
- **Gift_Promotion**: A promotion of type `buy_x_get_free_item` that grants a free gift product when qualifying conditions are met.
- **Gift_Item**: A free product line added to an order with `unit_price = 0` and `is_gift = 1`.
- **Precedence**: The deterministic ordering of promotions, sorted by `priority` ascending then `id` ascending (lower value = higher precedence).
- **Stackable**: A promotion attribute; a non-stackable discount promotion excludes all other discount promotions from applying.
- **Admin**: An authenticated administrative user with a valid admin session.
- **Rupiah**: The currency unit, always represented as a non-negative integer.

## Requirements

### Requirement 1: Flash-Sale Window Determination

**User Story:** As a store operator, I want the system to recognize when the global flash-sale window is open, so that promo pricing is only honored during the active sale period.

#### Acceptance Criteria

1. WHILE `flash_sale_active` equals integer 1 AND `flash_sale_end` is a non-empty, non-whitespace-only value AND `flash_sale_end` parses to a valid timestamp AND that parsed timestamp is strictly later than the current evaluation time (the single reference timestamp captured once at the start of the determination), THE Price_Resolver SHALL report the Flash_Sale_Window as active.
2. IF `flash_sale_active` is not equal to integer 1 (including null, missing, or any other value), THEN THE Price_Resolver SHALL report the Flash_Sale_Window as inactive.
3. IF `flash_sale_end` is empty, missing, or contains only whitespace, THEN THE Price_Resolver SHALL report the Flash_Sale_Window as inactive.
4. IF `flash_sale_end` parses to a valid timestamp that is equal to or earlier than the current evaluation time, THEN THE Price_Resolver SHALL report the Flash_Sale_Window as inactive.
5. IF `flash_sale_end` cannot be parsed into a valid timestamp, THEN THE Price_Resolver SHALL report the Flash_Sale_Window as inactive.
6. WHEN determining the Flash_Sale_Window state for identical inputs and the same current evaluation time, THE Price_Resolver SHALL return the same result on every invocation.
7. WHEN determining the Flash_Sale_Window state, THE Price_Resolver SHALL perform no database access and SHALL produce no side effects.

### Requirement 2: Effective Product Price Resolution

**User Story:** As a buyer, I want each product to show and charge the correct price, so that I am never charged a promo price after the flash sale ends.

#### Acceptance Criteria

1. WHEN the Flash_Sale_Window is active AND `promo_active` equals 1 AND `promo_price` is an integer greater than 0 AND `promo_stock` is an integer greater than 0, THE Price_Resolver SHALL return the Effective_Price equal to `promo_price` in integer Rupiah.
2. IF the Flash_Sale_Window is inactive, THEN THE Price_Resolver SHALL return the Effective_Price equal to `selling_price` in integer Rupiah.
3. IF `promo_active` is not equal to 1, THEN THE Price_Resolver SHALL return the Effective_Price equal to `selling_price` in integer Rupiah.
4. IF `promo_price` is less than or equal to 0 or is not an integer, THEN THE Price_Resolver SHALL return the Effective_Price equal to `selling_price` in integer Rupiah.
5. IF `promo_stock` is less than or equal to 0 or is not an integer, THEN THE Price_Resolver SHALL return the Effective_Price equal to `selling_price` in integer Rupiah.
6. IF `promo_active`, `promo_price`, or `promo_stock` is null or missing, THEN THE Price_Resolver SHALL return the Effective_Price equal to `selling_price` in integer Rupiah.
7. THE Price_Resolver SHALL return an Effective_Price that equals either `promo_price` or `selling_price` and that is a non-negative integer in Rupiah.
8. WHEN resolving an Effective_Price for fixed inputs, THE Price_Resolver SHALL return the same result on every invocation, SHALL perform no database access, and SHALL produce no side effects.

### Requirement 3: Unified Flash-Sale Pricing Across Surfaces

**User Story:** As a buyer, I want consistent prices on every page, so that the price I see when browsing matches the price I pay at checkout.

#### Acceptance Criteria

1. WHEN a Storefront_Surface displays a product price, THE Storefront_Surface SHALL display the Effective_Price returned by the Price_Resolver for that product, gated by the current Flash_Sale_Window state, as a non-negative integer in Rupiah.
2. WHEN the Checkout_Processor calculates the price charged for a cart item, THE Checkout_Processor SHALL charge the Effective_Price returned by the Price_Resolver for that item, gated by the current Flash_Sale_Window state, as a non-negative integer in Rupiah.
3. WHILE the Flash_Sale_Window is inactive, THE Storefront_Surface SHALL display `selling_price` for every product.
4. WHILE the Flash_Sale_Window is inactive, THE Checkout_Processor SHALL charge `selling_price` for every product.
5. WHILE the Flash_Sale_Window is active AND a product's promo state is eligible (`promo_active` equals 1 AND `promo_price` is an integer greater than 0 AND `promo_stock` is an integer greater than 0), THE Storefront_Surface SHALL display `promo_price` for that product.
6. WHILE the Flash_Sale_Window is active AND a cart item's promo state is eligible (`promo_active` equals 1 AND `promo_price` is an integer greater than 0 AND `promo_stock` is an integer greater than 0), THE Checkout_Processor SHALL charge `promo_price` for that item.
7. WHEN a buyer views a product on a Storefront_Surface and then checks out that same product under an identical Flash_Sale_Window state, identical promo state inputs, and the same evaluation time, THE system SHALL ensure the Effective_Price displayed by the Storefront_Surface equals the Effective_Price charged by the Checkout_Processor.

### Requirement 4: Active Promotion Loading

**User Story:** As a store operator, I want only currently valid promotions to be applied, so that scheduled and deactivated promotions do not affect orders.

#### Acceptance Criteria

1. WHEN loading promotions, THE Promotion_Repository SHALL treat the current evaluation time as the server's current date and time captured at the moment the query is executed, and SHALL return only promotions where `is_active` equals 1 AND (`start_at` is null OR `start_at` is at or before the current evaluation time) AND (`end_at` is null OR `end_at` is at or after the current evaluation time).
2. WHEN loading a promotion, THE Promotion_Repository SHALL include that promotion's targets as a list of entries, where each entry contains a `target_type` value and a `target_id` value.
3. IF a loaded promotion has no associated targets, THEN THE Promotion_Repository SHALL set that promotion's targets to an empty list.
4. WHEN returning promotions, THE Promotion_Repository SHALL order them by `priority` ascending, and for promotions sharing the same `priority` value SHALL order them by `id` ascending.
5. THE Promotion_Repository SHALL execute all queries using PDO prepared statements.
6. IF no promotion satisfies the active and in-window conditions defined in criterion 1, THEN THE Promotion_Repository SHALL return an empty list.
7. IF a promotion query fails to execute, THEN THE Promotion_Repository SHALL raise an error indicating the load failure and SHALL NOT return a partial or empty result set as if it were a successful load.

### Requirement 5: Subtotal Computation

**User Story:** As a buyer, I want my cart subtotal computed accurately from effective prices, so that discounts and totals start from the correct base.

#### Acceptance Criteria

1. WHEN evaluating a cart, THE Promotion_Engine SHALL compute the Subtotal as the integer sum over all paid items (cart items whose `is_gift` is not equal to 1) of `Effective_Price × quantity`, where each item's `Effective_Price` is the non-negative integer Rupiah value returned by the Price_Resolver.
2. WHEN computing the Subtotal, THE Promotion_Engine SHALL treat each paid item's quantity as a positive integer of at least 1, substituting 1 for any quantity that is missing, zero, negative, or non-integer.
3. THE Promotion_Engine SHALL produce a Subtotal that is a non-negative integer in the range 0 through 999,999,999,999.
4. WHEN evaluating a cart that contains no paid items, THE Promotion_Engine SHALL set the Subtotal to 0.
5. WHEN computing the Subtotal for fixed cart inputs at a fixed evaluation time, THE Promotion_Engine SHALL return the same Subtotal on every invocation and SHALL produce no side effects.

### Requirement 6: Category-Specific Discounts

**User Story:** As a store operator, I want to offer discounts on specific product categories, so that I can promote targeted product groups.

#### Acceptance Criteria

1. WHEN a Category_Discount targets the category of a cart item, THE Promotion_Engine SHALL reduce that item's line subtotal, defined as the item's Effective_Price multiplied by its quantity (quantity treated as at least 1), by the computed Category_Discount amount.
2. THE Promotion_Engine SHALL apply at most one Category_Discount to each cart item.
3. WHEN more than one Category_Discount targets an item's category, THE Promotion_Engine SHALL select the Category_Discount with the highest Precedence (lowest `priority`, then lowest `id`).
4. IF a cart item's category is not among a Category_Discount's targets, THEN THE Promotion_Engine SHALL exclude that item from that Category_Discount.
5. THE Promotion_Engine SHALL record each applied item discount as a non-negative integer that is greater than 0 and at most the item's line subtotal, and SHALL NOT record an item discount when the computed amount is 0.
6. WHERE a Category_Discount has `discount_type` of `percent`, THE Promotion_Engine SHALL compute the item discount as the integer floor of `line subtotal × discount_value / 100`, with `discount_value` clamped to the range 0 through 100.
7. WHERE a Category_Discount has `discount_type` of `fixed`, THE Promotion_Engine SHALL compute the item discount as `discount_value`, limited to at most the item's line subtotal.
8. IF a Category_Discount has an unrecognized `discount_type`, THEN THE Promotion_Engine SHALL apply an item discount of 0.

### Requirement 7: Regular Cart Discounts

**User Story:** As a store operator, I want to apply a percentage or fixed discount to the whole cart, so that I can run store-wide promotions.

#### Acceptance Criteria

1. WHEN at least one Cart_Discount is active AND the Subtotal_After_Discounts is greater than or equal to the highest-precedence Cart_Discount's effective `min_subtotal`, THE Promotion_Engine SHALL apply that Cart_Discount to the Subtotal_After_Discounts, where the effective `min_subtotal` is the stored `min_subtotal` value, or 0 when `min_subtotal` is missing, null, or less than 0.
2. THE Promotion_Engine SHALL apply at most one Cart_Discount per order.
3. WHEN more than one Cart_Discount is active, THE Promotion_Engine SHALL select the single Cart_Discount with the highest precedence value.
4. WHERE a Cart_Discount has `discount_type` of `percent`, THE Promotion_Engine SHALL compute the discount as the integer floor of `base × discount_value / 100`, where `base` is the Subtotal_After_Discounts and `discount_value` is clamped to the range 0 through 100 inclusive.
5. WHERE a Cart_Discount has `discount_type` of `percent` AND `max_discount` is set to a value greater than or equal to 0, THE Promotion_Engine SHALL limit the computed discount to at most `max_discount`.
6. WHERE a Cart_Discount has `discount_type` of `fixed`, THE Promotion_Engine SHALL compute the discount as `discount_value` clamped to the range 0 through `base` inclusive, where `base` is the Subtotal_After_Discounts.
7. IF a Cart_Discount has an unrecognized `discount_type`, THEN THE Promotion_Engine SHALL apply a discount of 0 and SHALL leave the Subtotal_After_Discounts unchanged.
8. IF the highest-precedence active Cart_Discount's effective `min_subtotal` is greater than the Subtotal_After_Discounts, THEN THE Promotion_Engine SHALL apply a discount of 0 and SHALL leave the Subtotal_After_Discounts unchanged.
9. IF no Cart_Discount is active, THEN THE Promotion_Engine SHALL apply a discount of 0 and SHALL leave the Subtotal_After_Discounts unchanged.

### Requirement 8: Free Shipping Over Threshold

**User Story:** As a buyer, I want free shipping when my order is large enough, so that I am rewarded for larger purchases.

#### Acceptance Criteria

1. WHEN at least one active Free_Shipping_Promotion returned by the Promotion_Repository has a `min_subtotal`, treated as a non-negative integer in the range 0 through 999,999,999,999 with any missing, negative, or non-integer value substituted by 0, that is less than or equal to the Subtotal_After_Discounts, THE Promotion_Engine SHALL set `free_shipping` to boolean true.
2. IF no active Free_Shipping_Promotion exists, OR no active Free_Shipping_Promotion has a `min_subtotal` (treated as a non-negative integer with any missing, negative, or non-integer value substituted by 0) less than or equal to the Subtotal_After_Discounts, THEN THE Promotion_Engine SHALL set `free_shipping` to boolean false.
3. WHILE `free_shipping` is true, THE Promotion_Engine SHALL set `shipping_cost` to integer 0.
4. WHILE `free_shipping` is false, THE Promotion_Engine SHALL set `shipping_cost` to the shipping area's cost, treated as a non-negative integer in the range 0 through 999,999,999,999, substituting 0 for any missing, negative, or non-integer value.
5. WHEN evaluating `free_shipping` and `shipping_cost` for identical inputs at a fixed evaluation time, THE Promotion_Engine SHALL return the same values on every invocation and SHALL produce no side effects.

### Requirement 9: Buy-X-Get-Free-Item Promotions

**User Story:** As a buyer, I want to receive a free gift when I meet a promotion's conditions, so that qualifying purchases include the advertised bonus item.

#### Acceptance Criteria

1. WHEN a Gift_Promotion's qualifying conditions are met AND a valid `gift_product_id` references an existing, active product AND `gift_quantity` is an integer between 1 and 999 inclusive, THE Promotion_Engine SHALL grant the configured Gift_Item.
2. WHERE a Gift_Promotion specifies a `min_subtotal` greater than 0, THE Promotion_Engine SHALL treat the gift as qualifying only when the Subtotal_After_Discounts is greater than or equal to `min_subtotal`, and SHALL grant no gift when the Subtotal_After_Discounts is less than `min_subtotal`.
3. WHERE a Gift_Promotion specifies a `min_quantity` greater than 0, THE Promotion_Engine SHALL treat the gift as qualifying only when the total cart quantity is greater than or equal to `min_quantity`, where total cart quantity is the integer sum of the quantities of all paid items (lines with `is_gift` not equal to 1, each line having quantity of at least 1), and SHALL grant no gift when the total cart quantity is less than `min_quantity`.
4. WHEN granting a Gift_Item, THE Promotion_Engine SHALL set the gift line's `unit_price` to exactly 0, its `quantity` to the configured `gift_quantity`, and its `is_gift` flag to 1.
5. IF a Gift_Promotion has a `gift_product_id` that does not reference an existing, active product OR has a `gift_quantity` less than 1, THEN THE Promotion_Engine SHALL grant no gift for that promotion AND SHALL leave all existing paid and gift lines unchanged.
6. WHEN the Promotion_Engine evaluates the same cart contents and the same Gift_Promotion configuration more than once, THE Promotion_Engine SHALL produce identical gift-line results each time AND SHALL make no modification to any cart line other than the gift lines it grants under this promotion.

### Requirement 10: Discount Stacking and Determinism

**User Story:** As a store operator, I want predictable promotion stacking, so that totals are consistent and exclusivity rules are enforced.

#### Acceptance Criteria

1. WHEN evaluated two or more times with byte-identical cartItems, shippingArea, promotions inputs and the same evaluation time (`now`), THE Promotion_Engine SHALL return PromotionResult values that are field-by-field identical across every field (subtotal, discount_total, free_shipping, shipping_cost, total, item_discounts, gift_items, and applied_promotions).
2. WHEN the Promotion_Engine evaluates a cart, THE Promotion_Engine SHALL determine the highest-precedence discount promotion as the discount promotion with the lowest priority value, and where two or more discount promotions share the same lowest priority value, the one with the lowest id, considering only promotions of type Category_Discount and Cart_Discount.
3. WHERE the highest-precedence discount promotion is non-stackable, THE Promotion_Engine SHALL apply only that one discount promotion and SHALL exclude all other Category_Discount and Cart_Discount promotions from applying, leaving free-shipping and gift promotions unaffected.
4. WHERE every applicable discount promotion is stackable, THE Promotion_Engine SHALL apply each qualifying Category_Discount and Cart_Discount according to the per-item and per-order limits, leaving free-shipping and gift promotions unaffected.
5. THE Promotion_Engine SHALL apply at most one Category_Discount per item and at most one Cart_Discount per order.
6. THE Promotion_Engine SHALL evaluate promotions in Precedence order, defined as ascending priority value then ascending id.
7. WHEN the Promotion_Engine evaluates a cart, THE Promotion_Engine SHALL produce its PromotionResult without performing any database access and without mutating its input arguments or any external state.

### Requirement 11: Total Integrity and Non-Negativity

**User Story:** As a store operator, I want order totals to be mathematically sound, so that no order is ever undercharged below zero or charged a negative discount.

#### Acceptance Criteria

1. WHEN evaluating a cart, THE Promotion_Engine SHALL compute `total` as `max(0, subtotal - discount_total + shipping_cost)`, producing a non-negative integer in the range 0 through 999,999,999,999 in Rupiah.
2. WHEN evaluating a cart, THE Promotion_Engine SHALL produce a `discount_total` that is a non-negative integer greater than or equal to 0 and less than or equal to the Subtotal.
3. WHEN evaluating a cart, THE Promotion_Engine SHALL ensure that each individual line item discount is a non-negative integer that is at most that item's line subtotal, where line subtotal is the item's Effective_Price multiplied by its quantity (quantity treated as at least 1).
4. WHEN evaluating a cart, THE Promotion_Engine SHALL produce `subtotal`, `discount_total`, `shipping_cost`, and `total` each as a non-negative integer in the range 0 through 999,999,999,999 in Rupiah.
5. IF a computed discount would exceed its base amount, where the base amount is the line subtotal for an item discount or the Subtotal_After_Discounts for a Cart_Discount, THEN THE Promotion_Engine SHALL limit the discount to that base amount.
6. IF any computed discount value is negative, THEN THE Promotion_Engine SHALL clamp that discount value to 0.

### Requirement 12: Order Persistence of Discount Outcomes

**User Story:** As a store operator, I want each order to record its discount outcome, so that order history and totals are auditable.

#### Acceptance Criteria

1. WHEN persisting an order, THE Checkout_Processor SHALL store `subtotal`, `discount_total`, `free_shipping`, `shipping_cost`, and `total` on the order record, where `subtotal`, `discount_total`, `shipping_cost`, and `total` are each stored as non-negative integer Rupiah values (minimum 0) and `free_shipping` is stored as either 0 (not applied) or 1 (applied).
2. THE Checkout_Processor SHALL persist `total` as the integer Rupiah value `max(0, subtotal - discount_total + shipping_cost)`.
3. WHEN a promotion is applied to an order, THE Checkout_Processor SHALL insert one `order_promotions` row per applied promotion containing `promotion_id`, `promotion_name`, `promotion_type`, and `discount_amount`, where `discount_amount` is a non-negative integer Rupiah value (minimum 0) and is stored as 0 for non-monetary promotions such as free shipping.
4. WHEN persisting a Gift_Item, THE Checkout_Processor SHALL insert an `order_items` row with `product_price` of 0 and `is_gift` set to 1.
5. THE Checkout_Processor SHALL ensure that the sum of `discount_amount` across `order_promotions` rows whose `promotion_type` is a discount type (`category_discount` or `cart_discount`) equals the order's `discount_total`.
6. WHEN persisting paid items and gift items, THE Checkout_Processor SHALL decrease stock for items whose status is `ready`.
7. WHEN persisting paid items and gift items, THE Checkout_Processor SHALL leave stock unchanged for items whose status is not `ready`.
8. IF any part of order persistence fails (order record, `order_promotions` rows, `order_items` rows, or stock decrement), THEN THE Checkout_Processor SHALL persist no order record, no promotion rows, no item rows, and no stock changes, leaving all affected records in their pre-operation state and returning an error indication that persistence did not complete.

### Requirement 13: Server-Side Pricing Authority at Checkout

**User Story:** As a store operator, I want all prices and discounts recomputed on the server at checkout, so that client-side values cannot be trusted or manipulated.

#### Acceptance Criteria

1. WHEN processing a checkout submission, THE Checkout_Processor SHALL recompute each item's Effective_Price server-side using the Price_Resolver, gated by the Flash_Sale_Window state evaluated against a single reference timestamp captured once at the start of submission processing, and SHALL disregard any client-supplied price values.
2. WHEN processing a checkout submission, THE Checkout_Processor SHALL recompute the discount_total, free_shipping state, shipping_cost, and gift_items server-side using the Promotion_Engine, and SHALL derive the order total solely from the resulting PromotionResult.
3. IF the Flash_Sale_Window closes between page load and checkout submission, THEN THE Checkout_Processor SHALL charge `selling_price` in integer Rupiah for affected items.
4. THE Checkout_Processor SHALL persist only server-computed prices and totals and SHALL NOT persist any client-supplied price, discount, or total value.
5. IF the Price_Resolver or Promotion_Engine cannot complete the server-side recomputation for the submitted cart, THEN THE Checkout_Processor SHALL reject the submission, SHALL NOT persist any order, and SHALL return an error indication to the customer stating that checkout could not be completed.
6. IF a client-supplied total accompanies the submission and differs from the server-computed total, THEN THE Checkout_Processor SHALL charge the server-computed total in integer Rupiah.

### Requirement 14: Admin Promotion Management

**User Story:** As an admin, I want to create, edit, activate, and delete promotions of every type, so that I can manage store promotions without code changes.

#### Acceptance Criteria

1. WHEN an Admin submits a request to create or edit a promotion with valid data, THE Promotion_Admin SHALL persist the `promotions` record and replace the associated `promotion_targets` rows within a single atomic database transaction that either commits all changes together or applies none.
2. WHEN an Admin submits a toggle request for a promotion, THE Promotion_Admin SHALL set that promotion's `is_active` value to 1 if its current value is 0, or to 0 if its current value is 1.
3. WHEN an Admin submits a delete request for a promotion, THE Promotion_Admin SHALL remove that `promotions` record and cascade-delete all associated `promotion_targets` rows within a single atomic database transaction that either commits all deletions together or applies none.
4. WHEN a promotion create, edit, toggle, or delete operation completes successfully, THE Promotion_Admin SHALL redirect to the promotion management listing and display a success message identifying the completed operation.
5. IF an Admin requests a state-changing promotion operation without a valid admin session, THEN THE Promotion_Admin SHALL deny the operation, leave all `promotions` and `promotion_targets` records unchanged, and redirect to the admin login page.
6. IF an Admin submits a state-changing promotion form with a missing or invalid CSRF token, THEN THE Promotion_Admin SHALL reject the request, leave all `promotions` and `promotion_targets` records unchanged, and display an error message indicating the request could not be verified.
7. IF a database error occurs during a promotion create, edit, toggle, or delete operation, THEN THE Promotion_Admin SHALL roll back the transaction so that no partial changes are persisted and redirect with an error message indicating the operation failed.

### Requirement 15: Admin Promotion Validation

**User Story:** As an admin, I want my promotion inputs validated, so that misconfigured promotions are rejected before they reach buyers.

#### Acceptance Criteria

1. IF a submitted promotion uses the percent discount type AND the discount value is not an integer within the inclusive range 1 through 100, THEN THE Promotion_Admin SHALL reject the submission, SHALL NOT persist any promotion record, and SHALL redirect with an error message that identifies the discount value field as invalid.
2. IF a submitted promotion uses the fixed discount type AND the discount value is not an integer greater than or equal to 1, THEN THE Promotion_Admin SHALL reject the submission, SHALL NOT persist any promotion record, and SHALL redirect with an error message that identifies the discount value field as invalid.
3. IF a submitted `min_subtotal`, `min_quantity`, or `gift_quantity` value is not an integer greater than or equal to 0, THEN THE Promotion_Admin SHALL reject the submission, SHALL NOT persist any promotion record, and SHALL redirect with an error message that identifies the offending field as invalid.
4. IF a buy-X-get-free-item promotion has a missing `gift_product_id`, a `gift_product_id` that does not match an existing product, a `gift_product_id` whose product is not active, or a `gift_quantity` less than 1, THEN THE Promotion_Admin SHALL reject the submission, SHALL NOT persist any promotion record, and SHALL redirect with an error message that identifies the gift field as invalid.
5. IF a numeric promotion field (discount value, `min_subtotal`, `min_quantity`, or `gift_quantity`) is empty or contains a non-numeric value when that field is required for the selected promotion type, THEN THE Promotion_Admin SHALL reject the submission, SHALL NOT persist any promotion record, and SHALL redirect with an error message that identifies the offending field as invalid.
6. WHEN a promotion submission is rejected as invalid, THE Promotion_Admin SHALL NOT create or modify any promotion record and SHALL re-display the form populated with the admin's previously submitted input values.

### Requirement 16: Backward Compatibility With No Active Promotions

**User Story:** As a store operator, I want the system to behave exactly as before when no promotions are active, so that introducing the engine does not change normal orders.

#### Acceptance Criteria

1. WHEN the Promotion_Repository returns an empty list of active promotions, THE Promotion_Engine SHALL produce a `discount_total` of 0 and set `free_shipping` to false.
2. WHEN the Promotion_Repository returns an empty list of active promotions, THE Promotion_Engine SHALL produce an empty `gift_items` list, an empty `item_discounts` list, and an empty `applied_promotions` list.
3. WHEN the Promotion_Repository returns an empty list of active promotions, THE Promotion_Engine SHALL set `shipping_cost` to the shipping area's cost as a non-negative integer in Rupiah.
4. WHEN the Promotion_Repository returns an empty list of active promotions, THE Promotion_Engine SHALL compute `total` as the non-negative integer sum of `subtotal` and `shipping_cost`, equal to `max(0, subtotal - discount_total + shipping_cost)` with `discount_total` equal to 0.
5. IF a historical order record persisted before the promotion engine has a `discount_total` that is null or missing, THEN THE system SHALL treat `discount_total` as integer 0.
6. IF a historical order record persisted before the promotion engine has a `free_shipping` value that is null or missing, THEN THE system SHALL treat `free_shipping` as integer 0 (false), preserving the invariant `total = subtotal - discount_total + shipping_cost`.

### Requirement 17: Error Handling During Promotion Evaluation and Checkout

**User Story:** As a buyer, I want checkout to fail safely, so that errors never produce a negative, inflated, or partially-persisted order.

#### Acceptance Criteria

1. IF a Gift_Promotion references a gift product whose active flag != 1 OR whose available stock < the configured gift_quantity, THEN THE Promotion_Engine SHALL skip that gift, write a misconfiguration entry to the error log indicating the affected promotion and gift product, continue applying all remaining promotions, and still produce a complete PromotionResult for the order.
2. IF a database error occurs during checkout persistence, THEN THE Checkout_Processor SHALL roll back the entire checkout transaction, retain the cart contents unchanged, and display a generic error message indicating that checkout could not be completed.
3. IF a checkout database error occurs, THEN THE Checkout_Processor SHALL write technical error details to the error log and SHALL NOT persist any order record, any order_items rows, any order_promotions rows, or any stock decrement.
4. IF a misconfigured promotion specifies a percent value greater than 100, THEN THE Promotion_Engine SHALL clamp the percent value to 100 before computing the discount.
5. IF a misconfigured promotion specifies a discount_value less than 0, THEN THE Promotion_Engine SHALL clamp the discount_value to 0 so that the resulting discount is never negative.

### Requirement 18: Security Controls for Promotions

**User Story:** As a store operator, I want promotion management and rendering to be secure, so that the feature does not introduce vulnerabilities.

#### Acceptance Criteria

1. THE Promotion_Admin SHALL execute all promotion queries using PDO prepared statements with bound parameters, with no user-supplied input concatenated into the SQL string.
2. WHEN rendering a promotion name or a gift product name to an HTML response, THE system SHALL pass the value through `sanitizeOutput()` before output so that any HTML or script metacharacters are encoded.
3. WHERE an admin promotion form changes state, THE Promotion_Admin SHALL embed a CSRF token generated via `generateCSRFToken()` in the form before display.
4. WHEN an Admin submits a state-changing promotion request, THE Promotion_Admin SHALL validate the submitted CSRF token via `validateCSRFToken()` before performing any state change.
5. IF the submitted CSRF token is missing or does not match the session token, THEN THE Promotion_Admin SHALL reject the request, make no change to promotion data, and return an error indication to the caller.
6. IF an unauthenticated user requests a state-changing promotion handler, THEN THE Promotion_Admin SHALL deny the request via `requireAdmin()`, make no change to promotion data, and redirect to the admin login.
