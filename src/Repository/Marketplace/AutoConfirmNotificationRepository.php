<?php

namespace App\Repository\Marketplace;

use App\Entity\Marketplace\AutoConfirmNotification;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AutoConfirmNotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AutoConfirmNotification::class);
    }

    public function findUnreadByInvestisseur(int $investisseurId): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.investisseurId = :id')
            ->andWhere('n.isRead = false')
            ->setParameter('id', $investisseurId)
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
