<?php

namespace App\Repository;

use App\Entity\Investissement\Deal;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Deal>
 */
class DealRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Deal::class);
    }

    public function findByBuyerId(int $buyerId): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.buyer_id = :buyerId')
            ->setParameter('buyerId', $buyerId)
            ->orderBy('d.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countActiveByBuyerId(int $buyerId): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.deal_id)')
            ->andWhere('d.buyer_id = :buyerId')
            ->andWhere('d.status NOT IN (:closed)')
            ->setParameter('buyerId', $buyerId)
            ->setParameter('closed', [Deal::STATUS_COMPLETED, Deal::STATUS_CANCELLED])
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findByProjectId(int $projectId): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.project_id = :projectId')
            ->setParameter('projectId', $projectId)
            ->orderBy('d.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
