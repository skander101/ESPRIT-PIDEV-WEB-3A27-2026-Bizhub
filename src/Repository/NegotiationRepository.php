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
}
