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

    /**
     * Produits de la même catégorie, en excluant un produit donné.
     * Utilisé pour les recommandations "Produits similaires".
     */
    public function findByCategorieSauf(string $categorie, int $excludeId, int $limit = 4): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.categorie = :cat')
            ->andWhere('p.idProduit != :exclude')
            ->andWhere('p.disponible = true')
            ->setParameter('cat', $categorie)
            ->setParameter('exclude', $excludeId)
            ->orderBy('p.nom', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Produits disponibles en excluant une liste d'IDs (fallback popularité).
     *
     * @param int[] $excludeIds
     */
    public function findDisponiblesExcluant(array $excludeIds, int $limit = 4): array
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.disponible = true')
            ->orderBy('p.nom', 'ASC')
            ->setMaxResults($limit);

        if (!empty($excludeIds)) {
            $qb->andWhere('p.idProduit NOT IN (:ids)')
               ->setParameter('ids', $excludeIds);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Produits disponibles appartenant à au moins une des catégories données,
     * en excluant une liste d'IDs (suggestions panier cross-category).
     *
     * @param string[] $categories
     * @param int[]    $excludeIds
     */
    public function findByCategoriesExcluant(array $categories, array $excludeIds, int $limit = 4): array
    {
        if (empty($categories)) {
            return $this->findDisponiblesExcluant($excludeIds, $limit);
        }

        $qb = $this->createQueryBuilder('p')
            ->where('p.categorie IN (:cats)')
            ->andWhere('p.disponible = true')
            ->setParameter('cats', $categories)
            ->orderBy('p.nom', 'ASC')
            ->setMaxResults($limit);

        if (!empty($excludeIds)) {
            $qb->andWhere('p.idProduit NOT IN (:ids)')
               ->setParameter('ids', $excludeIds);
        }

        return $qb->getQuery()->getResult();
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
