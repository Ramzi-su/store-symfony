<?php

namespace App\Tests\Controller;

use App\Entity\Product;
use App\Tests\DatabaseWebTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('database')]
class CheckoutFlowTest extends DatabaseWebTestCase
{
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->product = $this->createProduct();
    }

    public function testGuestCartIsShownOnCheckoutPage(): void
    {
        $this->addToCartFromShop($this->product);

        $this->client->request('GET', '/checkout');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Test Phone');
    }

    public function testInvalidCheckoutDetailsAreRejectedWithoutCreatingAnOrder(): void
    {
        $this->addToCartFromShop($this->product);
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
        $this->assertSame(0, $this->countRows('orders'));
    }

    public function testCheckoutWithoutCsrfTokenIsRejected(): void
    {
        $this->addToCartFromShop($this->product);

        $this->client->request('POST', '/checkout/create-session', ['firstName' => 'Ada']);

        $this->assertResponseRedirects('/checkout');
        $this->assertSame(0, $this->countRows('orders'));
    }
}
