<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Runs during login, before the password is checked: an account whose phone
 * number has not been verified with the SMS code cannot log in.
 */
class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->getIsVerified()) {
            // Shown on the login page as-is (the "custom user message" part of the exception).
            throw new CustomUserMessageAccountStatusException(
                'Votre compte n’est pas encore vérifié. Saisissez le code reçu par SMS.'
            );
        }
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
    }
}
