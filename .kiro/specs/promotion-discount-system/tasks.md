# Implementation Plan: Promotion & Discount System

## Overview

This plan implements a rule-driven promotion engine and a flash-sale price-expiry fix for the TC Komputer PHP application, following the existing stack conventions (PHP native, PDO prepared statements, integer Rupiah, session cart, server-rendered pages, CSRF-protected admin forms, `sanitizeOutput()`).

The build order is bottom-up and incremental: additive/idempotent database migrations first, then the pure price-resolution helpers, then the promotion repository, then the deterministic engine, then checkout persistence and storefront unification, and finally the admin CRUD. Each step builds on the previous one and ends by wiring new logic into the surfaces that consume it, so there is no orphaned code. Property-based tests (PHPUnit, in the existing `tests/Property/` directory) are placed next to the functions they validate to catch correctness regressions early.

## Tasks

- [ ] 1. Database migration (additive and idempotent)
  - [ ] 1.1 Create migration script for new promotion tables
    - Add a root-level `migrate_promotions.php` following the existing `migrate_*.php` convention (run once via Laragon PHP, PDO from `config/db.php`)
    - Create `promotions`, `promotion_targets`, and `order_promotions` tables using `CREATE TABLE IF NOT EXISTS` with the exact columns, indexes, and foreign keys from the design
    - _Requirements: 12.3, 14.1, 14.3_
  - [ ] 1.2 Add additive columns to existing order tables
    - In the same migration script, add `orders.discount_total`, `orders.free_shipping`, `order_items.is_gift`, `order_items.promotion_id`
    - Guard each `ALTER TABLE ... ADD COLUMN` with an `INFORMATION_SCHEMA.COLUMNS` existence check so the script is safe to re-run; defaults preserve historical-order totals
    - _Requirements: 12.1, 12.4, 16.5, 16.6_

- [ ] 2. Price-resolution core in `config/promotions.php`
  - [ ] 2.1 Scaffold `config/promotions.php` and implement window + clamp helpers
    - Create `config/promotions.php` requiring only PDO and `config/helpers.php`
    - Implement `isFlashSaleWindowActive(array $storeSettings, ?int $now = null): bool` (active flag == 1, non-empty parseable `flash_sale_end`, strictly future, single captured `now`, no DB access, no side effects)
    - Implement `clampNonNegative(int $value): int`
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7, 11.6_
  - [ ] 2.2 Implement effective-price functions
    - Implement `getEffectiveProductPrice(array $product, bool $flashWindowActive): int` returning `promo_price` only when window active AND `promo_active == 1` AND `promo_price > 0` AND `promo_stock > 0`, else `selling_price`, always clamped `>= 0`
    - Implement `isPromoPriceActive(array $product, bool $flashWindowActive): bool` mirroring the price decision for badges/strike-through
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 2.7, 2.8_
  - [ ]* 2.3 Write property test for flash-sale gated effective price
    - Create `tests/Property/EffectivePricePropertyTest.php` (PHPUnit, `mt_rand` generators, ~500 iterations, mirroring `OrderTotalPropertyTest`)
    - **Property 1: Flash-sale gated effective price**
    - **Validates: Requirements 2.1, 2.7**
  - [ ]* 2.4 Write property test for expired/inactive flash sale
    - Add to `tests/Property/EffectivePricePropertyTest.php`
    - **Property 2: Expired/inactive flash sale never charges promo price**
    - **Validates: Requirements 1.2, 1.3, 1.4, 1.5, 2.2**
  - [ ]* 2.5 Write unit tests for `isFlashSaleWindowActive` truth table
    - Table-driven cases: window on/off, end in past/future, empty/unparseable end, determinism
    - _Requirements: 1.1, 1.4, 1.5, 1.6, 1.7_

