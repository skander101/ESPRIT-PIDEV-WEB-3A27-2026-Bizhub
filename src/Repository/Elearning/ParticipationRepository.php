<?php

namespace App\Repository\Elearning;

use App\Entity\Elearning\Participation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

use App\Entity\Elearning\Formation;
use App\Entity\UsersAvis\User;

class ParticipationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Participation::class);
    }

    public function findByFormationOrdered(Formation $formation): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.formation = :formation')
            ->setParameter('formation', $formation)
            ->orderBy('p.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByUserAndFormation(User $user, Formation $formation): ?Participation
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.user = :user')
            ->andWhere('p.formation = :formation')
            ->setParameter('user', $user)
            ->setParameter('formation', $formation)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
