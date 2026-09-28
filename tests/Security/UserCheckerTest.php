<?php

namespace App\Tests\Security;

use App\Entity\User;
use App\Security\UserChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

class UserCheckerTest extends TestCase
{
    public function testUnverifiedUserCannotLogIn(): void
    {
        $user = (new User())->setIsVerified(false);

        $this->expectException(CustomUserMessageAccountStatusException::class);

        (new UserChecker())->checkPreAuth($user);
    }

    public function testVerifiedUserCanLogIn(): void
    {
        $user = (new User())->setIsVerified(true);

        (new UserChecker())->checkPreAuth($user);

        $this->addToAssertionCount(1);
    }
}
