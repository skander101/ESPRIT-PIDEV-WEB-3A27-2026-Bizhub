<?php

namespace App\Repository;

use App\Entity\Investissement\Investment;
use App\Entity\Investissement\Project;
use App\Entity\UsersAvis\User;
use App\Service\Investissement\MoneyHelper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Money\Money;

/**
 * @extends ServiceEntityRepository<Investment>
 */
class InvestmentRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private MoneyHelper $moneyHelper,
    ) {
        parent::__construct($registry, Investment::class);
    }

    public function findByProject(Project $project): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.project = :project')
            ->setParameter('project', $project)
            ->orderBy('i.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getTotalInvestedByProject(Project $project): float
    {
        $result = $this->createQueryBuilder('i')
            ->select('SUM(i.amount)')
            ->andWhere('i.project = :project')
            ->setParameter('project', $project)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? (float) $result : 0.0;
    }

    /**
     * Retourne le total investi sous forme d'objet Money (précision garantie).
     * Utilise des entiers en interne → pas d'erreur de float.
     */
    public function getTotalAsMoneyByProject(Project $project, string $currency = 'TND'): Money
    {
        $investments = $this->findByProject($project);
        return $this->moneyHelper->sumInvestments($investments, $currency);
    }

    /**
     * Retourne le total investi par un utilisateur sous forme d'objet Money.
     */
    public function getTotalAsMoneyByUser(User $user, string $currency = 'TND'): Money
    {
        $investments = $this->findAllByUser($user);
        return $this->moneyHelper->sumInvestments($investments, $currency);
    }

    public function getTotalInvested(): float
    {
        $result = $this->createQueryBuilder('i')
            ->select('SUM(i.amount)')
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? (float) $result : 0.0;
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.investment_id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findAllWithProject(): array
    {
        return $this->createQueryBuilder('i')
            ->orderBy('i.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // ── Méthodes dashboard investisseur ─────────────────────────────────────

    /** Total investi par cet investisseur */
    public function getTotalInvestedByUser(User $user): float
    {
        $result = $this->createQueryBuilder('i')
            ->select('SUM(i.amount)')
            ->andWhere('i.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
        return $result ? (float) $result : 0.0;
    }

    /** Nombre d'investissements de cet investisseur */
    public function countByUser(User $user): int
    {
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.investment_id)')
            ->andWhere('i.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** Nombre de projets distincts dans lesquels il a investi */
    public function countDistinctProjectsByUser(User $user): int
    {
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(DISTINCT i.project)')
            ->andWhere('i.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** Derniers N investissements d'un utilisateur */
    public function findLastByUser(User $user, int $limit = 5): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.user = :user')
            ->setParameter('user', $user)
            ->orderBy('i.created_at', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** Répartition des investissements par statut pour un utilisateur */
    public function countByStatutForUser(User $user): array
    {
        $rows = $this->createQueryBuilder('i')
            ->select('i.statut, COUNT(i.investment_id) as total')
            ->andWhere('i.user = :user')
            ->setParameter('user', $user)
            ->groupBy('i.statut')
            ->getQuery()
            ->getResult();

        // Transformer en tableau associatif ['statut' => count]
        $result = [];
        foreach ($rows as $row) {
            $result[$row['statut'] ?? 'en_attente'] = (int) $row['total'];
        }
        return $result;
    }

    // ── Méthodes dashboard startup ───────────────────────────────────────────

    /** Tous les investissements d'un utilisateur, triés par date */
    public function findAllByUser(User $user): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.user = :user')
            ->setParameter('user', $user)
            ->orderBy('i.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Agrégation mensuelle des investissements d'un utilisateur.
     * Retourne un tableau indexé par 'YYYY-MM' => montant total.
     */
    public function getMonthlyTotalByUser(User $user, int $months = 6): array
    {
        $since = (new \DateTime())->modify("-{$months} months");

        $rows = $this->createQueryBuilder('i')
            ->select('SUBSTRING(i.created_at, 1, 7) AS month, SUM(i.amount) AS total')
            ->andWhere('i.user = :user')
            ->andWhere('i.created_at >= :since')
            ->setParameter('user', $user)
            ->setParameter('since', $since)
            ->groupBy('month')
            ->orderBy('month', 'ASC')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['month']] = (float) $row['total'];
        }
        return $result;
    }

    /**
     * Find the single Investment for a given project+buyer (used for Investment–Deal status sync).
     */
    public function findOneByProjectIdAndBuyerId(int $projectId, int $buyerId): ?Investment
    {
        return $this->createQueryBuilder('i')
            ->andWhere('IDENTITY(i.project) = :pid')
            ->andWhere('IDENTITY(i.user)    = :uid')
            ->setParameter('pid', $projectId)
            ->setParameter('uid', $buyerId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** Derniers N investissements reçus sur une liste de projets */
    public function findLastReceivedByProjects(array $projects, int $limit = 5): array
    {
        if (empty($projects)) {
            return [];
        }
        return $this->createQueryBuilder('i')
            ->andWhere('i.project IN (:projects)')
            ->setParameter('projects', $projects)
            ->orderBy('i.created_at', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** Total investi sur une liste de projets (pour dashboard startup) */
    public function getTotalInvestedByProjects(array $projects): float
    {
        if (empty($projects)) {
            return 0.0;
        }
        $result = $this->createQueryBuilder('i')
            ->select('SUM(i.amount)')
            ->andWhere('i.project IN (:projects)')
            ->setParameter('projects', $projects)
            ->getQuery()
            ->getSingleScalarResult();
        return $result ? (float) $result : 0.0;
    }
}
