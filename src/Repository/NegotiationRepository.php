<?php

namespace App\Repository;

use App\Entity\Investissement\Negotiation;
use App\Entity\Investissement\Project;
use App\Entity\UsersAvis\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Negotiation>
 */
class NegotiationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Negotiation::class);
    }

    public function findByProject(Project $project): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.project = :project')
            ->setParameter('project', $project)
            ->orderBy('n.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByInvestor(User $investor): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.investor = :investor')
            ->setParameter('investor', $investor)
            ->orderBy('n.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByStartup(User $startup): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.startup = :startup')
            ->setParameter('startup', $startup)
            ->orderBy('n.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countOpenByInvestor(User $investor): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.negotiation_id)')
            ->andWhere('n.investor = :investor')
            ->andWhere('n.status = :status')
            ->setParameter('investor', $investor)
            ->setParameter('status', Negotiation::STATUS_OPEN)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findOneByProjectAndInvestor(Project $project, User $investor): ?Negotiation
    {
        $cacheKey = sprintf(
            'negotiation_project_%s_investor_%s',
            (string) $project->getProject_id(),
            (string) $investor->getUserId()
        );

        return $this->createQueryBuilder('n')
            ->andWhere('n.project = :project')
            ->andWhere('n.investor = :investor')
            ->setParameter('project', $project)
            ->setParameter('investor', $investor)
            ->setMaxResults(1)
            ->getQuery()
            ->enableResultCache(3600, $cacheKey)
            ->getOneOrNullResult();
    }

    /**
     * Batch-fetch negotiations for a given investor across many projects.
     *
     * @param Project[] $projects
     *
     * @return array<int, Negotiation> Map: project_id => negotiation
     */
    public function findMapByProjectsAndInvestor(array $projects, User $investor): array
    {
        $projects = array_values(array_filter($projects, static fn ($p) => $p instanceof Project));
        if ($projects === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('n')
            ->andWhere('n.investor = :investor')
            ->andWhere('n.project IN (:projects)')
            ->setParameter('investor', $investor)
            ->setParameter('projects', $projects)
            ->orderBy('n.created_at', 'DESC')
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($rows as $negotiation) {
            if (!$negotiation instanceof Negotiation) {
                continue;
            }
            $projectId = $negotiation->getProject()?->getProject_id();
            if (!$projectId) {
                continue;
            }
            // Keep the latest negotiation per project (ORDER BY created_at DESC)
            if (!isset($map[$projectId])) {
                $map[$projectId] = $negotiation;
            }
        }

        return $map;
    }

    /**
     * Batch-fetch negotiations for a given project across many investors.
     *
     * @param User[] $investors
     *
     * @return array<int, Negotiation> Map: investor_user_id => negotiation
     */
    public function findMapByProjectAndInvestors(Project $project, array $investors): array
    {
        $investors = array_values(array_filter($investors, static fn ($u) => $u instanceof User));
        if ($investors === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('n')
            ->andWhere('n.project = :project')
            ->andWhere('n.investor IN (:investors)')
            ->setParameter('project', $project)
            ->setParameter('investors', $investors)
            ->orderBy('n.created_at', 'DESC')
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($rows as $negotiation) {
            if (!$negotiation instanceof Negotiation) {
                continue;
            }
            $investorId = $negotiation->getInvestor()?->getUserId();
            if (!$investorId) {
                continue;
            }
            // Keep the latest negotiation per investor (ORDER BY created_at DESC)
            if (!isset($map[$investorId])) {
                $map[$investorId] = $negotiation;
            }
        }

        return $map;
    }
}
