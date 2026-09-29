<?php

namespace App\Cart\Exception;

use App\Entity\Product;

/**
 * Thrown when a product cannot be added (or kept) in the cart because none is left in stock.
 */
class OutOfStockException extends \RuntimeException
{
    public function __construct(public readonly Product $product)
    {
        parent::__construct(sprintf('Product "%s" is out of stock.', $product->getName()));
    }
}
