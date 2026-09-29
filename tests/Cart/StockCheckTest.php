<?php

namespace App\Tests\Cart;

use App\Entity\Product;
use App\Tests\DatabaseWebTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Stock limits are enforced by the server: these tests post the requests
 * directly, as a user bypassing the quantity inputs of the page would.
 */
#[Group('database')]
class StockCheckTest extends DatabaseWebTestCase
{
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->product = $this->createProduct(); // stock: 10
    }

    public function testAddingMoreThanTheStockIsCapped(): void
    {
        $this->post('/cart/add/' . $this->product->getId(), ['quantity' => 15]);

        $this->assertFlash('Stock limité : seulement 10 article(s) ajouté(s).');
        $this->assertSame(10, $this->quantityInCart());
    }

    public function testAddingWhenTheWholeStockIsAlreadyInTheCart(): void
    {
        $this->post('/cart/add/' . $this->product->getId(), ['quantity' => 10]);
        $this->post('/cart/add/' . $this->product->getId(), ['quantity' => 1]);

        $this->assertFlash('Vous avez déjà tout le stock disponible dans votre panier.');
        $this->assertSame(10, $this->quantityInCart());
    }

    public function testOutOfStockProductCannotBeAdded(): void
    {
        $this->product->setStock(0);
        $this->em->flush();

        $this->post('/cart/add/' . $this->product->getId(), ['quantity' => 1]);

        $this->assertFlash('n’est plus en stock');
        $this->assertSame(0, $this->quantityInCart());
    }

    public function testUpdatingAboveTheStockIsCapped(): void
    {
        $this->post('/cart/add/' . $this->product->getId(), ['quantity' => 2]);

        $this->post('/cart/update/' . $this->product->getId(), ['quantity' => 50]);

        $this->assertFlash('Stock limité : quantité ramenée à 10.');
        $this->assertSame(10, $this->quantityInCart());
    }

    public function testLinesOfTheSameProductShareTheStock(): void
    {
        // Same product in two colors: 6 + 6 would exceed a stock of 10.
        $this->post('/cart/add/' . $this->product->getId(), ['quantity' => 6, 'color' => 'black']);
        $this->post('/cart/add/' . $this->product->getId(), ['quantity' => 6, 'color' => 'white']);

        $this->assertSame(10, $this->quantityInCart());
    }

    public function testCheckoutRefusesACartThatNoLongerFitsTheStock(): void
    {
        $this->post('/cart/add/' . $this->product->getId(), ['quantity' => 5]);
        $crawler = $this->client->request('GET', '/checkout');
        $form = $crawler->filter('#checkout-form')->form([
            'firstName' => 'Ada', 'lastName' => 'Lovelace', 'email' => 'ada@example.com',
            'phone' => '+33612345678', 'streetAddress' => '1 rue de la Paix', 'city' => 'Paris',
            'postcode' => '75002', 'country' => 'US',
        ]);

        // Someone else bought most of the stock in the meantime.
        $this->em->getConnection()->executeStatement('UPDATE product SET stock = 3');
        $this->client->submit($form);

        $this->assertResponseRedirects('/cart');
        $this->assertSame(0, $this->countRows('orders'));
        $this->client->followRedirect();
        $this->assertSelectorTextContains('.alert-danger', 'Stock insuffisant pour « Test Phone » (3 disponible(s))');
    }

    /**
     * Posts to a cart route with a valid CSRF token, taken from the shop page form.
     */
    private function post(string $url, array $data): void
    {
        $token = $this->client->request('GET', '/shop')->filter('form[action^="/cart/add/"] input[name="_token"]')->attr('value');
        $this->client->request('POST', $url, $data + ['_token' => $token]);
        $this->assertResponseRedirects();
    }

    private function quantityInCart(): int
    {
        $crawler = $this->client->request('GET', '/cart');

        return array_sum($crawler->filter('.quantity-input')->each(fn ($input) => (int) $input->attr('value')));
    }

    private function assertFlash(string $message): void
    {
        $this->client->request('GET', '/cart');
        $this->assertAnySelectorTextContains('.alert', $message);
    }
}
