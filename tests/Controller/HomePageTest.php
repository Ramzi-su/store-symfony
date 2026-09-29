<?php

namespace App\Tests\Controller;

use App\Tests\DatabaseWebTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('database')]
class HomePageTest extends DatabaseWebTestCase
{
    public function testHomeShowsRealProductsAndCategories(): void
    {
        // Used to be empty: the page looked for hard-coded "mobile" and "watch" categories.
        $this->createProduct('Test Phone');
        $watch = $this->createProduct('Test Watch');
        $watch->setCategory('watches');
        $this->em->flush();

        $crawler = $this->client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertGreaterThan(0, $crawler->filter('.ms-grid a[href="/product/test-phone"]')->count());
        $this->assertGreaterThan(0, $crawler->filter('.ms-grid a[href="/product/test-watch"]')->count());
        $this->assertSelectorTextContains('a.ms-category[href="/shop?category=phones"]', 'Téléphones');
        $this->assertSelectorTextContains('a.ms-category[href="/shop?category=watches"]', 'Montres');
    }

    public function testFeaturedProductPrefersASaleItemInStock(): void
    {
        $this->createProduct('Plain Phone');
        $sale = $this->createProduct('Sale Phone');
        $sale->setIsSale(true);
        $soldOut = $this->createProduct('Sold Out Sale Phone');
        $soldOut->setIsSale(true)->setStock(0);
        $this->em->flush();

        $this->client->request('GET', '/');

        $this->assertSelectorTextContains('.ms-hero__feature', 'Sale Phone');
        $this->assertSelectorTextNotContains('.ms-hero__feature', 'Sold Out');
    }

    public function testAdminCanSeeTheStorefront(): void
    {
        // Used to redirect admins to /admin, so they could never see their own shop.
        $this->client->loginUser($this->createUser('admin@example.com', ['ROLE_ADMIN']));

        $this->client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('header a[href="/admin"]');
    }
}
