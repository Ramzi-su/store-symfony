<?php

namespace App\Tests\Controller;

use App\Tests\DatabaseWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DomCrawler\Crawler;

#[Group('database')]
class ShopFiltersTest extends DatabaseWebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createProduct('Cheap Phone', 19900);
        $this->createProduct('Mid Phone', 49900);
        $this->createProduct('Sold Out Phone', 79900)->setStock(0);
        $this->createProduct('Some Watch', 29900)->setCategory('watches');
        $this->em->flush();
    }

    public function testCategoryFilter(): void
    {
        $this->assertSame(['Some Watch'], $this->names($this->client->request('GET', '/shop?category=watches')));
        $this->assertSelectorTextContains('h1', 'Montres');
    }

    public function testPriceRangeInDollars(): void
    {
        $names = $this->names($this->client->request('GET', '/shop?min=250&max=600'));

        $this->assertEqualsCanonicalizing(['Mid Phone', 'Some Watch'], $names);
    }

    public function testMinAboveMaxIsSwappedInsteadOfReturningNothing(): void
    {
        $names = $this->names($this->client->request('GET', '/shop?min=600&max=250'));

        $this->assertEqualsCanonicalizing(['Mid Phone', 'Some Watch'], $names);
    }

    public function testInStockOnly(): void
    {
        $this->assertNotContains('Sold Out Phone', $this->names($this->client->request('GET', '/shop?stock=1')));
    }

    public function testSortByPrice(): void
    {
        $this->assertSame(
            ['Cheap Phone', 'Some Watch', 'Mid Phone', 'Sold Out Phone'],
            $this->names($this->client->request('GET', '/shop?tri=prix-asc'))
        );
        $this->assertSame(
            ['Sold Out Phone', 'Mid Phone', 'Some Watch', 'Cheap Phone'],
            $this->names($this->client->request('GET', '/shop?tri=prix-desc'))
        );
    }

    #[DataProvider('abusiveQueries')]
    public function testInvalidParametersFallBackToNoFilter(string $query): void
    {
        $crawler = $this->client->request('GET', '/shop?' . $query);

        $this->assertResponseIsSuccessful();
        $this->assertCount(4, $this->names($crawler));
    }

    public static function abusiveQueries(): iterable
    {
        yield 'text price' => ['min=abc&max=xyz'];
        yield 'negative price' => ['min=-50'];
        yield 'unknown sort' => ['tri=p.stock'];
        yield 'paginator sort injection' => ['sort=p.stock&direction=desc'];
        yield 'paginator filter injection' => ['filterField=p.name&filterValue=Mid'];
        yield 'page below 1' => ['page=-3'];
    }

    public function testPaginationKeepsTheFilters(): void
    {
        for ($i = 1; $i <= 12; ++$i) {
            $this->createProduct('Extra Phone ' . $i, 10000);
        }

        $crawler = $this->client->request('GET', '/shop?category=phones&tri=prix-asc');

        $this->assertCount(12, $this->names($crawler));
        $next = $crawler->filter('.ms-pagination a[rel="next"]')->attr('href');
        $this->assertStringContainsString('category=phones', $next);
        $this->assertStringContainsString('tri=prix-asc', $next);
        $this->assertStringContainsString('page=2', $next);

        $this->assertCount(3, $this->names($this->client->request('GET', $next)));
    }

    public function testTemplateFiltersThatDidNotExistAreGone(): void
    {
        $this->client->request('GET', '/shop');

        $this->assertSelectorTextNotContains('main', 'Samsung');
        $this->assertSelectorTextNotContains('main', 'Brands');
    }

    /**
     * @return list<string>
     */
    private function names(Crawler $crawler): array
    {
        return $crawler->filter('.ms-tile__name a')->each(fn (Crawler $a) => $a->text());
    }
}
