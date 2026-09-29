<?php

namespace App\Order;

use App\Cart\Exception\OutOfStockException;
use App\Entity\Orders;
use App\Entity\Product;
use App\Enum\OrderStatus;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Takes the ordered quantities out of stock when an order is created (before the
 * customer is sent to Stripe) and gives them back if the order is cancelled.
 *
 * Every change is a single atomic UPDATE with a guard in its WHERE clause, so two
 * customers checking out at the same time can never both get the last unit:
 * the database serialises the two statements and the second one matches no row.
 */
class StockReservation
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * Reserves every item of the order, or none of them.
     * Must run inside a transaction so a failure on one item rolls back the others.
     *
     * @throws OutOfStockException when an item is no longer available in the ordered quantity
     */
    public function reserve(Orders $order): void
    {
        foreach ($order->getItems() as $item) {
            $product = $item->getProduct();

            $updated = $this->em->createQuery(
                'UPDATE App\Entity\Product p SET p.stock = p.stock - :quantity
                 WHERE p.id = :id AND p.stock >= :quantity'
            )
                ->setParameter('quantity', $item->getQuantity())
                ->setParameter('id', $product->getId())
                ->execute();

            if ($updated === 0) {
                throw new OutOfStockException($product);
            }

            // DQL updates bypass the entity: reload it so the page shows the new stock.
            $this->em->refresh($product);
        }
    }

    /**
     * Cancels a pending order and puts its items back in stock (customer cancel,
     * Stripe failure or expired session).
     *
     * @return bool false when the order was not pending anymore (paid or already cancelled)
     */
    public function cancel(Orders $order): bool
    {
        return $this->cancelFrom($order, OrderStatus::Pending);
    }

    /**
     * Moves an order from $from to "cancelled" and, when $restock is true, puts its
     * items back in stock.
     *
     * The status change is itself a guarded UPDATE (WHERE status = $from): if two
     * cancellations race (cancel page + "session expired" webhook, double click in
     * the admin), only one of them releases the stock.
     *
     * @return bool false when the order was not in the $from status anymore
     */
    public function cancelFrom(Orders $order, OrderStatus $from, bool $restock = true): bool
    {
        return $this->em->wrapInTransaction(function () use ($order, $from, $restock): bool {
            $cancelled = $this->em->createQuery(
                'UPDATE App\Entity\Orders o SET o.status = :cancelled WHERE o.id = :id AND o.status = :from'
            )
                ->setParameter('cancelled', OrderStatus::Cancelled->value)
                ->setParameter('from', $from->value)
                ->setParameter('id', $order->getId())
                ->execute();

            if ($cancelled === 0) {
                return false;
            }

            if ($restock) {
                foreach ($order->getItems() as $item) {
                    $this->em->createQuery('UPDATE App\Entity\Product p SET p.stock = p.stock + :quantity WHERE p.id = :id')
                        ->setParameter('quantity', $item->getQuantity())
                        ->setParameter('id', $item->getProduct()->getId())
                        ->execute();
                }
            }

            $this->em->refresh($order);
            foreach ($order->getItems() as $item) {
                $this->refreshIfManaged($item->getProduct());
            }

            return true;
        });
    }

    private function refreshIfManaged(Product $product): void
    {
        if ($this->em->contains($product)) {
            $this->em->refresh($product);
        }
    }
}
