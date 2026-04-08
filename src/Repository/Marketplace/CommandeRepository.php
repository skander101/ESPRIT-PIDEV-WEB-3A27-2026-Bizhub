<?php

namespace App\Repository\Marketplace;

use App\Entity\Marketplace\Commande;
use App\Entity\Marketplace\ProduitService;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CommandeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Commande::class);
    }

    /** Commandes d'un client, triées par date DESC */
    public function findByClient(int $clientId): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.idClient = :id')
            ->setParameter('id', $clientId)
            ->orderBy('c.dateCommande', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** Commandes filtrées par statut */
    public function findByStatut(string $statut): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.statut = :s')
            ->setParameter('s', $statut)
            ->orderBy('c.dateCommande', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findEnAttente(): array
    {
        return $this->findByStatut(Commande::STATUT_ATTENTE);
    }

    public function findConfirmees(): array
    {
        return $this->findByStatut(Commande::STATUT_CONFIRMEE);
    }

    /** Compte par statut pour les KPIs admin */
    public function countByStatut(): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('c.statut, COUNT(c.idCommande) as nb')
            ->groupBy('c.statut')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['statut']] = (int) $row['nb'];
        }
        return $result;
    }

    /**
     * Commandes reçues par un investisseur via SQL JOIN direct
     */
    public function findByInvestisseur(int $investisseurId, ?string $statut = null): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT DISTINCT c.id_commande
            FROM commande c
            JOIN commande_ligne cl ON cl.id_commande = c.id_commande
            JOIN produit_service ps ON ps.id_produit = cl.id_produit
            WHERE ps.owner_user_id = :owner
        ';
        $params = ['owner' => $investisseurId];

        if ($statut !== null) {
            $sql .= ' AND c.statut = :statut';
            $params['statut'] = $statut;
        }

        $ids = array_column($conn->fetchAllAssociative($sql, $params), 'id_commande');

        if (empty($ids)) {
            return [];
        }

        return $this->createQueryBuilder('c')
            ->where('c.idCommande IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('c.dateCommande', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function save(Commande $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) $this->getEntityManager()->flush();
    }

    public function remove(Commande $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) $this->getEntityManager()->flush();
    }
}
