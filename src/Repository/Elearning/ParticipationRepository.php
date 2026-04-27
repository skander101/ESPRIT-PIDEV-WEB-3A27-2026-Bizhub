<?php

namespace App\Repository\Elearning;

use App\Entity\Elearning\Formation;
use App\Entity\Elearning\Participation;
use App\Entity\UsersAvis\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

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
            ->orderBy('p.id_candidature', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findPaidByUserAndFormation(User $user, Formation $formation): ?Participation
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.user = :user')
            ->andWhere('p.formation = :formation')
            ->andWhere('p.lifecycleStatus = :paid')
            ->andWhere('p.payment_status = :pPaid')
            ->setParameter('user', $user)
            ->setParameter('formation', $formation)
            ->setParameter('paid', Participation::STATUS_PAID)
            ->setParameter('pPaid', 'PAID')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findAwaitingPaymentByUserAndFormation(User $user, Formation $formation): ?Participation
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.user = :user')
            ->andWhere('p.formation = :formation')
            ->andWhere('p.lifecycleStatus = :st')
            ->setParameter('user', $user)
            ->setParameter('formation', $formation)
            ->setParameter('st', Participation::STATUS_AWAITING_PAYMENT)
            ->orderBy('p.id_candidature', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<Participation>
     */
    public function findAllForAdminDashboard(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.formation', 'f')->addSelect('f')
            ->leftJoin('p.user', 'u')->addSelect('u')
            ->orderBy('p.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Formations où l'utilisateur a déjà une inscription (tous statuts).
     *
     * @return list<int>
     */
    public function findEngagedFormationIdsByUser(User $user): array
    {
        $r = $this->createQueryBuilder('p')
            ->select('IDENTITY(p.formation) AS fid')
            ->andWhere('p.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getScalarResult();

        return array_values(array_unique(array_map(static fn (array $row): int => (int) $row['fid'], $r)));
    }

    /**
     * Formations avec une inscription encore active (payée confirmée ou en attente de paiement).
     * Les dossiers annulés ne bloquent pas une nouvelle suggestion sur la même formation.
     *
     * @return list<int>
     */
    public function findActiveEnrollmentFormationIdsByUser(User $user): array
    {
        $r = $this->createQueryBuilder('p')
            ->select('IDENTITY(p.formation) AS fid')
            ->andWhere('p.user = :user')
            ->andWhere(
                '(p.lifecycleStatus = :paid AND p.payment_status = :pPaid) OR p.lifecycleStatus = :await'
            )
            ->setParameter('user', $user)
            ->setParameter('paid', Participation::STATUS_PAID)
            ->setParameter('pPaid', 'PAID')
            ->setParameter('await', Participation::STATUS_AWAITING_PAYMENT)
            ->getQuery()
            ->getScalarResult();

        return array_values(array_unique(array_map(static fn (array $row): int => (int) $row['fid'], $r)));
    }

    /**
     * @return list<Participation>
     */
    public function findRecentParticipationsWithFormation(User $user, int $limit = 25): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.formation', 'f')->addSelect('f')
            ->andWhere('p.user = :user')
            ->setParameter('user', $user)
            ->orderBy('p.created_at', 'DESC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();
    }
}
