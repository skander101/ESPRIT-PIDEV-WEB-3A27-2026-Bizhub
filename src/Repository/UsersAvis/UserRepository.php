<?php

namespace App\Repository\UsersAvis;

use App\Entity\UsersAvis\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.email = :email')
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findActive(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.is_active = :active')
            ->setParameter('active', true)
            ->orderBy('u.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByRole(string $userType): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.user_type = :userType')
            ->setParameter('userType', $userType)
            ->orderBy('u.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findAdmins(): array
    {
        return $this->findByRole('admin');
    }

    public function findAllRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('u')
            ->orderBy('u.created_at', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.user_id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countActive(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.user_id)')
            ->andWhere('u.is_active = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
