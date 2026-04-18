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

    /**
     * Charge tous les messages d'une négociation, triés par date croissante,
     * avec le sender pré-chargé (LEFT JOIN) pour éviter les problèmes de proxy Doctrine
     * et garantir que les messages de l'investisseur sont visibles côté startup.
     *
     * @return NegotiationMessage[]
     */
    public function findByNegotiation(Negotiation $negotiation): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.user', 'u')
            ->addSelect('u')
            ->where('m.negotiation = :neg')
            ->setParameter('neg', $negotiation)
            ->orderBy('m.created_at', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
