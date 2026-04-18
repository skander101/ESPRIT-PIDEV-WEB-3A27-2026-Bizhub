<?php

namespace App\Repository\Marketplace;

use App\Entity\Marketplace\Commande;
use App\Entity\Marketplace\CommandeStatusHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CommandeStatusHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommandeStatusHistory::class);
    }

    /** Retourne l'historique d'une commande, du plus récent au plus ancien. */
    public function findByCommande(Commande $commande): array
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.commande = :commande')
            ->setParameter('commande', $commande)
            ->orderBy('h.changedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function save(CommandeStatusHistory $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
