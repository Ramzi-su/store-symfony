<?php

namespace App\Tests\Cart;

use App\Cart\CartTotals;
use PHPUnit\Framework\TestCase;

class CartTotalsTest extends TestCase
{
    public function testEmptyCartCostsNothing(): void
    {
        $totals = CartTotals::fromSubtotal(0);

        $this->assertSame(0, $totals->shipping);
        $this->assertSame(0, $totals->tax);
        $this->assertSame(0, $totals->total);
    }

    public function testShippingAndTaxAreAddedToTheSubtotal(): void
    {
        $totals = CartTotals::fromSubtotal(10000);

        $this->assertSame(10000, $totals->subtotal);
        $this->assertSame(1000, $totals->shipping);
        $this->assertSame(1000, $totals->tax);
        $this->assertSame(12000, $totals->total);
    }

    public function testTaxIsRoundedToTheNearestCent(): void
    {
        // 10% of $19.99 is $1.999: charged $2.00, and the total stays an exact integer.
        $totals = CartTotals::fromSubtotal(1999);

        $this->assertSame(200, $totals->tax);
        $this->assertSame(1999 + 1000 + 200, $totals->total);
    }
}
