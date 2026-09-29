<?php

namespace App\Tests\Security;

use App\Entity\Orders;
use App\Entity\User;
use App\Enum\OrderStatus;
use App\Tests\DatabaseWebTestCase;

/**
 * @group database
 */
class OrderAccessTest extends DatabaseWebTestCase
{
    private User $owner;
    private Orders $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = $this->createUser('owner@example.com');
        $this->order = $this->createOrder($this->owner);
    }

    public function testOwnerSeesTheirOrder(): void
    {
        $this->client->loginUser($this->owner);

        $this->client->request('GET', '/account/orders/' . $this->order->getId());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', '$120.00');
        $this->assertSelectorTextContains('body', 'Payée');
        $this->assertSelectorNotExists('a[href$="/edit"]', 'Customers must not see admin actions.');
    }

    public function testAnotherCustomerCannotSeeTheOrderByChangingTheId(): void
    {
        $this->client->loginUser($this->createUser('intruder@example.com'));

        $this->client->request('GET', '/account/orders/' . $this->order->getId());

        $this->assertResponseStatusCodeSame(403);
    }

    public function testGuestOrdersAreNotVisibleToCustomers(): void
    {
        $guestOrder = $this->createOrder(null);
        $this->client->loginUser($this->owner);

        $this->client->request('GET', '/account/orders/' . $guestOrder->getId());

        $this->assertResponseStatusCodeSame(403);
    }

    public function testAnonymousVisitorIsSentToLogin(): void
    {
        $this->client->request('GET', '/account/orders/' . $this->order->getId());

        $this->assertResponseRedirects('/login');
    }

    public function testCustomerOrderListLinksToTheirOrder(): void
    {
        $this->client->loginUser($this->owner);

        $this->client->request('GET', '/account/orders');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists(sprintf('a[href="/account/orders/%d"]', $this->order->getId()));
    }

    public function testAdminSeesAnyOrderAndItsAdminPages(): void
    {
        $this->client->loginUser($this->createUser('admin@example.com', ['ROLE_ADMIN']));

        $this->client->request('GET', '/account/orders/' . $this->order->getId());
        $this->assertResponseIsSuccessful();

        $this->client->request('GET', '/orders/' . $this->order->getId());
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('a[href$="/edit"]');

        // Admin edit form: status enum choices and total in dollars (MoneyType, divisor 100).
        $crawler = $this->client->request('GET', '/orders/' . $this->order->getId() . '/edit');
        $this->assertResponseIsSuccessful();
        $this->assertSame('120.00', $crawler->filter('input[name="orders[total]"]')->attr('value'));
        $this->assertSame(4, $crawler->filter('select[name="orders[status]"] option')->count());
    }

    private function createOrder(?User $user): Orders
    {
        $order = (new Orders())
            ->setUser($user)
            ->setStatus(OrderStatus::Paid)
            ->setSubtotal(10000)
            ->setShippingCost(1000)
            ->setTax(1000)
            ->setTotal(12000)
            ->setCreatedAt(new \DateTimeImmutable());
        $this->em->persist($order);
        $this->em->flush();

        return $order;
    }
}
