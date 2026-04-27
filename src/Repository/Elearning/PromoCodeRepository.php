<?php

declare(strict_types=1);

namespace App\Repository\Elearning;

use App\Entity\Elearning\PromoCode;
use App\Entity\UsersAvis\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PromoCode>
 */
class PromoCodeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PromoCode::class);
    }

    public function existsByCode(string $code): bool
    {
        return null !== $this->findOneBy(['code' => $code]);
    }

    public function findOneByCode(string $code): ?PromoCode
    {
        return $this->findOneBy(['code' => strtoupper(trim($code))]);
    }

    /**
     * @return list<PromoCode>
     */
    public function findAllForAdmin(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.user', 'u')->addSelect('u')
            ->leftJoin('p.participationSource', 'ps')->addSelect('ps')
            ->orderBy('p.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
