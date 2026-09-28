<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CsrfProtectionTest extends WebTestCase
{
    public function testAddToCartIsNotReachableWithGet(): void
    {
        $client = static::createClient();
        $client->request('GET', '/cart/add/1');

        $this->assertResponseStatusCodeSame(405);
    }

    public function testClearCartWithoutTokenIsRejected(): void
    {
        $client = static::createClient();
        $client->request('POST', '/cart/clear');

        $this->assertResponseRedirects('/cart');
        $client->followRedirect();
        $this->assertSelectorTextContains('body', 'Votre session a expiré');
    }

    public function testClearCartWithoutTokenIsRejectedForAjax(): void
    {
        $client = static::createClient();
        $client->xmlHttpRequest('POST', '/cart/clear');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testResendCodeIsNotReachableWithGet(): void
    {
        $client = static::createClient();
        $client->request('GET', '/verify/resend?email=victim@example.com');

        $this->assertResponseStatusCodeSame(405);
    }
}
