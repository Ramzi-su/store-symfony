<?php

namespace App\Tests\Cart;

use App\Cart\CartTotals;
use PHPUnit\Framework\TestCase;

class CartTotalsTest extends TestCase
{
    public function testEmptyCartCostsNothing(): void
    {
        $totals = CartTotals::fromSubtotal(0);

        $this->assertEquals(0, $totals->shipping);
        $this->assertEquals(0, $totals->tax);
        $this->assertEquals(0, $totals->total);
    }

    public function testShippingAndTaxAreAddedToTheSubtotal(): void
    {
        $totals = CartTotals::fromSubtotal(100);

        $this->assertEquals(100, $totals->subtotal);
        $this->assertEquals(10, $totals->shipping);
        $this->assertEquals(10, $totals->tax);
        $this->assertEquals(120, $totals->total);
    }
}
