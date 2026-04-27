<?php

namespace App\Repository;

use App\Entity\Marketplace\Order;
use App\Entity\UsersAvis\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    /** All orders for a given buyer, newest first */
    public function findByBuyer(User $user): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.user = :user')
            ->setParameter('user', $user)
            ->orderBy('o.order_date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countByBuyer(User $user): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.order_id)')
            ->andWhere('o.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getTotalSpentByBuyer(User $user): float
    {
        return (float) ($this->createQueryBuilder('o')
            ->select('SUM(o.total_price)')
            ->andWhere('o.user = :user')
            ->andWhere('o.status != :refused')
            ->setParameter('user', $user)
            ->setParameter('refused', 'annule')
            ->getQuery()
            ->getSingleScalarResult() ?? 0);
    }
}
