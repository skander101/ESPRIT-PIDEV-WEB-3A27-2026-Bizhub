<?php

namespace App\Security;

use App\Entity\UsersAvis\User;
use App\Service\Auth\UserAuthStateService;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Prevents login for users with unverified email accounts.
 */
class VerifiedUserChecker implements UserCheckerInterface
{
    public function __construct(private UserAuthStateService $userAuthStateService)
    {
    }

    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$this->userAuthStateService->isVerified($user)) {
            throw new CustomUserMessageAccountStatusException('Please verify your email before logging in.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
    }
}
