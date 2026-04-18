<?php

namespace App\Repository;

use App\Entity\Investissement\Negotiation;
use App\Entity\Investissement\NegotiationMessage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NegotiationMessage>
 */
class NegotiationMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NegotiationMessage::class);
    }

    public function findByNegotiation(Negotiation $negotiation): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.user', 'u')
            ->addSelect('u')
            ->andWhere('m.negotiation = :negotiation')
            ->setParameter('negotiation', $negotiation)
            ->orderBy('m.created_at', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
