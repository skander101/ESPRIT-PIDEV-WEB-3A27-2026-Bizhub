<?php

namespace App\Repository\Investissement;

use App\Entity\Investissement\Deal;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DealRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Deal::class);
    }

    /** Tous les deals d'un acheteur */
    public function findByBuyerId(int $buyerId): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.buyer_id = :bid')
            ->setParameter('bid', $buyerId)
            ->orderBy('d.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** Nombre de deals actifs (paid/signed/completed) pour un acheteur */
    public function countActiveByBuyerId(int $buyerId): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.deal_id)')
            ->andWhere('d.buyer_id = :bid')
            ->andWhere('d.status IN (:statuses)')
            ->setParameter('bid', $buyerId)
            ->setParameter('statuses', ['paid', 'pending_signature', 'signed', 'completed'])
            ->getQuery()
            ->getSingleScalarResult();
    }
}
