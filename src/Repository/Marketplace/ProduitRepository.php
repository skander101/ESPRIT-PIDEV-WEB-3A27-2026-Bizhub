<?php

namespace App\Repository\Marketplace;

use App\Entity\Marketplace\ProductService;
use App\Entity\UsersAvis\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductService>
 */
class ProduitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductService::class);
    }

    /** All active products, optionally filtered by category and search term */
    public function findActive(?string $category = null, ?string $search = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.status = :status')
            ->andWhere('p.stock > 0')
            ->setParameter('status', ProductService::STATUS_ACTIVE)
            ->orderBy('p.created_at', 'DESC');

        if ($category) {
            $qb->andWhere('p.category = :category')->setParameter('category', $category);
        }

        if ($search) {
            $qb->andWhere('LOWER(p.name) LIKE :search OR LOWER(p.description) LIKE :search')
               ->setParameter('search', '%' . mb_strtolower($search) . '%');
        }

        return $qb->getQuery()->getResult();
    }

    /** Products owned by a specific seller */
    public function findBySeller(User $seller): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.seller = :seller')
            ->setParameter('seller', $seller)
            ->orderBy('p.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** Last N products for dashboard widgets */
    public function findLatest(int $limit = 6): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.status = :status')
            ->setParameter('status', ProductService::STATUS_ACTIVE)
            ->orderBy('p.created_at', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countActive(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.product_id)')
            ->andWhere('p.status = :status')
            ->setParameter('status', ProductService::STATUS_ACTIVE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countBySeller(User $seller): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.product_id)')
            ->andWhere('p.seller = :seller')
            ->setParameter('seller', $seller)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