- [ ] 3. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 4. Promotion repository in `config/promotions.php`
  - [ ] 4.1 Implement `loadActivePromotions`
    - Implement `loadActivePromotions(PDO $pdo, ?int $now = null): array` using PDO prepared statements only
    - Filter `is_active = 1 AND (start_at IS NULL OR start_at <= now) AND (end_at IS NULL OR end_at >= now)`; eager-load `promotion_targets` into each promotion's `targets` (empty list when none) and the gift product reference
    - Order deterministically by `priority` ASC then `id` ASC; raise on query failure rather than returning a partial/empty result
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 4.7_
  - [ ]* 4.2 Write unit tests for repository ordering and filtering
    - Seed promotions with varied schedules/priorities; assert in-window filtering, deterministic ordering, and target attachment
    - _Requirements: 4.1, 4.4, 4.6_

- [ ] 5. Promotion engine in `config/promotions.php`
  - [ ] 5.1 Implement subtotal computation and engine scaffolding
    - Add `evaluatePromotions(array $cartItems, array $shippingArea, array $promotions, int $now): array` scaffold plus internal helpers (`filterByType`, `targetsCategory`, `addApplied`, `firstNonStackable`)
    - Compute Subtotal over paid items as integer sum of `unit_price * quantity` (quantity coerced to >= 1, prices clamped >= 0); empty cart yields 0
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5_
  - [ ] 5.2 Implement `computeDiscount` and the per-item category-discount pass
    - Implement `computeDiscount` (percent = floor(base * value / 100) with value clamped 0..100 and optional `max_discount` cap; fixed = clamp to base; unknown type = 0)
    - Apply at most one highest-precedence category discount per item; record only discounts `> 0` and `<= line subtotal`
    - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5, 6.6, 6.7, 6.8, 11.3, 11.5, 17.4, 17.5_
  - [ ] 5.3 Implement the single cart-discount pass
    - Apply at most one highest-precedence cart discount to the post-category subtotal when its effective `min_subtotal` is met; clamp to remaining base; unknown type / unmet threshold leaves subtotal unchanged
    - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5, 7.6, 7.7, 7.8, 7.9_
  - [ ] 5.4 Implement free-shipping determination
    - Set `free_shipping` true when any active free-shipping promo has effective `min_subtotal <= subtotal_after_discounts`; set `shipping_cost` to 0 when free, else the clamped shipping-area cost
    - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5_
  - [ ] 5.5 Implement gift-item granting
    - Grant a gift when `min_subtotal`/`min_quantity` conditions are met and a valid active `gift_product_id` with `gift_quantity` 1..999 exists; gift line has `unit_price == 0`, configured quantity, `is_gift = 1`
    - Skip and log misconfigured/unavailable gift products without altering other lines
    - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5, 9.6, 17.1_
  - [ ] 5.6 Assemble PromotionResult with stacking, clamping, and total integrity
    - Enforce non-stackable exclusivity across category/cart discounts in precedence order; clamp `discount_total <= subtotal`; compute `total = max(0, subtotal - discount_total + shipping_cost)`
    - Return the full PromotionResult (`subtotal`, `discount_total`, `free_shipping`, `shipping_cost`, `total`, `item_discounts`, `gift_items`, `applied_promotions`) without mutating inputs or touching the DB; empty-promotion case reproduces pre-feature behavior
    - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5, 10.6, 10.7, 11.1, 11.2, 11.4, 11.6, 16.1, 16.2, 16.3, 16.4_
  - [ ]* 5.7 Write property test for free shipping correctness
    - Create `tests/Property/FreeShippingPropertyTest.php`
    - **Property 3: Free shipping correctness**
    - **Validates: Requirements 8.1, 8.2, 8.3**
  - [ ]* 5.8 Write property test for category discount scoping
    - Create `tests/Property/CategoryDiscountPropertyTest.php`
    - **Property 4: Category discount scoping**
    - **Validates: Requirements 6.2, 6.4, 6.5**
  - [ ]* 5.9 Write property test for buy-X-get-free-item correctness
    - Create `tests/Property/GiftItemPropertyTest.php`
    - **Property 5: Buy-X-get-free-item correctness**
    - **Validates: Requirements 9.1, 9.2, 9.3, 9.4**
  - [ ]* 5.10 Write property test for order total integrity
    - Create `tests/Property/PromotionTotalPropertyTest.php`
    - **Property 6: Order total integrity**
    - **Validates: Requirements 5.1, 11.1, 11.2, 11.4**
  - [ ]* 5.11 Write property test for non-negativity / no underflow
    - Add to `tests/Property/PromotionTotalPropertyTest.php`
    - **Property 7: Non-negativity / no underflow**
    - **Validates: Requirements 11.3, 11.5, 11.6**
  - [ ]* 5.12 Write property test for determinism and bounded stacking
    - Add to `tests/Property/PromotionTotalPropertyTest.php`
    - **Property 8: Determinism & bounded stacking**
    - **Validates: Requirements 10.1, 10.3, 10.5, 10.7**
  - [ ]* 5.13 Write unit tests for backward compatibility (no active promotions)
    - Assert empty-promotion evaluation yields zero discount, no gifts/item discounts, shipping = area cost, `total = subtotal + shipping_cost`
    - _Requirements: 16.1, 16.2, 16.3, 16.4_

