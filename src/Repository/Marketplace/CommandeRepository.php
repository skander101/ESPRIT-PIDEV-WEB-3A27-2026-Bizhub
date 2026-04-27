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
            SELECT DISTINCT c.commande_id
            FROM commande c
            JOIN commande_ligne cl ON cl.commande_id = c.commande_id
            JOIN produit_service ps ON ps.id_produit = cl.id_produit
            WHERE ps.owner_user_id = :owner
        ';
        $params = ['owner' => $investisseurId];

        if ($statut !== null) {
            $sql .= ' AND c.statut = :statut';
            $params['statut'] = $statut;
        }

        $ids = array_column($conn->fetchAllAssociative($sql, $params), 'commande_id');

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

    /**
     * Top N commandes par montant total TTC décroissant.
     * Exclut les commandes annulées et celles sans montant.
     *
     * @return array<int, array{idCommande: int, idClient: int, statut: string, dateCommande: \DateTimeInterface, totalTtc: string}>
     */
    public function findTopByAmount(int $limit = 5): array
    {
        return $this->createQueryBuilder('c')
            ->select(
                'c.idCommande',
                'c.idClient',
                'c.statut',
                'c.dateCommande',
                'c.totalTtc',
                'c.totalHt',
                'c.totalTva'
            )
            ->where('c.statut != :annulee')
            ->andWhere('c.totalTtc IS NOT NULL')
            ->setParameter('annulee', Commande::STATUT_ANNULEE)
            ->orderBy('c.totalTtc', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Chiffre d'affaires mensuel sur les N derniers mois.
     *
     * @return array<int, array{mois: string, total: string, nb: int}>
     */
    public function findMonthlyRevenue(int $months = 6): array
    {
        $since = new \DateTime("-{$months} months");

        $rows = $this->createQueryBuilder('c')
            ->select('c.dateCommande', 'c.totalTtc')
            ->where('c.estPayee = true')
            ->andWhere('c.dateCommande >= :since')
            ->setParameter('since', $since)
            ->orderBy('c.dateCommande', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $grouped = [];
        foreach ($rows as $row) {
            $date = $row['dateCommande'];
            $mois = $date instanceof \DateTimeInterface
                ? $date->format('Y-m')
                : substr((string) $date, 0, 7);
            $grouped[$mois] ??= ['mois' => $mois, 'total' => 0.0, 'nb' => 0];
            $grouped[$mois]['total'] += (float) ($row['totalTtc'] ?? 0);
            $grouped[$mois]['nb']++;
        }

        ksort($grouped);
        return array_values($grouped);
    }

    /**
     * Répartition des commandes par statut avec leur montant cumulé.
     *
     * @return array<int, array{statut: string, nb: int, totalTtc: string}>
     */
    public function findStatsByStatut(): array
    {
        return $this->createQueryBuilder('c')
            ->select(
                'c.statut',
                'COUNT(c.idCommande) AS nb',
                'SUM(c.totalTtc) AS totalTtc'
            )
            ->groupBy('c.statut')
            ->orderBy('nb', 'DESC')
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
