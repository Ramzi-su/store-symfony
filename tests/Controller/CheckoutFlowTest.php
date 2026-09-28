<?php

namespace App\Tests\Controller;

use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Needs a database: the schema of the test database (suffixed "_test" by
 * config/packages/doctrine.yaml) is dropped and recreated before each test.
 *
 * @group database
 */
class CheckoutFlowTest extends WebTestCase
{
    private KernelBrowser $client;
    private Product $product;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $schemaTool = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $this->product = (new Product())
            ->setName('Test Phone')
            ->setDescription('A phone used in tests.')
            ->setPrice('100')
            ->setStock(10)
            ->setCategory('phones')
            ->setSlug('test-phone')
            ->setImage('images/product-item1.jpg')
            ->setCreatedAt(new \DateTimeImmutable());
        $em->persist($this->product);
        $em->flush();
    }

    public function testGuestCartIsShownOnCheckoutPage(): void
    {
        $this->addProductToCartAsGuest();

        $this->client->request('GET', '/checkout');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Test Phone');
    }

    public function testInvalidCheckoutDetailsAreRejectedWithoutCreatingAnOrder(): void
    {
        $this->addProductToCartAsGuest();
        $crawler = $this->client->request('GET', '/checkout');

        $form = $crawler->filter('#checkout-form')->form([
            'firstName' => 'Ada',
            'lastName' => 'Lovelace',
            'email' => 'not-an-email',
            'phone' => '+33612345678',
            'streetAddress' => '1 rue de la Paix',
            'city' => 'Paris',
            'postcode' => '75002',
            'country' => 'US',
        ]);
        $this->client->submit($form);

        $this->assertResponseRedirects('/checkout');
        $this->client->followRedirect();
        $this->assertSelectorTextContains('.alert', 'email');
        $this->assertSame(0, $this->countOrders());
    }

    public function testCheckoutWithoutCsrfTokenIsRejected(): void
    {
        $this->addProductToCartAsGuest();

        $this->client->request('POST', '/checkout/create-session', ['firstName' => 'Ada']);

        $this->assertResponseRedirects('/checkout');
        $this->assertSame(0, $this->countOrders());
    }

    private function addProductToCartAsGuest(): void
    {
        // Use the real "Add to Cart" form of the shop page, so the CSRF token is valid.
        $crawler = $this->client->request('GET', '/shop');
        $form = $crawler->filter(sprintf('form[action="/cart/add/%d"]', $this->product->getId()))->form();
        $this->client->submit($form);
        $this->assertResponseRedirects();
    }

    private function countOrders(): int
    {
        return (int) static::getContainer()->get(EntityManagerInterface::class)
            ->getConnection()->fetchOne('SELECT COUNT(*) FROM orders');
    }
}
