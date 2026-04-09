<?php

namespace App\Repository\Marketplace;

use App\Entity\Marketplace\Panier;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PanierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Panier::class);
    }

    /** Tous les articles du panier d'un client */
    public function findByClient(int $clientId): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.idClient = :id')
            ->setParameter('id', $clientId)
            ->orderBy('p.dateAjout', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** Cherche un article précis dans le panier (pour éviter les doublons) */
    public function findItem(int $clientId, int $produitId): ?Panier
    {
        return $this->createQueryBuilder('p')
            ->where('p.idClient = :c AND p.idProduit = :pr')
            ->setParameter('c', $clientId)
            ->setParameter('pr', $produitId)
            ->getQuery()
            ->getOneOrNullResult();
            
    }

    /** Nombre total d'articles dans le panier (pour le badge) */
    public function countByClient(int $clientId): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.idPanier)')
            ->where('p.idClient = :id')
            ->setParameter('id', $clientId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** Vider tout le panier d'un client */
    public function viderPanier(int $clientId): void
    {
        $this->createQueryBuilder('p')
            ->delete()
            ->where('p.idClient = :id')
            ->setParameter('id', $clientId)
            ->getQuery()
            ->execute();
    }

    public function save(Panier $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) $this->getEntityManager()->flush();
    }

    public function remove(Panier $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) $this->getEntityManager()->flush();
    }
}