- [ ] 6. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 7. Checkout integration and order persistence
  - [ ] 7.1 Implement `persistOrderPromotions`
    - Add `persistOrderPromotions(PDO $pdo, int $orderId, array $appliedPromotions): void` to `config/promotions.php`, inserting one `order_promotions` row per applied promotion (`promotion_id`, `promotion_name`, `promotion_type`, `discount_amount`) via prepared statements; non-monetary promos record `discount_amount = 0`
    - _Requirements: 12.3, 12.5, 18.1_
  - [ ] 7.2 Wire server-side recompute and persistence into `actions/checkout-process.php`
    - Capture a single reference timestamp; rebuild the cart with `getEffectiveProductPrice` (flash-sale gated), call `loadActivePromotions` + `evaluatePromotions`, and derive totals solely from the result, disregarding client-supplied prices/totals
    - Inside the existing transaction persist `orders` (subtotal, discount_total, free_shipping, shipping_cost, clamped total), paid + gift `order_items` (`is_gift`, `product_price = 0` for gifts), `order_promotions`, and decrease stock only for `ready` items; roll back and surface a generic error on failure
    - _Requirements: 12.1, 12.2, 12.4, 12.6, 12.7, 12.8, 13.1, 13.2, 13.3, 13.4, 13.5, 13.6, 17.2, 17.3_
  - [ ] 7.3 Display promotion outcome in `checkout.php`
    - Render server-computed subtotal, discounts, free-shipping flag, gift items, and total using `getEffectiveProductPrice` and `evaluatePromotions`; pass promotion/gift names through `sanitizeOutput()`
    - _Requirements: 3.2, 13.1, 13.2, 18.2_
  - [ ]* 7.4 Write property test for persisted-order consistency
    - Add to `tests/Property/PromotionTotalPropertyTest.php`, cross-checking computed results against persisted-order invariants
    - **Property 9: Persisted-order consistency**
    - **Validates: Requirements 12.2, 12.4, 12.5**
  - [ ]* 7.5 Write integration test for checkout persistence
    - Seed one promotion of each type, run checkout, assert `orders`, `order_items` (incl. gift lines), and `order_promotions` rows match the PromotionResult
    - _Requirements: 12.1, 12.3, 12.4, 12.5_

- [ ] 8. Storefront price unification (flash-sale fix rollout)
  - [ ] 8.1 Update `index.php` to use the unified effective price
    - Compute the window flag once, replace ad-hoc `$isPromo` expressions in featured/newest/flash-sale sections with `getEffectiveProductPrice` / `isPromoPriceActive`
    - _Requirements: 3.1, 3.3, 3.5_
  - [ ] 8.2 Update `products.php` and `category.php` listing cards
    - Replace ad-hoc promo-price logic on listing cards with the unified resolver
    - _Requirements: 3.1, 3.3, 3.5_
  - [ ] 8.3 Update `product-detail.php`
    - Apply the unified resolver to the main price, strike-through display, and related-product cards
    - _Requirements: 3.1, 3.3, 3.5_
  - [ ] 8.4 Update `cart.php` line prices and totals
    - Resolve each line's effective price via the unified resolver gated by the current window
    - _Requirements: 3.1_
  - [ ] 8.5 Update `actions/cart-add.php` and `actions/cart-update.php`
    - Store/revalidate the session price using `getEffectiveProductPrice` so cart prices stay consistent with display and checkout
    - _Requirements: 3.1, 3.2_
  - [ ]* 8.6 Write integration test for flash-sale expiry regression
    - Set `flash_sale_end` in the past and assert every surface shows and charges `selling_price`, and that display equals charged price under identical state
    - _Requirements: 3.4, 3.6, 3.7_

