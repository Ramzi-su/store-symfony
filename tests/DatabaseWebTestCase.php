<?php

namespace App\Tests;

use App\Entity\Product;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Base class for functional tests that need a database: the schema of the
 * test database (suffixed "_test" by config/packages/doctrine.yaml) is
 * dropped and recreated before each test.
 */
#[Group('database')]
abstract class DatabaseWebTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $schemaTool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    // $price in cents.
    protected function createProduct(string $name = 'Test Phone', int $price = 10000): Product
    {
        $product = (new Product())
            ->setName($name)
            ->setDescription('A product used in tests.')
            ->setPrice($price)
            ->setStock(10)
            ->setCategory('phones')
            ->setSlug(strtolower(str_replace(' ', '-', $name)))
            ->setImage('images/product-item1.jpg')
            ->setCreatedAt(new \DateTimeImmutable());
        $this->em->persist($product);
        $this->em->flush();

        return $product;
    }

    protected function createUser(string $email = 'ada@example.com', array $roles = ['ROLE_USER']): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setPassword('not-used-tests-log-in-with-loginUser')
            ->setRoles($roles)
            ->setFirstName('Ada')
            ->setLastName('Lovelace')
            ->setPhoneNumber('+33612345678')
            ->setIsVerified(true)
            ->setCreatedAt(new \DateTimeImmutable());
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    /**
     * Submits the real "Add to Cart" form of the shop page, so the CSRF token is valid.
     */
    protected function addToCartFromShop(Product $product): void
    {
        $crawler = $this->client->request('GET', '/shop');
        $form = $crawler->filter(sprintf('form[action="/cart/add/%d"]', $product->getId()))->form();
        $this->client->submit($form);
        $this->assertResponseRedirects();
    }

    protected function countRows(string $table): int
    {
        return (int) $this->em->getConnection()->fetchOne("SELECT COUNT(*) FROM $table");
    }
}
