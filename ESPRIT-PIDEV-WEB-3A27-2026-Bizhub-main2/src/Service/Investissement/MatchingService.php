<?php

namespace App\Service\Investissement;

use App\Entity\Investissement\Project;
use App\Entity\UsersAvis\User;
use App\Repository\InvestmentRepository;
use App\Repository\ProjectRepository;

/**
 * Automatic investor–project compatibility matching.
 *
 * Score breakdown (0–100 per component):
 *   35% sector affinity     — how well the project's sector matches investor history
 *   25% budget fit          — does the remaining funding gap match the investor's ticket size
 *   25% project health      — funding traction + social proof
 *   15% sector ROI potential — historical return potential of the sector
 */
class MatchingService
{
    // ── Constants ────────────────────────────────────────────────────────────

    private const SECTOR_LABELS = [
        'tech'        => 'Technologie',
        'fintech'     => 'FinTech',
        'sante'       => 'Santé',
        'agriculture' => 'Agriculture',
        'education'   => 'Éducation',
        'commerce'    => 'Commerce',
        'energie'     => 'Énergie',
        'immobilier'  => 'Immobilier',
        'transport'   => 'Transport',
        'autre'       => 'Autre',
    ];

    /** Sector ROI potential score (0–100), used as the 4th component. */
    private const SECTOR_ROI_SCORE = [
        'fintech'     => 100,
        'tech'        => 95,
        'sante'       => 80,
        'energie'     => 75,
        'education'   => 70,
        'immobilier'  => 65,
        'agriculture' => 55,
        'commerce'    => 50,
        'transport'   => 50,
        'autre'       => 40,
    ];

