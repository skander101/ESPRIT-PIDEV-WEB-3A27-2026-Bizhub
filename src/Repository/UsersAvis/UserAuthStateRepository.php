<?php

namespace App\Repository\UsersAvis;

use App\Entity\UsersAvis\User;
use App\Entity\UsersAvis\UserAuthState;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserAuthState>
 */
class UserAuthStateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserAuthState::class);
    }

    public function findOneByUser(User $user): ?UserAuthState
    {
        return $this->findOneBy(['user' => $user]);
    }

    public function getOrCreateForUser(User $user): UserAuthState
    {
        $state = $this->findOneByUser($user);
        if ($state instanceof UserAuthState) {
            return $state;
        }

        $state = (new UserAuthState())->setUser($user);
        $this->getEntityManager()->persist($state);

        return $state;
    }

    public function findByVerificationToken(string $token): ?UserAuthState
    {
        return $this->findOneBy(['verification_token' => $token]);
    }

    public function findByPasswordResetToken(string $token): ?UserAuthState
    {
        return $this->findOneBy(['password_reset_token' => $token]);
    }

    public function findByOauthIdentity(string $provider, string $providerId): ?UserAuthState
    {
        return $this->findOneBy([
            'oauth_provider' => $provider,
            'oauth_provider_id' => $providerId,
        ]);
    }
}
