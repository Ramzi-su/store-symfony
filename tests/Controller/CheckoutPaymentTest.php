<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CheckoutPaymentTest extends WebTestCase
{
    // Must match STRIPE_WEBHOOK_SECRET in phpunit.xml.dist (a dummy value, not a real secret).
    private const WEBHOOK_SECRET = 'whsec_test_dummy';

    public function testSuccessPageWithoutCheckoutSessionDoesNotConfirmAnything(): void
    {
        $client = static::createClient();
        $client->request('GET', '/checkout/success');

        $this->assertResponseRedirects();
        $this->assertStringNotContainsString('/checkout/success', (string) $client->getResponse()->headers->get('Location'));
    }

    public function testWebhookRejectsMissingSignature(): void
    {
        $client = static::createClient();
        $client->request('POST', '/checkout/webhook', [], [], ['CONTENT_TYPE' => 'application/json'], $this->payload());

        $this->assertResponseStatusCodeSame(400);
    }

    public function testWebhookRejectsForgedSignature(): void
    {
        $client = static::createClient();
        $payload = $this->payload();
        $client->request('POST', '/checkout/webhook', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $this->sign($payload, 'whsec_attacker_guess'),
        ], $payload);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testWebhookAcceptsCorrectlySignedEvent(): void
    {
        $client = static::createClient();
        // An event type the app ignores, so the test does not need a database.
        $payload = $this->payload('customer.created');
        $client->request('POST', '/checkout/webhook', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $this->sign($payload, self::WEBHOOK_SECRET),
        ], $payload);

        $this->assertResponseIsSuccessful();
    }

    private function payload(string $type = 'checkout.session.completed'): string
    {
        return json_encode([
            'id' => 'evt_test',
            'object' => 'event',
            'type' => $type,
            'data' => ['object' => [
                'id' => 'cs_test',
                'object' => 'checkout.session',
                'payment_status' => 'paid',
                'metadata' => ['order_id' => '1'],
            ]],
        ], JSON_THROW_ON_ERROR);
    }

    // Same format as the Stripe-Signature header: t=<timestamp>,v1=<HMAC-SHA256 of "t.payload">.
    private function sign(string $payload, string $secret): string
    {
        $timestamp = time();

        return sprintf('t=%d,v1=%s', $timestamp, hash_hmac('sha256', $timestamp . '.' . $payload, $secret));
    }
}
