<?php

namespace App\Repository\Marketplace;

use App\Entity\Marketplace\CommandeLigne;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CommandeLigneRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommandeLigne::class);
    }

    /**
     * IDs des produits commandés par un utilisateur (historique personnel).
     * Utilisé pour personnaliser les recommandations.
     *
     * @return int[]
     */
    public function findProductIdsByUser(int $userId): array
    {
        $rows = $this->createQueryBuilder('cl')
            ->select('DISTINCT cl.idProduit')
            ->join('cl.commande', 'c')
            ->where('c.idClient = :uid')
            ->setParameter('uid', $userId)
            ->getQuery()
            ->getResult();

        return array_column($rows, 'idProduit');
    }

    /**
     * IDs des produits les plus populaires, en excluant une liste.
     * Utilisé comme fallback quand les suggestions par catégorie sont insuffisantes.
     *
     * @param int[] $excludeIds
     * @return int[]
     */
    public function findPopularProductIds(int $limit = 8, array $excludeIds = []): array
    {
        $qb = $this->createQueryBuilder('cl')
            ->select('cl.idProduit, SUM(cl.quantite) AS total')
            ->groupBy('cl.idProduit')
            ->orderBy('total', 'DESC')
            ->setMaxResults($limit);

        if (!empty($excludeIds)) {
            $qb->where('cl.idProduit NOT IN (:ids)')
               ->setParameter('ids', $excludeIds);
        }

        $rows = $qb->getQuery()->getResult();
        return array_column($rows, 'idProduit');
    }

    /**
     * Top N produits les plus commandés, triés par quantité totale vendue.
     * Joint avec produit_service pour récupérer le nom du produit.
     *
     * @return array<int, array{idProduit: int, nomProduit: string, totalQuantite: int, totalRevenue: string, nbCommandes: int}>
     */
    public function findTopProductsByFrequency(int $limit = 5): array
    {
        return $this->createQueryBuilder('cl')
            ->select(
                'cl.idProduit',
                'ps.nom AS nomProduit',
                'ps.categorie AS categorie',
                'SUM(cl.quantite) AS totalQuantite',
                'SUM(cl.quantite * cl.prixHtUnitaire) AS totalRevenue',
                'COUNT(DISTINCT cl.commande) AS nbCommandes'
            )
            ->join(
                \App\Entity\Marketplace\ProduitService::class,
                'ps',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'ps.idProduit = cl.idProduit'
            )
            ->groupBy('cl.idProduit', 'ps.nom', 'ps.categorie')
            ->orderBy('totalQuantite', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Top N produits par chiffre d'affaires généré (montant total HT).
     *
     * @return array<int, array{idProduit: int, nomProduit: string, totalRevenue: string, totalQuantite: int}>
     */
    public function findTopProductsByRevenue(int $limit = 5): array
    {
        return $this->createQueryBuilder('cl')
            ->select(
                'cl.idProduit',
                'ps.nom AS nomProduit',
                'ps.categorie AS categorie',
                'SUM(cl.quantite * cl.prixHtUnitaire) AS totalRevenue',
                'SUM(cl.quantite) AS totalQuantite',
                'COUNT(DISTINCT cl.commande) AS nbCommandes'
            )
            ->join(
                \App\Entity\Marketplace\ProduitService::class,
                'ps',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'ps.idProduit = cl.idProduit'
            )
            ->groupBy('cl.idProduit', 'ps.nom', 'ps.categorie')
            ->orderBy('totalRevenue', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
