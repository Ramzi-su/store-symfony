<?php

namespace App\Tests\Cart;

use App\Entity\Product;
use App\Tests\DatabaseWebTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('database')]
class CartFlowTest extends DatabaseWebTestCase
{
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->product = $this->createProduct();
    }

    public function testGuestAddingTheSameProductTwiceIncreasesTheQuantity(): void
    {
        $this->addToCartFromShop($this->product);
        $this->addToCartFromShop($this->product);

        $crawler = $this->client->request('GET', '/cart');

        $this->assertResponseIsSuccessful();
        $this->assertSame('2', $crawler->filter('.quantity-input')->attr('value'));
        // 2 x $100.00 + $10.00 shipping + $20.00 tax
        $this->assertSelectorTextContains('.cart-summary', "230,00\u{00A0}$");
    }

    public function testLoggedInUserCanAddToCart(): void
    {
        // Used to crash: CartItem.cart is NOT NULL and no Cart row was ever created.
        $this->client->loginUser($this->createUser());

        $this->addToCartFromShop($this->product);

        $this->assertSame(1, $this->countRows('cart'));
        $this->assertSame(1, $this->countRows('cart_item'));
        $this->client->request('GET', '/cart');
        $this->assertSelectorTextContains('body', 'Test Phone');
    }

    public function testLoggedInUserReusesTheirCart(): void
    {
        $this->client->loginUser($this->createUser());

        $this->addToCartFromShop($this->product);
        $this->addToCartFromShop($this->createProduct('Other Phone'));

        $this->assertSame(1, $this->countRows('cart'), 'One Cart per user, not one per item.');
        $this->assertSame(2, $this->countRows('cart_item'));
    }

    public function testRemovingALineEmptiesTheCart(): void
    {
        $this->addToCartFromShop($this->product);
        $crawler = $this->client->request('GET', '/cart');

        $this->client->submit($crawler->filter(sprintf('form[action="/cart/remove/%d"]', $this->product->getId()))->form());

        $this->assertResponseRedirects('/cart');
        $this->client->followRedirect();
        $this->assertSelectorTextContains('body', 'Votre panier est vide');
    }
}
