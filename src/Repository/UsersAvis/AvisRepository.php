<?php

namespace App\Repository\UsersAvis;

use App\Entity\Elearning\Formation;
use App\Entity\UsersAvis\Avis;
use App\Entity\UsersAvis\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Avis>
 */
class AvisRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Avis::class);
    }

    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.user = :user')
            ->setParameter('user', $user)
            ->orderBy('a.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByUserAndFormation(User $user, Formation $formation): ?Avis
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.user = :user')
            ->andWhere('a.formation = :formation')
            ->setParameter('user', $user)
            ->setParameter('formation', $formation)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param list<Formation> $formations
     * @return array<int, list<Avis>>
     */
    public function findVisibleGroupedByFormations(array $formations): array
    {
        if ($formations === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('a')
            ->leftJoin('a.user', 'u')
            ->addSelect('u')
            ->andWhere('a.formation IN (:formations)')
            ->andWhere('a.is_removed = false OR a.is_removed IS NULL')
            ->setParameter('formations', $formations)
            ->orderBy('a.created_at', 'DESC')
            ->getQuery()
            ->getResult();

        $grouped = [];
        foreach ($rows as $row) {
            $formation = $row->getFormation();
            if ($formation === null || $formation->getFormation_id() === null) {
                continue;
            }

            $formationId = $formation->getFormation_id();
            if (!isset($grouped[$formationId])) {
                $grouped[$formationId] = [];
            }
            $grouped[$formationId][] = $row;
        }

        return $grouped;
    }

    public function findVerified(): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.is_verified = :verified')
            ->setParameter('verified', true)
            ->orderBy('a.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('a')
            ->orderBy('a.created_at', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findByRating(int $rating): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.rating = :rating')
            ->setParameter('rating', $rating)
            ->orderBy('a.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findAllWithUser(): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.user', 'u')
            ->addSelect('u')
            ->orderBy('a.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.avis_id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countVerified(): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.avis_id)')
            ->andWhere('a.is_verified = :verified')
            ->setParameter('verified', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countUnverified(): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.avis_id)')
            ->andWhere('a.is_verified = :verified OR a.is_verified IS NULL')
            ->setParameter('verified', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countDistinctReviewers(): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(DISTINCT IDENTITY(a.user))')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getAverageRating(): float
    {
        $result = $this->createQueryBuilder('a')
            ->select('AVG(a.rating) AS avg_rating')
            ->andWhere('a.rating IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();

        return round((float) $result, 2);
    }

    /**
     * @return array<int, array{rating: int, total: int}>
     */
    public function getRatingDistribution(): array
    {
        $rows = $this->createQueryBuilder('a')
            ->select('a.rating AS rating, COUNT(a.avis_id) AS total')
            ->andWhere('a.rating IS NOT NULL')
            ->groupBy('a.rating')
            ->orderBy('a.rating', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): array => [
            'rating' => (int) $row['rating'],
            'total' => (int) $row['total'],
        ], $rows);
    }
}
