<?php

namespace App\Tests\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * An invalid registration must answer 422 and tell the visitor why.
 */
class RegistrationFormTest extends WebTestCase
{
    #[DataProvider('invalidPasswords')]
    public function testPasswordErrorsAreShown(string $first, string $second, string $expectedError): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/register');

        $form = $crawler->filter('form[name="registration_form"]')->form([
            'registration_form[firstName]' => 'Ada',
            'registration_form[lastName]' => 'Lovelace',
            'registration_form[email]' => 'ada@example.com',
            'registration_form[phoneNumber]' => '+33612345678',
            'registration_form[plainPassword][first]' => $first,
            'registration_form[plainPassword][second]' => $second,
        ]);
        $form['registration_form[agreeTerms]']->tick();
        // Stateless CSRF (Symfony 7.2+) accepts same-origin requests.
        $client->submit($form, [], ['HTTP_ORIGIN' => 'http://localhost']);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains('.card-body', $expectedError);
    }

    public static function invalidPasswords(): iterable
    {
        yield 'too short' => ['short', 'short', 'at least 8 characters'];
        yield 'mismatch' => ['a-long-password-1', 'a-long-password-2', 'The password fields must match.'];
    }
}
