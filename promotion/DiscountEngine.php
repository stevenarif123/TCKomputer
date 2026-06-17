<?php
/**
 * Discount Engine
 * Evaluates active promotions from the database and calculates the optimal discounts for a cart.
 */

class DiscountEngine {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Retrieves all currently active promotions
     */
    private function getActivePromotions(): array {
        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare("
            SELECT * FROM promotions 
            WHERE is_active = 1 
            AND start_date <= ? 
            AND end_date >= ?
            ORDER BY promo_type ASC, discount_value DESC
        ");
        $stmt->execute([$now, $now]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Applies all eligible discounts to the cart
     * 
     * @param array $cartItems Array of items. Each item must have: product_id, category_id, quantity, price, subtotal
     * @param int $shippingCost Initial shipping cost
     * @return array Contains discount_amount, applied_promotions, free_item_id, new_shipping_cost
     */
    public function applyDiscounts(array $cartItems, int $shippingCost = 0): array {
        $promotions = $this->getActivePromotions();
        
        $totalDiscount = 0;
        $appliedPromotions = [];
        $freeItemId = null;
        $newShippingCost = $shippingCost;
        
        // Calculate initial subtotal
        $cartSubtotal = 0;
        foreach ($cartItems as $item) {
            $cartSubtotal += $item['subtotal'];
        }

        foreach ($promotions as $promo) {
            // Check minimum spend constraint globally for this promo
            if ($promo['min_spend'] > 0 && $cartSubtotal < $promo['min_spend']) {
                continue;
            }

            switch ($promo['promo_type']) {
                case 'free_shipping':
                    if ($newShippingCost > 0) {
                        $maxShippingDiscount = $promo['discount_value'];
                        $discount = min($newShippingCost, $maxShippingDiscount);
                        if ($discount > 0) {
                            $newShippingCost -= $discount;
                            $totalDiscount += $discount;
                            $appliedPromotions[] = $promo['name'];
                        }
                    }
                    break;

                case 'cart_discount':
                    $discount = 0;
                    if ($promo['discount_type'] === 'percentage') {
                        $discount = (int)($cartSubtotal * ($promo['discount_value'] / 100));
                    } else {
                        $discount = $promo['discount_value'];
                    }
                    
                    // Don't discount more than the cart subtotal
                    $discount = min($cartSubtotal, $discount);
                    if ($discount > 0) {
                        $totalDiscount += $discount;
                        $appliedPromotions[] = $promo['name'];
                    }
                    break;

                case 'category_discount':
                    $catDiscount = 0;
                    foreach ($cartItems as $item) {
                        if (isset($item['category_id']) && $item['category_id'] == $promo['target_category_id']) {
                            if ($promo['discount_type'] === 'percentage') {
                                $catDiscount += (int)($item['subtotal'] * ($promo['discount_value'] / 100));
                            } else {
                                // Fixed discount per item or globally? Let's say per quantity of matching category
                                $catDiscount += $promo['discount_value'] * $item['quantity'];
                            }
                        }
                    }
                    if ($catDiscount > 0) {
                        $totalDiscount += $catDiscount;
                        $appliedPromotions[] = $promo['name'];
                    }
                    break;

                case 'free_item':
                    if ($promo['free_item_id'] && !$freeItemId) {
                        $freeItemId = $promo['free_item_id'];
                        $appliedPromotions[] = $promo['name'];
                    }
                    break;
            }
        }

        return [
            'discount_amount' => $totalDiscount,
            'applied_promotions' => array_unique($appliedPromotions),
            'free_item_id' => $freeItemId,
            'new_shipping_cost' => $newShippingCost,
            'original_subtotal' => $cartSubtotal
        ];
    }
}
