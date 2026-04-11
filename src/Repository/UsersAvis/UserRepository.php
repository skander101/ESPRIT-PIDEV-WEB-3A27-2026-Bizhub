<?php

namespace App\Repository\UsersAvis;

use App\Entity\UsersAvis\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.email = :email')
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return User[]
     */
    public function findActiveWithFaceEnrollment(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.is_active = :active')
            ->andWhere('u.face_token IS NOT NULL')
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();
    }

    public function findActive(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.is_active = :active')
            ->setParameter('active', true)
            ->orderBy('u.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByRole(string $userType): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.user_type = :userType')
            ->setParameter('userType', $userType)
            ->orderBy('u.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findAdmins(): array
    {
        return $this->findByRole('admin');
    }

    public function findAllRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('u')
            ->orderBy('u.created_at', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.user_id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countActive(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.user_id)')
            ->andWhere('u.is_active = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countInactive(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.user_id)')
            ->andWhere('u.is_active = :active OR u.is_active IS NULL')
            ->setParameter('active', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return User[]
     */
    public function findBySearchAndSort(?string $search, string $sort = 'created_at', string $direction = 'DESC'): array
    {
        $allowedSorts = [
            'username' => 'u.full_name',
            'email' => 'u.email',
            'role' => 'u.user_type',
            'created_at' => 'u.created_at',
        ];

        $sortField = $allowedSorts[$sort] ?? $allowedSorts['created_at'];
        $sortDirection = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';

        $qb = $this->createQueryBuilder('u');

        if ($search !== null && $search !== '') {
            $qb
                ->andWhere('LOWER(u.full_name) LIKE :search OR LOWER(u.email) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($search) . '%');
        }

        return $qb
            ->orderBy($sortField, $sortDirection)
            ->addOrderBy('u.user_id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<int, array{role: string, total: int}>
     */
    public function getRoleDistribution(): array
    {
        $rows = $this->createQueryBuilder('u')
            ->select('u.user_type AS role, COUNT(u.user_id) AS total')
            ->groupBy('u.user_type')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): array => [
            'role' => (string) $row['role'],
            'total' => (int) $row['total'],
        ], $rows);
    }

    /**
     * @return array<int, array{period: string, total: int}>
     */
    public function getMonthlyRegistrations(int $months = 6): array
    {
        $months = max(1, $months);

        $sql = <<<'SQL'
SELECT DATE_FORMAT(created_at, '%Y-%m') AS period, COUNT(user_id) AS total
FROM user
WHERE created_at IS NOT NULL
  AND created_at >= DATE_SUB(CURDATE(), INTERVAL :months MONTH)
GROUP BY DATE_FORMAT(created_at, '%Y-%m')
ORDER BY period ASC
SQL;

        $rows = $this->getEntityManager()->getConnection()->executeQuery($sql, [
            'months' => $months,
        ])->fetchAllAssociative();

        return array_map(static fn (array $row): array => [
            'period' => (string) $row['period'],
            'total' => (int) $row['total'],
        ], $rows);
    }
}
