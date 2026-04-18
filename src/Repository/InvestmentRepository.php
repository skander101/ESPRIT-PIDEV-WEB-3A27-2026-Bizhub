<?php

namespace App\Repository;

use App\Entity\Investissement\Investment;
use App\Entity\Investissement\Project;
use App\Entity\UsersAvis\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Investment>
 */
class InvestmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
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

    /** Tous les investissements d'un utilisateur (pas de limite) */
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
     * Total mensuel sur les N derniers mois pour un utilisateur.
     * Retourne ['2025-01' => 5000.0, '2025-02' => 8000.0, ...]
     */
    public function getMonthlyTotalByUser(User $user, int $months = 6): array
    {
        $from = (new \DateTime())->modify("-{$months} months")->modify('first day of this month')->setTime(0, 0, 0);

        $rows = $this->createQueryBuilder('i')
            ->select("DATE_FORMAT(i.created_at, '%Y-%m') AS ym, SUM(i.amount) AS total")
            ->andWhere('i.user = :user')
            ->andWhere('i.created_at >= :from')
            ->setParameter('user', $user)
            ->setParameter('from', $from)
            ->groupBy('ym')
            ->orderBy('ym', 'ASC')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['ym']] = (float) $row['total'];
        }
        return $result;
    }

    /** Investissement spécifique pour un projet et un investisseur */
    public function findOneByProjectIdAndBuyerId(int $projectId, int $buyerId): ?object
    {
        return $this->createQueryBuilder('i')
            ->join('i.project', 'p')
            ->join('i.user', 'u')
            ->andWhere('p.project_id = :projectId')
            ->andWhere('u.user_id = :buyerId')
            ->setParameter('projectId', $projectId)
            ->setParameter('buyerId', $buyerId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
