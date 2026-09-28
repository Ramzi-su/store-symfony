<?php

namespace App\Tests\Enum;

use App\Enum\OrderStatus;
use PHPUnit\Framework\TestCase;

class OrderStatusTest extends TestCase
{
    public function testOnlyPendingOrdersCanBeCancelled(): void
    {
        $this->assertTrue(OrderStatus::Pending->isCancellable());
        $this->assertFalse(OrderStatus::Paid->isCancellable());
        $this->assertFalse(OrderStatus::Shipped->isCancellable());
        $this->assertFalse(OrderStatus::Cancelled->isCancellable());
    }

    public function testValuesMatchWhatIsStoredInTheDatabase(): void
    {
        $this->assertSame(['pending', 'paid', 'shipped', 'cancelled'], array_column(OrderStatus::cases(), 'value'));
    }
}