    public function __construct(
        private InvestmentRepository $investmentRepo,
        private ProjectRepository    $projectRepo,
    ) {}

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Returns up to 6 recommended projects sorted by compatibility score DESC.
     *
     * Each entry: {project, score, reason, badge, badge_color, badge_class, funded_pct}
     */
    public function matchProjects(User $investor): array
    {
        $investments = $this->investmentRepo->findAllByUser($investor);
        $profile     = $this->buildProfile($investments);
        $projects    = $this->projectRepo->findOpenForMatching($investor);

        $matches = [];
        foreach ($projects as $project) {
            $components = $this->computeComponents($project, $profile);
            $score      = $this->weightedScore($components);
            $badge      = $this->badge($score);
            $reason     = $this->generateReason($project, $profile, $components);

            $matches[] = [
                'project'     => $project,
                'score'       => $score,
                'reason'      => $reason,
                'badge'       => $badge['label'],
                'badge_color' => $badge['color'],
                'badge_class' => $badge['class'],
                'funded_pct'  => $components['funded_pct'],
                'nb_investors'=> $components['nb_investors'],
            ];
        }

        usort($matches, static fn($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($matches, 0, 6);
    }

    // ── Profile ───────────────────────────────────────────────────────────────

    private function buildProfile(array $investments): array
    {
        if (empty($investments)) {
            return [
                'total'          => 0.0,
                'avg_amount'     => 0.0,
                'nb'             => 0,
                'sector_weights' => [],   // sector => fraction of total (0.0–1.0)
                'top_sector'     => null,
            ];
        }

        $total         = 0.0;
        $sectorAmounts = [];

        foreach ($investments as $inv) {
            $amount = (float) $inv->getAmount();
            $sector = $inv->getProject()?->getSecteur() ?? 'autre';
            $total += $amount;
            $sectorAmounts[$sector] = ($sectorAmounts[$sector] ?? 0.0) + $amount;
        }

        $sectorWeights = [];
        foreach ($sectorAmounts as $sector => $amount) {
            $sectorWeights[$sector] = $total > 0 ? $amount / $total : 0.0;
        }
        arsort($sectorWeights);

        return [
            'total'          => $total,
            'avg_amount'     => $total / count($investments),
            'nb'             => count($investments),
            'sector_weights' => $sectorWeights,
            'top_sector'     => array_key_first($sectorWeights),
        ];
    }

    // ── Scoring components ────────────────────────────────────────────────────

    /**
     * Computes the four score components for a project, plus helper values.
     *
     * @return array{
     *   sector_score: int, budget_score: int, health_score: int, roi_score: int,
     *   funded_pct: float, nb_investors: int
     * }
     */
    private function computeComponents(Project $project, array $profile): array
    {
        $sector      = $project->getSecteur() ?? 'autre';
        $budget      = (float) ($project->getRequiredBudget() ?: 1);
        $funded      = 0.0;
        $nbInvestors = count($project->getInvestments());

        foreach ($project->getInvestments() as $inv) {
            $funded += (float) $inv->getAmount();
        }

        $fundedPct = min(100.0, round($funded / $budget * 100, 1));

        return [
            'sector_score'  => $this->scoreSector($sector, $profile),
            'budget_score'  => $this->scoreBudget($budget - $funded, $profile),
            'health_score'  => $this->scoreHealth($fundedPct, $nbInvestors),
            'roi_score'     => self::SECTOR_ROI_SCORE[$sector] ?? 40,
            'funded_pct'    => $fundedPct,
            'nb_investors'  => $nbInvestors,
            'sector'        => $sector,
            'remaining'     => max(0.0, $budget - $funded),
        ];
    }

    private function weightedScore(array $c): int
    {
        $raw = $c['sector_score'] * 0.35
             + $c['budget_score'] * 0.25
             + $c['health_score'] * 0.25
             + $c['roi_score']    * 0.15;

        return max(0, min(100, (int) round($raw)));
    }

    /**
     * Sector affinity (0–100):
     *  – Matches investor's dominant sector → high score
     *  – Small exposure → moderate
     *  – New sector (diversification) → 32
     *  – No investment history → neutral 55
     */
    private function scoreSector(string $sector, array $profile): int
    {
        if ($profile['nb'] === 0) {
            return 55; // No history → neutral
        }

        $weight = $profile['sector_weights'][$sector] ?? 0.0;

        if ($weight >= 0.5) return 100;
        if ($weight >= 0.3) return 88;
        if ($weight >= 0.15) return 72;
        if ($weight > 0) return 55;

        // Completely new sector → diversification opportunity (lower priority)
        return 32;
    }

    /**
     * Budget fit (0–100): ratio of remaining funding gap vs investor's avg ticket.
     */
    private function scoreBudget(float $remaining, array $profile): int
    {
        if ($remaining <= 0) {
            return 8; // Already fully funded
        }

        if ($profile['avg_amount'] <= 0) {
            return 50; // No investment history → neutral
        }

        $ratio = $remaining / $profile['avg_amount'];

        return match(true) {
            $ratio >= 0.5 && $ratio <= 2.0 => 100, // Sweet spot
            $ratio >= 0.25 && $ratio <= 4.0 => 75,
            $ratio >= 0.1 && $ratio <= 8.0  => 48,
            default                          => 22,
        };
    }

    /**
     * Project health (0–100): funding progress + social proof.
     */
    private function scoreHealth(float $fundedPct, int $nbInvestors): int
    {
        $baseScore = match(true) {
            $fundedPct >= 80  => 50,  // Almost closed, little room left
            $fundedPct >= 50  => 100, // Strong momentum
            $fundedPct >= 20  => 80,  // Good early traction
            $fundedPct >= 5   => 55,  // Very early stage
            default           => 35,  // No investors yet
        };

        $socialBonus = min(20, $nbInvestors * 4); // Up to +20 pts

        return min(100, $baseScore + $socialBonus);
    }

    // ── Reason generation ─────────────────────────────────────────────────────

    /**
     * Produces a single human-readable sentence explaining why this project was matched.
     * Uses the dominant score driver as the primary reason, with a fallback.
     */
    private function generateReason(Project $project, array $profile, array $c): string
    {
        $sector      = $c['sector'];
        $sectorLabel = self::SECTOR_LABELS[$sector] ?? ucfirst($sector);
        $weight      = $profile['sector_weights'][$sector] ?? 0.0;
        $sectorPct   = round($weight * 100);
        $fundedPct   = $c['funded_pct'];
        $nb          = $c['nb_investors'];
        $remaining   = $c['remaining'];
        $avgAmount   = $profile['avg_amount'];

        // Priority 1: Strong sector affinity
        if ($c['sector_score'] >= 88 && $weight >= 0.3) {
            return "Le secteur $sectorLabel représente {$sectorPct}% de votre portefeuille — c'est votre zone de confort.";
        }

        // Priority 2: Perfect budget match
        if ($c['budget_score'] >= 75 && $avgAmount > 0) {
            $avg = number_format((int) $avgAmount, 0, ',', ' ');
            return "Le financement restant (~" . number_format((int) $remaining, 0, ',', ' ') . " TND) correspond parfaitement à votre ticket moyen de {$avg} TND.";
        }

        // Priority 3: Strong project momentum
        if ($c['health_score'] >= 88 && $nb >= 3) {
            return "{$nb} investisseurs ont déjà rejoint ce projet avec {$fundedPct}% du budget atteint — forte dynamique en cours.";
        }

        // Priority 4: High ROI sector
        if ($c['roi_score'] >= 95) {
            return "Le secteur $sectorLabel affiche le meilleur potentiel de ROI de la plateforme — idéal pour maximiser vos rendements.";
        }

        // Priority 5: Good momentum (less strong)
        if ($fundedPct >= 20 && $nb >= 1) {
            return "Ce projet en $sectorLabel montre de bons signes de traction avec {$nb} investisseur(s) et {$fundedPct}% financé.";
        }

        // Priority 6: New sector diversification
        if ($c['sector_score'] <= 32 && $profile['nb'] >= 2) {
            return "Ce projet diversifierait votre portefeuille vers le secteur $sectorLabel — actuellement absent de votre historique.";
        }

        // Priority 7: Early opportunity
        if ($fundedPct < 10) {
            return "Projet en phase de démarrage en $sectorLabel — position de premier investisseur disponible.";
        }

        // Fallback
        return "Projet ouvert à l'investissement en $sectorLabel, compatible avec votre profil d'investisseur.";
    }

    // ── Badge ─────────────────────────────────────────────────────────────────

    private function badge(int $score): array
    {
        if ($score >= 70) {
            return ['label' => 'Très compatible', 'color' => '#059669', 'class' => 'match-high'];
        }
        if ($score >= 45) {
            return ['label' => 'Compatible', 'color' => '#d97706', 'class' => 'match-mid'];
        }
        return ['label' => 'Faible', 'color' => '#6b7280', 'class' => 'match-low'];
    }
}
