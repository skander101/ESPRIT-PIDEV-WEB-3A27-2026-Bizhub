<?php

declare(strict_types=1);

namespace App\Service\Elearning;

use App\Entity\Elearning\Formation;
use App\Entity\UsersAvis\User;
use App\Repository\Elearning\FormationRepository;

/**
 * Netflix-style blocks: personalized (Python or local), trending, popular, new.
 */
final class FormationRecommendationService
{
    public function __construct(
        private readonly FormationRepository $formationRepository,
        private readonly PythonRecommendationClient $pythonRecommendationClient,
        private readonly LocalFormationRecommender $localFormationRecommender,
    ) {
    }

    /**
     * @return array{
     *   personalized: list<Formation>,
     *   trending: list<Formation>,
     *   popular: list<Formation>,
     *   newFormations: list<Formation>
     * }
     */
    public function getHomeBlocksForUser(User $user): array
    {
        return [
            'personalized' => $this->personalizedFormations($user, 6),
            'trending' => $this->trendingSlice(6),
            'popular' => $this->popularSlice(6),
            'newFormations' => $this->newSlice(6),
        ];
    }

    /**
     * @return array<string, list<Formation>>
     */
    public function getFormationsIndexBlocksForUser(User $user): array
    {
        return [
            'personalized' => $this->personalizedFormations($user, 8),
            'trending' => $this->trendingSlice(8),
            'popular' => $this->popularSlice(8),
            'newFormations' => $this->newSlice(8),
        ];
    }

    /**
     * @return list<Formation>
     */
    private function personalizedFormations(User $user, int $limit): array
    {
        $fetch = max(24, $limit);
        $pyIds = $this->pythonRecommendationClient->fetchRecommendedFormationIds($user, $fetch);
        $ids = $pyIds ?? $this->localFormationRecommender->recommendFormationIds($user, $fetch);

        return array_slice($this->formationRepository->findByIdsPreservingOrder($ids), 0, $limit);
    }

    /**
     * @return list<Formation>
     */
    private function trendingSlice(int $limit): array
    {
        return $this->formationRepository->findByIdsPreservingOrder(
            $this->formationRepository->findTrendingFormationIds(30, $limit)
        );
    }

    /**
     * @return list<Formation>
     */
    private function popularSlice(int $limit): array
    {
        return $this->formationRepository->findByIdsPreservingOrder(
            $this->formationRepository->findPopularFormationIds($limit)
        );
    }

    /**
     * @return list<Formation>
     */
    private function newSlice(int $limit): array
    {
        return $this->formationRepository->findByIdsPreservingOrder(
            $this->formationRepository->findNewestFormationIds($limit)
        );
    }
}
