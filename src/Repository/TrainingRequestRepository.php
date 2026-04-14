<?php

namespace App\Repository;

use App\Entity\Elearning\Formation;
use App\Entity\Elearning\TrainingRequest;
use App\Entity\UsersAvis\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TrainingRequest>
 */
class TrainingRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TrainingRequest::class);
    }

    public function findOneByUserAndFormation(User $user, Formation $formation): ?TrainingRequest
    {
        return $this->createQueryBuilder('tr')
            ->andWhere('tr.user = :user')
            ->andWhere('tr.formation = :formation')
            ->setParameter('user', $user)
            ->setParameter('formation', $formation)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countApprovedByFormation(Formation $formation): int
    {
        return (int) $this->createQueryBuilder('tr')
            ->select('COUNT(tr.id)')
            ->andWhere('tr.formation = :formation')
            ->andWhere('tr.status = :status')
            ->setParameter('formation', $formation)
            ->setParameter('status', 'accepted')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<TrainingRequest>
     */
    public function findByFormationOrdered(Formation $formation): array
    {
        return $this->createQueryBuilder('tr')
            ->leftJoin('tr.user', 'u')
            ->addSelect('u')
            ->andWhere('tr.formation = :formation')
            ->setParameter('formation', $formation)
            ->orderBy('tr.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }

    //    /**
    //     * @return TrainingRequest[] Returns an array of TrainingRequest objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('t.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?TrainingRequest
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
