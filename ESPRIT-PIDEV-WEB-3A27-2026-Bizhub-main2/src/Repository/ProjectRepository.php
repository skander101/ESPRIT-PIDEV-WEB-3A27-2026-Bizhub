<?php

namespace App\Repository;

use App\Entity\Investissement\Investment;
use App\Entity\Investissement\Project;
use App\Entity\UsersAvis\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Project>
 */
class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    /**
     * Projets visibles dans la vue "Explorer" (investisseur).
     * Exclut les brouillons — ils sont privés à la startup.
     */
    public function findAllWithInvestments(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.status != :brouillon')
            ->setParameter('brouillon', Project::STATUS_BROUILLON)
            ->orderBy('p.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.status = :status')
            ->setParameter('status', $status)
            ->orderBy('p.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.project_id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Recherche filtrée des projets.
     * $filters peut contenir : q, secteur, statut, budget_min, budget_max
     */
    public function search(array $filters): array
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.status != :brouillon')
            ->setParameter('brouillon', Project::STATUS_BROUILLON)
            ->orderBy('p.created_at', 'DESC');

        // Mot-clé dans le titre ou la description
        if (!empty($filters['q'])) {
            $qb->andWhere('p.title LIKE :q OR p.description LIKE :q')
               ->setParameter('q', '%' . $filters['q'] . '%');
        }

        // Filtrer par secteur
        if (!empty($filters['secteur'])) {
            $qb->andWhere('p.secteur = :secteur')
               ->setParameter('secteur', $filters['secteur']);
        }

        // Filtrer par statut
        if (!empty($filters['statut'])) {
            $qb->andWhere('p.status = :statut')
               ->setParameter('statut', $filters['statut']);
        }

        // Budget minimum
        if (!empty($filters['budget_min'])) {
            $qb->andWhere('p.required_budget >= :bmin')
               ->setParameter('bmin', (float) $filters['budget_min']);
        }

        // Budget maximum
        if (!empty($filters['budget_max'])) {
            $qb->andWhere('p.required_budget <= :bmax')
               ->setParameter('bmax', (float) $filters['budget_max']);
        }

        return $qb->getQuery()->getResult();
    }

    public function getTotalBudgetRequired(): float
    {
        $result = $this->createQueryBuilder('p')
            ->select('SUM(p.required_budget)')
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? (float) $result : 0.0;
    }

    // ── Méthodes dashboard startup ───────────────────────────────────────────

    /** Tous les projets d'un utilisateur startup */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.user = :user')
            ->setParameter('user', $user)
            ->orderBy('p.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** Répartition par statut pour un utilisateur */
    public function countByStatusForUser(User $user): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('p.status, COUNT(p.project_id) as total')
            ->andWhere('p.user = :user')
            ->setParameter('user', $user)
            ->groupBy('p.status')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['status']] = (int) $row['total'];
        }
        return $result;
    }

    /** Total budget demandé par un utilisateur */
    public function getTotalBudgetByUser(User $user): float
    {
        $result = $this->createQueryBuilder('p')
            ->select('SUM(p.required_budget)')
            ->andWhere('p.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
        return $result ? (float) $result : 0.0;
    }

    /**
     * Répartition de TOUS les projets par secteur.
     * Retourne : ['tech' => 5, 'fintech' => 3, ...]
     */
    public function countBySecteur(): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('p.secteur, COUNT(p.project_id) as total')
            ->groupBy('p.secteur')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($rows as $row) {
            $key = $row['secteur'] ?? 'autre';
            $result[$key] = (int) $row['total'];
        }
        return $result;
    }

    /**
     * Open projects that the given investor has NOT yet invested in.
     * Used by the matching engine — returns up to 20 candidates.
     */
    public function findOpenForMatching(User $investor): array
    {
        // Subquery: project IDs already invested in by this investor
        $sub = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(i.project)')
            ->from(Investment::class, 'i')
            ->andWhere('i.user = :investor');

        $qb = $this->createQueryBuilder('p');

        return $qb
            ->andWhere('p.status = :status')
            ->andWhere($qb->expr()->notIn('p.project_id', $sub->getDQL()))
            ->setParameter('status', Project::STATUS_EN_COURS)
            ->setParameter('investor', $investor)
            ->orderBy('p.created_at', 'DESC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();
    }

    /** Projets en cours pour l'investisseur (ouverts aux investissements) */
    public function findEnCours(int $limit = 4): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.status = :status')
            ->setParameter('status', Project::STATUS_EN_COURS)
            ->orderBy('p.created_at', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
