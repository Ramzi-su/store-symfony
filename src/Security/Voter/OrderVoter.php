<?php

namespace App\Security\Voter;

use App\Entity\Orders;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Decides who may see a given order: its owner, or an administrator.
 *
 * Symfony asks every voter when isGranted('ORDER_VIEW', $order) is called
 * (#[IsGranted], is_granted() in Twig...). Keeping the rule here means the
 * controller, the templates and any future API share one definition.
 *
 * @extends Voter<string, Orders>
 */
class OrderVoter extends Voter
{
    public const VIEW = 'ORDER_VIEW';

    public function __construct(private readonly Security $security)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::VIEW && $subject instanceof Orders;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        // Compared by id: guest orders have no user, and never match.
        return $subject->getUser()?->getId() === $user->getId();
    }
}
