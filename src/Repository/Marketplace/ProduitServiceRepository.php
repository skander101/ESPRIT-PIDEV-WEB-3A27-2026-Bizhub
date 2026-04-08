<?php

namespace App\Repository\Marketplace;

use App\Entity\Marketplace\ProduitService;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ProduitServiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProduitService::class);
    }

    /** Tous les produits disponibles triés par nom */
    public function findDisponibles(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.disponible = true')
            ->orderBy('p.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** Recherche texte sur nom, description, catégorie */
    public function search(string $q): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.nom LIKE :q OR p.description LIKE :q OR p.categorie LIKE :q')
            ->andWhere('p.disponible = true')
            ->setParameter('q', '%' . $q . '%')
            ->orderBy('p.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** Filtre par catégorie */
    public function findByCategorie(string $categorie): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.categorie = :cat')
            ->andWhere('p.disponible = true')
            ->setParameter('cat', $categorie)
            ->orderBy('p.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** Liste des catégories distinctes */
    public function findAllCategories(): array
    {
        return $this->createQueryBuilder('p')
            ->select('DISTINCT p.categorie')
            ->where('p.categorie IS NOT NULL')
            ->orderBy('p.categorie', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();
    }

    /** Produits d'un investisseur (owner) */
    public function findByOwner(int $userId): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.ownerUserId = :uid')
            ->setParameter('uid', $userId)
            ->orderBy('p.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function save(ProduitService $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) $this->getEntityManager()->flush();
    }

    public function remove(ProduitService $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) $this->getEntityManager()->flush();
    }
}
