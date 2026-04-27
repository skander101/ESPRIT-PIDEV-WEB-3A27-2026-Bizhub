<?php

namespace App\Repository\Elearning;

use App\Entity\Elearning\Formation;
use App\Entity\Elearning\Participation;
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

    /**
     * @param list<int> $ids
     * @return list<Formation>
     */
    public function findByIdsPreservingOrder(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids, static fn ($v) => is_int($v) || ctype_digit((string) $v))));
        if ($ids === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('f')
            ->where('f.formation_id IN (:ids)')
            ->setParameter('ids', $ids);
        /** @var list<Formation> $rows */
        $rows = $qb->getQuery()->getResult();
        $map = [];
        foreach ($rows as $f) {
            $map[$f->getFormation_id()] = $f;
        }
        $out = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if (isset($map[$id])) {
                $out[] = $map[$id];
            }
        }

        return $out;
    }

    /**
     * @return list<int>
     */
    public function findPopularFormationIds(int $limit): array
    {
        $r = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(p.formation) AS fid, COUNT(p.id_candidature) AS cnt')
            ->from(Participation::class, 'p')
            ->andWhere('p.lifecycleStatus = :paid')
            ->andWhere('p.payment_status = :psp')
            ->setParameter('paid', Participation::STATUS_PAID)
            ->setParameter('psp', 'PAID')
            ->groupBy('p.formation')
            ->orderBy('cnt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): int => (int) $row['fid'], $r);
    }

    /**
     * @return list<int>
     */
    public function findTrendingFormationIds(int $days, int $limit): array
    {
        $since = new \DateTimeImmutable('-' . max(1, $days) . ' days');
        $r = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(p.formation) AS fid, COUNT(p.id_candidature) AS cnt')
            ->from(Participation::class, 'p')
            ->andWhere('p.created_at >= :since')
            ->setParameter('since', $since)
            ->groupBy('p.formation')
            ->orderBy('cnt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): int => (int) $row['fid'], $r);
    }

    /**
     * @return list<int>
     */
    public function findNewestFormationIds(int $limit): array
    {
        $r = $this->createQueryBuilder('f')
            ->select('f.formation_id')
            ->orderBy('f.start_date', 'DESC')
            ->addOrderBy('f.formation_id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): int => (int) $row['formation_id'], $r);
    }
}
