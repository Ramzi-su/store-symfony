<?php

namespace App\Tests\Controller;

use App\Tests\DatabaseWebTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('database')]
class ShopSearchTest extends DatabaseWebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createProduct('Galaxy S24');
        $this->createProduct('Pixel 8');
    }

    public function testSearchMatchesPartOfTheNameIgnoringCase(): void
    {
        $crawler = $this->client->request('GET', '/shop?q=galaxy');

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('a[href="/product/galaxy-s24"]'));
        $this->assertCount(0, $crawler->filter('a[href="/product/pixel-8"]'));
    }

    public function testSqlWildcardsTypedByTheVisitorAreMatchedLiterally(): void
    {
        $crawler = $this->client->request('GET', '/shop?q=%25');

        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $crawler->filter('a[href^="/product/"]'), '"%" must not match every product.');
    }

    public function testHeaderSearchFormTargetsTheShop(): void
    {
        $crawler = $this->client->request('GET', '/');

        $this->assertSame('/shop', $crawler->filter('header form[role="search"]')->attr('action'));
        $this->assertSame('q', $crawler->filter('header form[role="search"] input[type="search"]')->attr('name'));
    }
}
