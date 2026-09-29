<?php

namespace App\Tests\Controller;

use App\Tests\DatabaseWebTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('database')]
class ProductPageTest extends DatabaseWebTestCase
{
    public function testProductPageShowsTheProduct(): void
    {
        $this->createProduct('Test Phone', 1999);

        $this->client->request('GET', '/product/test-phone');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2.product-title', 'Test Phone');
        $this->assertSelectorTextContains('.product-price', "19,99\u{00A0}$");
        $this->assertSelectorTextContains('.product-actions', '10 in stock');
    }

    public function testUnknownProductReturns404(): void
    {
        $this->client->request('GET', '/product/does-not-exist');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testAddToCartFormAddsTheChosenQuantity(): void
    {
        $this->createProduct('Test Phone');
        $crawler = $this->client->request('GET', '/product/test-phone');

        $this->client->submit($crawler->filter('.add-to-cart-form')->form(['quantity' => 3]));
        $this->assertResponseRedirects('/product/test-phone');
        $this->client->followRedirect();
        $this->assertSelectorTextContains('.alert-success', 'Produit ajouté au panier.');

        $crawler = $this->client->request('GET', '/cart');
        $this->assertSame('3', $crawler->filter('.quantity-input')->attr('value'));
    }

    public function testOutOfStockProductCannotBeAdded(): void
    {
        $product = $this->createProduct('Test Phone');
        $product->setStock(0);
        $this->em->flush();

        $this->client->request('GET', '/product/test-phone');

        $this->assertSelectorNotExists('.add-to-cart-form');
        $this->assertSelectorTextContains('.product-actions', 'Out of stock');
    }

    public function testRelatedProductsComeFromTheSameCategoryOnly(): void
    {
        $this->createProduct('Test Phone');
        $this->createProduct('Other Phone');
        $laptop = $this->createProduct('Some Laptop');
        $laptop->setCategory('laptops');
        $this->em->flush();

        $crawler = $this->client->request('GET', '/product/test-phone');

        $related = $crawler->filter('.related-products');
        $this->assertGreaterThan(0, $related->filter('a[href="/product/other-phone"]')->count());
        $this->assertCount(0, $related->filter('a[href="/product/test-phone"]'), 'The current product is not related to itself.');
        $this->assertCount(0, $related->filter('a[href="/product/some-laptop"]'));
    }

    public function testDescriptionHtmlIsEscaped(): void
    {
        $product = $this->createProduct('Test Phone');
        $product->setDescription("Line one\n<script>alert('xss')</script>");
        $this->em->flush();

        $this->client->request('GET', '/product/test-phone');

        $html = (string) $this->client->getResponse()->getContent();
        $this->assertStringNotContainsString("<script>alert('xss')</script>", $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('Line one<br />', $html);
    }
}
