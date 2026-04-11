<?php

namespace App\Service\Auth;

use App\Entity\UsersAvis\User;
use App\Entity\UsersAvis\UserAuthState;
use App\Repository\UsersAvis\UserAuthStateRepository;

class UserAuthStateService
{
    public function __construct(private UserAuthStateRepository $userAuthStateRepository)
    {
    }

    public function getOrCreate(User $user): UserAuthState
    {
        return $this->userAuthStateRepository->getOrCreateForUser($user);
    }

    public function findByVerificationToken(string $token): ?UserAuthState
    {
        return $this->userAuthStateRepository->findByVerificationToken($token);
    }

    public function findByPasswordResetToken(string $token): ?UserAuthState
    {
        return $this->userAuthStateRepository->findByPasswordResetToken($token);
    }

    public function findUserByOauthIdentity(string $provider, string $providerId): ?User
    {
        $state = $this->userAuthStateRepository->findByOauthIdentity($provider, $providerId);

        return $state?->getUser();
    }

    public function isVerified(User $user): bool
    {
        $state = $this->userAuthStateRepository->findOneByUser($user);

        // Existing accounts created before auth-state migration remain valid.
        return $state?->isVerified() ?? true;
    }

    public function isMfaEnabled(User $user): bool
    {
        $state = $this->userAuthStateRepository->findOneByUser($user);

        return $state?->isMfaEnabled() ?? false;
    }
}
