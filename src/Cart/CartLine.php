<?php

namespace App\Cart;

use App\Entity\Product;

/**
 * One product line of the cart, whatever the storage (session for guests,
 * database for logged-in users). Immutable: the cart changes through CartService.
 */
final readonly class CartLine
{
    public function __construct(
        public Product $product,
        public int $quantity,
        public ?string $color = null,
        public ?string $storage = null,
    ) {
    }

    public function getSubtotal(): float
    {
        return (float) $this->product->getPrice() * $this->quantity;
    }
}
