<?php

namespace App\Tests\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AccessControlTest extends WebTestCase
{
    #[DataProvider('adminOnlyUrls')]
    public function testAnonymousVisitorIsRedirectedToLogin(string $url): void
    {
        $client = static::createClient();
        $client->request('GET', $url);

        $this->assertResponseRedirects('/login');
    }

    public static function adminOnlyUrls(): iterable
    {
        yield 'orders list' => ['/orders'];
        yield 'order creation' => ['/orders/new'];
        yield 'order edition' => ['/orders/1/edit'];
        yield 'admin dashboard' => ['/admin'];
        yield 'user management' => ['/user/'];
    }
}
