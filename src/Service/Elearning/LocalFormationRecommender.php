<?php

declare(strict_types=1);

namespace App\Service\Elearning;

use App\Entity\Elearning\Formation;
use App\Entity\UsersAvis\User;
use App\Repository\Elearning\FormationRepository;
use App\Repository\Elearning\ParticipationRepository;

/**
 * Heuristic hybrid recommender (PHP) when the Python API is unavailable.
 */
final class LocalFormationRecommender
{
    /** @var list<string> */
    private const STOPWORDS = ['le', 'la', 'les', 'un', 'une', 'des', 'de', 'du', 'et', 'à', 'pour', 'en', 'sur', 'avec', 'the', 'a', 'an', 'of', 'and', 'or', 'in', 'on', 'at'];

    public function __construct(
        private readonly FormationRepository $formationRepository,
        private readonly ParticipationRepository $participationRepository,
    ) {
    }

    /**
     * @return list<int> formation_id ordered by score desc
     */
    public function recommendFormationIds(User $user, int $max = 12): array
    {
        $engaged = $this->participationRepository->findEngagedFormationIdsByUser($user);
        $all = $this->formationRepository->findAllOrderedByStartDate();
        $candidates = array_values(array_filter($all, static function (Formation $f) use ($engaged): bool {
            $id = $f->getFormation_id();
            return $id !== null && !in_array($id, $engaged, true);
        }));

        if ($candidates === []) {
            return [];
        }

        $profileTokens = $this->buildProfileTokens($user, $engaged);
        $popularity = $this->popularityMap();

        $scored = [];
        foreach ($candidates as $f) {
            $id = (int) $f->getFormation_id();
            $textScore = $this->tokenOverlapScore($profileTokens, $this->tokenize((string) $f->getTitle() . ' ' . (string) ($f->getDescription() ?? '')));
            $cost = (float) ($f->getCost() ?? 0.0);
            $costFit = $this->costFitScore($user, $engaged, $cost);
            $maxPop = max($popularity) ?: 1;
            $pop = ($popularity[$id] ?? 0) / $maxPop;
            $recency = $this->recencyScore($f);
            $score = 2.2 * $textScore + 1.1 * $costFit + 1.4 * $pop + 0.9 * $recency;
            if ($f->isEnLigne()) {
                $score += 0.05;
            }
            $scored[] = ['id' => $id, 's' => $score];
        }

        usort($scored, static fn (array $a, array $b): int => $b['s'] <=> $a['s']);

        return array_slice(array_map(static fn (array $x): int => $x['id'], $scored), 0, $max);
    }

    /**
     * @param list<int> $engagedFormationIds
     * @return array<string, true>
     */
    private function buildProfileTokens(User $user, array $engagedFormationIds): array
    {
        $tokens = [];
        foreach ($this->formationRepository->findByIdsPreservingOrder($engagedFormationIds) as $f) {
            foreach ($this->tokenize((string) $f->getTitle() . ' ' . (string) ($f->getDescription() ?? '')) as $t) {
                $tokens[$t] = true;
            }
        }
        if ($tokens === []) {
            $name = strtolower((string) ($user->getFullName() ?? $user->getEmail() ?? ''));
            foreach ($this->tokenize($name) as $t) {
                $tokens[$t] = true;
            }
        }

        return $tokens;
    }

    /**
     * @param array<string, true> $profile
     * @param list<string>        $words
     */
    private function tokenOverlapScore(array $profile, array $words): float
    {
        if ($profile === [] || $words === []) {
            return 0.0;
        }
        $hit = 0;
        foreach ($words as $w) {
            if (isset($profile[$w])) {
                ++$hit;
            }
        }

        return min(1.0, $hit / max(4, count($words)));
    }

    /**
     * @return list<string>
     */
    private function tokenize(string $text): array
    {
        $text = strtolower(preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? '');
        $parts = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = [];
        foreach ($parts as $p) {
            if (strlen($p) < 3 || in_array($p, self::STOPWORDS, true)) {
                continue;
            }
            $out[] = $p;
        }

        return array_values(array_unique($out));
    }

    /**
     * @param list<int> $engagedFormationIds
     */
    private function costFitScore(User $user, array $engagedFormationIds, float $cost): float
    {
        unset($user);
        $refs = [];
        foreach ($this->formationRepository->findByIdsPreservingOrder($engagedFormationIds) as $f) {
            $c = (float) ($f->getCost() ?? 0.0);
            if ($c > 0) {
                $refs[] = $c;
            }
        }
        if ($refs === []) {
            return 0.35;
        }
        $mean = array_sum($refs) / count($refs);
        if ($mean <= 0) {
            return 0.35;
        }
        $diff = abs($cost - $mean) / $mean;

        return max(0.0, 1.0 - min(1.0, $diff));
    }

    private function recencyScore(Formation $f): float
    {
        $start = $f->getStartDate();
        if (!$start instanceof \DateTimeInterface) {
            return 0.2;
        }
        $days = (new \DateTimeImmutable())->diff(\DateTimeImmutable::createFromInterface($start))->days ?? 365;
        if ($start < new \DateTimeImmutable('today')) {
            return 0.15;
        }

        return max(0.1, 1.0 - min(1.0, $days / 180.0));
    }

    /**
     * @return array<int, float> formation_id => score 0..1 (by popularity rank)
     */
    private function popularityMap(): array
    {
        $ids = $this->formationRepository->findPopularFormationIds(200);
        $n = count($ids);
        if ($n === 0) {
            return [];
        }
        $map = [];
        foreach ($ids as $i => $id) {
            $map[(int) $id] = ($n - $i) / $n;
        }

        return $map;
    }
}
