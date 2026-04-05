<?php

namespace App\Repository\UsersAvis;

use App\Entity\UsersAvis\Avis;
use App\Entity\UsersAvis\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Avis>
 */
class AvisRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Avis::class);
    }

    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.user = :user')
            ->setParameter('user', $user)
            ->orderBy('a.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findVerified(): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.is_verified = :verified')
            ->setParameter('verified', true)
            ->orderBy('a.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('a')
            ->orderBy('a.created_at', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findByRating(int $rating): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.rating = :rating')
            ->setParameter('rating', $rating)
            ->orderBy('a.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findAllWithUser(): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.user', 'u')
            ->addSelect('u')
            ->orderBy('a.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.avis_id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countVerified(): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.avis_id)')
            ->andWhere('a.is_verified = :verified')
            ->setParameter('verified', true)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
