<?php

namespace App\Repository\Elearning;

use App\Entity\Elearning\Formation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Formation>
 */
class FormationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Formation::class);
    }

    /**
     * @return Formation[]
     */
    public function findAllOrderedByStartDate(): array
    {
        return $this->createQueryBuilder('f')
            ->orderBy('f.start_date', 'DESC')
            ->addOrderBy('f.formation_id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Formations triées par date, filtrées par texte (titre, description, lieu, formateur).
     *
     * @return Formation[]
     */
    public function findOrderedByStartDateWithSearch(?string $search): array
    {
        $qb = $this->createQueryBuilder('f')
            ->leftJoin('f.user', 'trainer')
            ->addSelect('trainer')
            ->orderBy('f.start_date', 'DESC')
            ->addOrderBy('f.formation_id', 'DESC');

        $search = $search !== null ? trim($search) : '';
        if ($search !== '') {
            $q = '%'.mb_strtolower($search).'%';
            $qb->andWhere($qb->expr()->orX(
                'LOWER(f.title) LIKE :q',
                'LOWER(f.description) LIKE :q',
                'LOWER(f.lieu) LIKE :q',
                'LOWER(trainer.full_name) LIKE :q',
                'LOWER(trainer.email) LIKE :q',
            ))
                ->setParameter('q', $q);
        }

        return $qb->getQuery()->getResult();
    }
}
