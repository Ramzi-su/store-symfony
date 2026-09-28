<?php

namespace App\Tests\Entity;

use App\Entity\Orders;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class OrdersValidationTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    }

    public function testValidCheckoutDetailsPass(): void
    {
        $violations = $this->validator->validate($this->validOrder(), null, ['checkout']);

        $this->assertCount(0, $violations);
    }

    public function testEmptyOrderReportsEveryRequiredField(): void
    {
        $violations = $this->validator->validate(new Orders(), null, ['checkout']);

        $fields = array_map(fn ($v) => $v->getPropertyPath(), iterator_to_array($violations));
        foreach (['firstName', 'lastName', 'email', 'phoneNumber', 'address', 'city', 'postcode', 'country'] as $required) {
            $this->assertContains($required, $fields);
        }
        $this->assertNotContains('state', $fields, 'State is optional.');
    }

    public function testInvalidEmailAndPhoneAreRejected(): void
    {
        $order = $this->validOrder()->setEmail('not-an-email')->setPhoneNumber('call me <script>');

        $fields = array_map(fn ($v) => $v->getPropertyPath(), iterator_to_array($this->validator->validate($order, null, ['checkout'])));

        $this->assertSame(['email', 'phoneNumber'], $fields);
    }

    public function testCheckoutRulesDoNotApplyOutsideTheCheckoutGroup(): void
    {
        // Orders created before the migration have no customer details and must stay editable by admins.
        $this->assertCount(0, $this->validator->validate(new Orders()));
    }

    private function validOrder(): Orders
    {
        return (new Orders())
            ->setFirstName('Ada')
            ->setLastName('Lovelace')
            ->setEmail('ada@example.com')
            ->setPhoneNumber('+33 6 12 34 56 78')
            ->setAddress('1 rue de la Paix')
            ->setCity('Paris')
            ->setPostcode('75002')
            ->setCountry('France');
    }
}
