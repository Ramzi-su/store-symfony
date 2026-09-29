<?php

namespace App\Cart;

/**
 * Amounts shown in the cart and charged at checkout. Computed in one place
 * so the cart page, the checkout page and Stripe always agree.
 */
final readonly class CartTotals
{
    public const SHIPPING_COST = 10.0;
    public const TAX_RATE = 0.1;

    private function __construct(
        public float $subtotal,
        public float $shipping,
        public float $tax,
        public float $total,
    ) {
    }

    public static function fromSubtotal(float $subtotal): self
    {
        // An empty cart is free: no shipping and no tax.
        $shipping = $subtotal > 0 ? self::SHIPPING_COST : 0.0;
        $tax = $subtotal * self::TAX_RATE;

        return new self($subtotal, $shipping, $tax, $subtotal + $shipping + $tax);
    }
}
