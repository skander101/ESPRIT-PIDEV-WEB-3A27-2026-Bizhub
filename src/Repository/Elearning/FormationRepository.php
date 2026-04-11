<?php

namespace App\Repository\Elearning;

use App\Entity\Elearning\Formation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class FormationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Formation::class);
    }

    public function findAllOrderedByStartDate(): array
    {
        return $this->createQueryBuilder('f')
            ->orderBy('f.start_date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOrderedByStartDateWithSearch(string $search): array
    {
        $qb = $this->createQueryBuilder('f')
            ->orderBy('f.start_date', 'DESC');

        if ($search !== '') {
            $qb->andWhere('f.title LIKE :search OR f.lieu LIKE :search OR f.description LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        return $qb->getQuery()->getResult();
    }
}
