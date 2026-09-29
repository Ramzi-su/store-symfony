<?php

namespace App\Cart;

/**
 * Amounts shown in the cart and charged at checkout. Computed in one place
 * so the cart page, the checkout page and Stripe always agree.
 */
final readonly class CartTotals
{
    // All amounts are in cents.
    public const SHIPPING_COST = 1000;
    public const TAX_RATE_PERCENT = 10;

    private function __construct(
        public int $subtotal,
        public int $shipping,
        public int $tax,
        public int $total,
    ) {
    }

    public static function fromSubtotal(int $subtotal): self
    {
        // An empty cart is free: no shipping and no tax.
        $shipping = $subtotal > 0 ? self::SHIPPING_COST : 0;
        // Rounded to the nearest cent, so the total always matches what Stripe charges.
        $tax = (int) round($subtotal * self::TAX_RATE_PERCENT / 100);

        return new self($subtotal, $shipping, $tax, $subtotal + $shipping + $tax);
    }
}
