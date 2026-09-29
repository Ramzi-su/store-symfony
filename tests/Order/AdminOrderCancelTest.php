<?php

namespace App\Tests\Order;

use App\Entity\OrderItems;
use App\Entity\Orders;
use App\Entity\Product;
use App\Entity\User;
use App\Enum\OrderStatus;
use App\Order\StockReservation;
use App\Tests\DatabaseWebTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Cancelling or deleting an order from the admin must give its reserved stock back,
 * except for goods that were already shipped.
 */
#[Group('database')]
class AdminOrderCancelTest extends DatabaseWebTestCase
{
    private User $admin;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->createUser('admin@example.com', ['ROLE_ADMIN']);
        $this->product = $this->createProduct(); // stock 10
        $this->client->loginUser($this->admin);
    }

    public function testCancellingAPendingOrderRestocksIt(): void
    {
        $order = $this->createReservedOrder(3, OrderStatus::Pending); // stock 7

        $this->changeStatus($order, 'cancelled');

        $this->assertSame(10, $this->stock());
        $this->assertSame('cancelled', $this->statusOf($order));
        $this->assertAnySelectorTextContains('.alert', 'articles remis en stock');
    }

    public function testCancellingAPaidOrderRestocksIt(): void
    {
        $order = $this->createReservedOrder(3, OrderStatus::Paid);

        $this->changeStatus($order, 'cancelled');

        $this->assertSame(10, $this->stock());
        $this->assertSame('cancelled', $this->statusOf($order));
    }

    public function testCancellingAShippedOrderDoesNotRestockIt(): void
    {
        $order = $this->createReservedOrder(3, OrderStatus::Shipped);

        $this->changeStatus($order, 'cancelled');

        $this->assertSame(7, $this->stock(), 'Shipped goods have left the warehouse.');
        $this->assertSame('cancelled', $this->statusOf($order));
        $this->assertAnySelectorTextContains('.alert', 'traitez le retour séparément');
    }

    public function testACancelledOrderCannotBeReactivated(): void
    {
        $order = $this->createReservedOrder(3, OrderStatus::Pending);
        static::getContainer()->get(StockReservation::class)->cancel($order); // stock back to 10

        $crawler = $this->client->request('GET', sprintf('/orders/%d/edit', $order->getId()));
        $this->client->submit($crawler->filter('form[name="orders"]')->form(['orders[status]' => 'paid']));

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains('form[name="orders"]', 'ne peut pas être réactivée');
        $this->assertSame('cancelled', $this->statusOf($order));
        $this->assertSame(10, $this->stock());
    }

    public function testEditingOtherFieldsDoesNotTouchTheStock(): void
    {
        $order = $this->createReservedOrder(3, OrderStatus::Pending);

        $this->changeStatus($order, 'paid');

        $this->assertSame(7, $this->stock());
        $this->assertSame('paid', $this->statusOf($order));
    }

    public function testDeletingAnOrderThatHoldsStockRestocksIt(): void
    {
        $order = $this->createReservedOrder(3, OrderStatus::Paid);
        $id = $order->getId();

        $crawler = $this->client->request('GET', sprintf('/orders/%d', $id));
        $this->client->submit($crawler->filter(sprintf('form[action="/orders/%d"]', $id))->form());

        $this->assertResponseRedirects('/orders');
        $this->assertSame(10, $this->stock());
        $this->assertSame(0, $this->countRows('orders'));
    }

    public function testEditingAGuestOrderKeepsItAGuestOrder(): void
    {
        $this->createUser('someone@example.com');
        $order = $this->createReservedOrder(1, OrderStatus::Pending);
        $order->setUser(null);
        $this->em->flush();

        $this->changeStatus($order, 'paid');

        $this->assertNull(
            $this->em->getConnection()->fetchOne('SELECT user_id FROM orders WHERE id = ?', [$order->getId()]),
            'Saving the form must not assign the guest order to a customer.'
        );
    }

    private function changeStatus(Orders $order, string $status): void
    {
        $crawler = $this->client->request('GET', sprintf('/orders/%d/edit', $order->getId()));
        $this->client->submit($crawler->filter('form[name="orders"]')->form(['orders[status]' => $status]));
        $this->assertResponseRedirects('/orders');
        $this->client->followRedirect();
    }

    // An order of $quantity units whose stock was reserved at checkout.
    private function createReservedOrder(int $quantity, OrderStatus $status): Orders
    {
        $order = (new Orders())
            ->setUser($this->admin)
            ->setStatus(OrderStatus::Pending)
            ->setTotal($quantity * $this->product->getPrice())
            ->setCreatedAt(new \DateTimeImmutable());
        $order->addItem((new OrderItems())->setProduct($this->product)->setQuantity($quantity)->setPrice($this->product->getPrice()));
        $this->em->persist($order);
        $this->em->flush();

        static::getContainer()->get(StockReservation::class)->reserve($order);
        $order->setStatus($status);
        $this->em->flush();

        return $order;
    }

    private function stock(): int
    {
        return (int) $this->em->getConnection()->fetchOne('SELECT stock FROM product WHERE id = ?', [$this->product->getId()]);
    }

    private function statusOf(Orders $order): string
    {
        return (string) $this->em->getConnection()->fetchOne('SELECT status FROM orders WHERE id = ?', [$order->getId()]);
    }
}
