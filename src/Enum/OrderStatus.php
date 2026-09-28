<?php

namespace App\Enum;

/**
 * Backed enum: Doctrine stores the string value ("paid"...) in the existing
 * VARCHAR column and hydrates it back into an OrderStatus case.
 */
enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Shipped = 'shipped';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Paid => 'Payée',
            self::Shipped => 'Expédiée',
            self::Cancelled => 'Annulée',
        };
    }

    /**
     * A customer may only abandon an order that has not been paid yet.
     */
    public function isCancellable(): bool
    {
        return $this === self::Pending;
    }
}