- [ ] 9. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 10. Admin promotion management
  - [ ] 10.1 Create `admin/promotions.php` listing page
    - List promotions with type, status, schedule, and action links, mirroring `admin/flash-sales.php`; render names through `sanitizeOutput()`; call `requireAdmin()`
    - _Requirements: 14.4, 18.2_
  - [ ] 10.2 Create `admin/promotion-add.php` and `admin/promotion-edit.php`
    - Per-type forms (free shipping, category discount, buy-X-get-free-item, cart discount) with embedded CSRF token; `requireAdmin()` + `validateCSRFToken()`; server-side validation (percent 1-100, fixed >= 1, thresholds/quantities >= 0, gift product exists and is active); persist `promotions` and replace `promotion_targets` atomically; re-display populated form on rejection
    - _Requirements: 14.1, 14.4, 14.5, 14.6, 14.7, 15.1, 15.2, 15.3, 15.4, 15.5, 15.6, 18.1, 18.2, 18.3, 18.4, 18.5, 18.6_
  - [ ] 10.3 Create `admin/promotion-toggle.php`
    - Flip `is_active` between 0 and 1 with `requireAdmin()` + CSRF validation; redirect with success/error flash
    - _Requirements: 14.2, 14.5, 14.6, 14.7, 18.4, 18.5, 18.6_
  - [ ] 10.4 Create `admin/promotion-delete.php`
    - Delete the promotion and cascade `promotion_targets` in one atomic transaction with `requireAdmin()` + CSRF validation; redirect with flash
    - _Requirements: 14.3, 14.5, 14.6, 14.7, 18.4, 18.5, 18.6_
  - [ ]* 10.5 Write tests for promotion validation rules
    - Cover percent/fixed bounds, threshold/quantity non-negativity, gift-product existence/active checks, and form re-display on rejection
    - _Requirements: 15.1, 15.2, 15.3, 15.4, 15.5, 15.6_
  - [ ]* 10.6 Write tests for admin auth and CSRF guards
    - Assert state-changing handlers deny missing-session and invalid-token requests without mutating data (reuse `AdminSessionGuardPropertyTest` / `CSRFTokenPropertyTest` patterns)
    - _Requirements: 18.4, 18.5, 18.6_

- [ ] 11. Final checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional test sub-tasks and can be skipped for a faster MVP; core implementation sub-tasks must always be implemented.
- Each task references specific requirements clauses for traceability.
- Property-based tests follow the existing `tests/Property/` PHPUnit convention (no external PBT dependency) and validate the universal Correctness Properties from the design.
- Migrations are additive and idempotent so they are safe to run against the live database.
- Checkout always recomputes prices and promotions server-side; client-supplied values are never trusted.

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1", "1.2"] },
    { "id": 1, "tasks": ["2.1"] },
    { "id": 2, "tasks": ["2.2"] },
    { "id": 3, "tasks": ["4.1", "2.3", "2.4", "2.5", "8.1", "8.2", "8.3", "8.4", "8.5"] },
    { "id": 4, "tasks": ["5.1", "4.2", "8.6"] },
    { "id": 5, "tasks": ["5.2"] },
    { "id": 6, "tasks": ["5.3"] },
    { "id": 7, "tasks": ["5.4"] },
    { "id": 8, "tasks": ["5.5"] },
    { "id": 9, "tasks": ["5.6"] },
    { "id": 10, "tasks": ["5.7", "5.8", "5.9", "5.10", "5.11", "5.12", "5.13"] },
    { "id": 11, "tasks": ["7.1"] },
    { "id": 12, "tasks": ["7.2"] },
    { "id": 13, "tasks": ["7.3", "7.4", "7.5"] },
    { "id": 14, "tasks": ["10.1", "10.2", "10.3", "10.4"] },
    { "id": 15, "tasks": ["10.5", "10.6"] }
  ]
}
```
