<?php

namespace App\Repository\Marketplace;

use App\Entity\Marketplace\Commande;
use App\Entity\Marketplace\Facture;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Facture>
 */
class FactureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Facture::class);
    }

    public function findOneByCommande(Commande $commande): ?Facture
    {
        return $this->findOneBy(['commande' => $commande]);
    }

    public function save(Facture $facture, bool $flush = true): void
    {
        $this->getEntityManager()->persist($facture);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
