<?php

namespace App\Tests\Order;

use App\Cart\Exception\OutOfStockException;
use App\Entity\OrderItems;
use App\Entity\Orders;
use App\Entity\Product;
use App\Enum\OrderStatus;
use App\Order\StockReservation;
use App\Tests\DatabaseWebTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('database')]
class StockReservationTest extends DatabaseWebTestCase
{
    // Must match STRIPE_WEBHOOK_SECRET in phpunit.xml.dist (a dummy value, not a real Stripe secret).
    private const WEBHOOK_SECRET = 'whsec_test_dummy';

    private StockReservation $reservation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reservation = static::getContainer()->get(StockReservation::class);
    }

    public function testReservingAnOrderTakesItsItemsOutOfStock(): void
    {
        $phone = $this->createProduct('Test Phone');   // stock 10
        $laptop = $this->createProduct('Some Laptop'); // stock 10

        $this->reservation->reserve($this->createOrder([[$phone, 3], [$laptop, 1]]));

        $this->assertSame(7, $this->stockOf($phone));
        $this->assertSame(9, $this->stockOf($laptop));
    }

    public function testTheLastUnitCannotBeReservedTwice(): void
    {
        $phone = $this->createProduct('Test Phone');
        $this->setStock($phone, 1);

        $this->reservation->reserve($this->createOrder([[$phone, 1]]));

        $this->expectException(OutOfStockException::class);
        $this->reservation->reserve($this->createOrder([[$phone, 1]]));
    }

    public function testAFailedReservationLeavesNoPartialStockChange(): void
    {
        $phone = $this->createProduct('Test Phone');
        $laptop = $this->createProduct('Some Laptop');
        $this->setStock($laptop, 1);
        $order = $this->createOrder([[$phone, 2], [$laptop, 5]]);

        // Same transaction handling as CheckoutController::createSession().
        $connection = $this->em->getConnection();
        $connection->beginTransaction();
        try {
            $this->reservation->reserve($order);
            $connection->commit();
            $this->fail('The laptop line should not fit in the stock.');
        } catch (OutOfStockException $e) {
            $connection->rollBack();
            $this->assertSame('Some Laptop', $e->product->getName());
        }

        $this->assertSame(10, $this->stockOf($phone), 'The phone reservation must be rolled back too.');
        $this->assertSame(1, $this->stockOf($laptop));
    }

    public function testCancellingReleasesTheStockOnlyOnce(): void
    {
        $phone = $this->createProduct('Test Phone');
        $order = $this->createOrder([[$phone, 4]]);
        $this->reservation->reserve($order);

        $this->assertTrue($this->reservation->cancel($order));
        $this->assertFalse($this->reservation->cancel($order), 'A second cancel (e.g. webhook after the cancel page) is a no-op.');

        $this->assertSame(10, $this->stockOf($phone));
        $this->assertSame(OrderStatus::Cancelled, $order->getStatus());
    }

    public function testAPaidOrderIsNeverCancelledNorRestocked(): void
    {
        $phone = $this->createProduct('Test Phone');
        $order = $this->createOrder([[$phone, 4]]);
        $this->reservation->reserve($order);
        $order->setStatus(OrderStatus::Paid);
        $this->em->flush();

        $this->assertFalse($this->reservation->cancel($order));

        $this->assertSame(6, $this->stockOf($phone));
        $this->assertSame(OrderStatus::Paid, $order->getStatus());
    }

    public function testExpiredStripeSessionReleasesTheStock(): void
    {
        $phone = $this->createProduct('Test Phone');
        $order = $this->createOrder([[$phone, 4]]);
        $order->setStripeSessionId('cs_test_expired');
        $this->em->flush();
        $this->reservation->reserve($order);

        $payload = json_encode([
            'id' => 'evt_test',
            'object' => 'event',
            'type' => 'checkout.session.expired',
            'data' => ['object' => [
                'id' => 'cs_test_expired',
                'object' => 'checkout.session',
                'payment_status' => 'unpaid',
                'metadata' => ['order_id' => (string) $order->getId()],
            ]],
        ], JSON_THROW_ON_ERROR);
        $timestamp = time();
        $this->client->request('POST', '/checkout/webhook', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => sprintf('t=%d,v1=%s', $timestamp, hash_hmac('sha256', $timestamp . '.' . $payload, self::WEBHOOK_SECRET)),
        ], $payload);

        $this->assertResponseIsSuccessful();
        $this->assertSame(10, $this->stockOf($phone));
        $this->assertSame('cancelled', $this->em->getConnection()->fetchOne('SELECT status FROM orders WHERE id = ?', [$order->getId()]));
    }

    /**
     * @param list<array{Product, int}> $lines
     */
    private function createOrder(array $lines): Orders
    {
        $order = (new Orders())
            ->setStatus(OrderStatus::Pending)
            ->setTotal(0)
            ->setCreatedAt(new \DateTimeImmutable());
        foreach ($lines as [$product, $quantity]) {
            $order->addItem((new OrderItems())->setProduct($product)->setQuantity($quantity)->setPrice($product->getPrice()));
        }
        $this->em->persist($order);
        $this->em->flush();

        return $order;
    }

    private function setStock(Product $product, int $stock): void
    {
        $product->setStock($stock);
        $this->em->flush();
    }

    // Read from the database, not from the (possibly stale) entity in memory.
    private function stockOf(Product $product): int
    {
        return (int) $this->em->getConnection()->fetchOne('SELECT stock FROM product WHERE id = ?', [$product->getId()]);
    }
}
