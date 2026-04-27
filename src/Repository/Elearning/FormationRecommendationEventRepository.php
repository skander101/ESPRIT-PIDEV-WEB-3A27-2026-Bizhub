<?php

declare(strict_types=1);

namespace App\Repository\Elearning;

use App\Entity\Elearning\FormationRecommendationEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FormationRecommendationEvent>
 */
class FormationRecommendationEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FormationRecommendationEvent::class);
    }

    /**
     * @return list<array{formation_id: int, title: string|null, impressions: int, clicks: int, enrolls: int}>
     */
    public function aggregateStatsByFormation(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = <<<'SQL'
SELECT f.formation_id AS formation_id,
       f.title AS title,
       SUM(CASE WHEN e.event_type = 'impression' THEN 1 ELSE 0 END) AS impressions,
       SUM(CASE WHEN e.event_type = 'click' THEN 1 ELSE 0 END) AS clicks,
       SUM(CASE WHEN e.event_type = 'enroll' THEN 1 ELSE 0 END) AS enrolls
FROM formation_recommendation_event e
INNER JOIN formation f ON f.formation_id = e.formation_id
GROUP BY f.formation_id, f.title
ORDER BY clicks DESC, impressions DESC
SQL;

        return $conn->fetchAllAssociative($sql);
    }
}
