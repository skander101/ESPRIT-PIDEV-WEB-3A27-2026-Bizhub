<?php

namespace App\Service\Investissement;

use App\Entity\UsersAvis\User;
use App\Repository\InvestmentRepository;
use App\Repository\NegotiationRepository;
use App\Repository\Investissement\DealRepository;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PortfolioAnalysisService
{
    private const SECTOR_RATES = [
        'tech'        => 18.0,
        'fintech'     => 20.0,
        'sante'       => 15.0,
        'agriculture' => 10.0,
        'education'   => 12.0,
        'commerce'    => 11.0,
        'energie'     => 13.0,
        'immobilier'  => 14.0,
        'transport'   => 11.0,
        'autre'       => 11.0,
    ];

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

    public function __construct(
        private InvestmentRepository  $investmentRepo,
        private NegotiationRepository $negotiationRepo,
        private DealRepository        $dealRepo,
        private HttpClientInterface   $httpClient,
        private string                $openaiApiKey,
    ) {}

    // ──────────────────────────────────────────────────────────────────────
    //  Public API
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Full portfolio analysis for an investor.
     *
     * @return array{
     *   score: int,
     *   status_label: string,
     *   status_color: string,
     *   total_invested: float,
     *   nb_investments: int,
     *   nb_sectors: int,
     *   roi_estimated: float,
     *   trend: string,
     *   trend_label: string,
     *   predictions: array,
     *   chart: array,
     *   analysis: array,
     *   analyzed_at: \DateTimeInterface,
     * }
     */
    public function analyzePortfolio(User $user): array
    {
        $investments  = $this->investmentRepo->findAllByUser($user);
        $negotiations = $this->negotiationRepo->findBy(['investor' => $user]);
        $deals        = $this->dealRepo->findByBuyerId($user->getUserId());

        $totalInvested = array_sum(array_map(fn($i) => (float)$i->getAmount(), $investments));
        $sectors       = $this->buildSectorBreakdown($investments, $totalInvested);
        $nbSectors     = count($sectors);
        $roi           = $this->calculateWeightedRoi($investments, $totalInvested);
        $score         = $this->calculateScore($investments, $negotiations, $deals, $roi, $nbSectors);

        $statusLabel = $score >= 70 ? 'Bon'   : ($score >= 40 ? 'Moyen' : 'Risqué');
        $statusColor = $score >= 70 ? '#10b981' : ($score >= 40 ? '#f59e0b' : '#ef4444');

        $trend      = $roi >= 30 ? 'croissance' : ($roi >= 15 ? 'stable' : 'baisse');
        $trendLabel = match($trend) {
            'croissance' => 'En croissance',
            'stable'     => 'Stable',
            default      => 'En baisse',
        };

        $predictions = $this->buildPredictions($totalInvested, $roi);
        $chart       = $this->buildChart($totalInvested, $roi);
        $analysis    = $this->getAnalysis($user, $investments, $negotiations, $score, $roi, $sectors, $predictions);

        return [
            'score'          => $score,
            'status_label'   => $statusLabel,
            'status_color'   => $statusColor,
            'total_invested' => $totalInvested,
            'nb_investments' => count($investments),
            'nb_sectors'     => $nbSectors,
            'sectors'        => $sectors,
            'roi_estimated'  => $roi,
            'trend'          => $trend,
            'trend_label'    => $trendLabel,
            'predictions'    => $predictions,
            'chart'          => $chart,
            'analysis'       => $analysis,
            'analyzed_at'    => new \DateTime(),
        ];
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Score calculation
    // ──────────────────────────────────────────────────────────────────────

    private function calculateScore(array $investments, array $negotiations, array $deals, float $roi, int $nbSectors): int
    {
        // ROI component (0-100) — weight 35 %
        $roiComp = match(true) {
            $roi >= 40 => 100,
            $roi >= 30 => 80,
            $roi >= 20 => 60,
            $roi >= 10 => 40,
            $roi > 0   => 20,
            default    => 0,
        };

        // Diversification (0-100) — weight 25 %
        $divComp = match(true) {
            $nbSectors >= 4 => 100,
            $nbSectors === 3 => 75,
            $nbSectors === 2 => 50,
            $nbSectors === 1 => 25,
            default          => 0,
        };

        // Activity level (0-100) — weight 20 %
        $actComp = min(100, count($investments) * 10);

        // Deal quality: signed/completed deals (0-100) — weight 20 %
        $signedDeals = array_filter($deals, fn($d) => in_array($d->getStatus(), ['signed', 'completed'], true));
        $dealComp    = min(100, count($signedDeals) * 25);

        $score = (int) round(
            $roiComp  * 0.35 +
            $divComp  * 0.25 +
            $actComp  * 0.20 +
            $dealComp * 0.20
        );

        return max(0, min(100, $score));
    }

    // ──────────────────────────────────────────────────────────────────────
    //  ROI & sectors
    // ──────────────────────────────────────────────────────────────────────

    private function calculateWeightedRoi(array $investments, float $total): float
    {
        if ($total <= 0 || empty($investments)) {
            return 0.0;
        }

        $weightedGain = 0.0;
        foreach ($investments as $inv) {
            $amount  = (float) $inv->getAmount();
            $sector  = $inv->getProject()?->getSecteur() ?? 'autre';
            $rate    = (self::SECTOR_RATES[$sector] ?? 11.0) / 100;
            $gain    = $amount * ((1 + $rate) ** 5) - $amount;
            $weightedGain += $gain;
        }

        return round($weightedGain / $total * 100, 1);
    }

    private function buildSectorBreakdown(array $investments, float $total): array
    {
        $map = [];
        foreach ($investments as $inv) {
            $sector = $inv->getProject()?->getSecteur() ?? 'autre';
            $amount = (float) $inv->getAmount();
            if (!isset($map[$sector])) {
                $map[$sector] = [
                    'label'  => self::SECTOR_LABELS[$sector] ?? ucfirst($sector),
                    'amount' => 0.0,
                    'count'  => 0,
                    'pct'    => 0.0,
                ];
            }
            $map[$sector]['amount'] += $amount;
            $map[$sector]['count']++;
        }

        foreach ($map as $key => $data) {
            $map[$key]['pct'] = $total > 0 ? round($data['amount'] / $total * 100, 1) : 0;
        }

        arsort($map);
        return $map;
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Predictions
    // ──────────────────────────────────────────────────────────────────────

    private function buildPredictions(float $invested, float $roi): array
    {
        if ($invested <= 0) {
            return $this->zeroPredictions();
        }

        $rate = $roi / 100;

        $build = function (int $years) use ($invested, $rate): array {
            $final = $invested * (1 + $rate / 5 * $years);   // linear approx
            $gain  = $final - $invested;
            $pct   = $invested > 0 ? round($gain / $invested * 100, 1) : 0;
            return [
                'annees'         => $years,
                'valeur_totale'  => round($final, 2),
                'gain_estime'    => round($gain, 2),
                'croissance_pct' => $pct,
            ];
        };

        return [
            'court_terme' => $build(1),
            'moyen_terme' => $build(3),
            'long_terme'  => $build(5),
        ];
    }

    private function zeroPredictions(): array
    {
        $z = ['annees' => 0, 'valeur_totale' => 0.0, 'gain_estime' => 0.0, 'croissance_pct' => 0.0];
        return [
            'court_terme' => array_merge($z, ['annees' => 1]),
            'moyen_terme' => array_merge($z, ['annees' => 3]),
            'long_terme'  => array_merge($z, ['annees' => 5]),
        ];
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Chart data (5-year projection, 3 scenarios)
    // ──────────────────────────────────────────────────────────────────────

    private function buildChart(float $invested, float $roi): array
    {
        $labels = ["Auj.", "An 1", "An 2", "An 3", "An 4", "An 5"];

        if ($invested <= 0) {
            return [
                'labels'     => $labels,
                'pessimiste' => array_fill(0, 6, 0),
                'realiste'   => array_fill(0, 6, 0),
                'optimiste'  => array_fill(0, 6, 0),
            ];
        }

        $rateReal  = $roi / 100 / 5;   // annual equivalent from 5-year ROI
        $ratePessi = $rateReal * 0.4;
        $rateOpti  = $rateReal * 1.8;

        $pessi = $real = $opti = [];
        for ($y = 0; $y <= 5; $y++) {
            $pessi[] = round($invested * (1 + $ratePessi * $y), 2);
            $real[]  = round($invested * (1 + $rateReal  * $y), 2);
            $opti[]  = round($invested * (1 + $rateOpti  * $y), 2);
        }

        return [
            'labels'     => $labels,
            'pessimiste' => $pessi,
            'realiste'   => $real,
            'optimiste'  => $opti,
        ];
    }

    // ──────────────────────────────────────────────────────────────────────
    //  AI / Local analysis text
    // ──────────────────────────────────────────────────────────────────────

    private function getAnalysis(User $user, array $investments, array $negotiations, int $score, float $roi, array $sectors, array $predictions): array
    {
        if (!$this->openaiApiKey || empty($investments)) {
            return $this->localAnalysis($score, $roi, $sectors, count($investments));
        }

        try {
            return $this->callOpenAi($investments, $negotiations, $score, $roi, $sectors, $predictions);
        } catch (\Throwable) {
            return $this->localAnalysis($score, $roi, $sectors, count($investments));
        }
    }

    private function callOpenAi(array $investments, array $negotiations, int $score, float $roi, array $sectors, array $predictions): array
    {
        $sectorList = implode(', ', array_map(
            fn($k, $v) => $v['label'] . ' (' . $v['pct'] . '%)',
            array_keys($sectors), $sectors
        ));
        $lt = $predictions['long_terme'];
        $investments_count = count($investments);
        $neg_count = count($negotiations);

        $prompt = <<<PROMPT
Tu es un conseiller financier expert en capital-risque pour le marché tunisien.

Profil du portefeuille :
- Score global : {$score}/100
- ROI estimé sur 5 ans : {$roi}%
- Nombre d'investissements : {$investments_count}
- Nombre de négociations : {$neg_count}
- Répartition sectorielle : {$sectorList}
- Valeur projetée à 5 ans (réaliste) : {$lt['valeur_totale']} TND (gain de {$lt['gain_estime']} TND)

Génère une analyse professionnelle complète. Réponds UNIQUEMENT en JSON sans balise Markdown :
{
  "sante": "2-3 phrases sur l'état de santé général du portefeuille",
  "risques": ["risque 1 (court, factuel)", "risque 2", "risque 3"],
  "forces": ["point fort 1", "point fort 2", "point fort 3"],
  "recommandations": [
    "Recommandation stratégique 1 (concrète, basée sur les données)",
    "Recommandation 2",
    "Recommandation 3",
    "Recommandation 4"
  ]
}

Règles : concis, professionnel, basé sur les chiffres réels, tout en français.
PROMPT;

        // Inject values (heredoc doesn't interpolate method calls)
        $prompt = str_replace(
            ['{$investments_count}', '{$neg_count}'],
            [count($investments), count($negotiations)],
            $prompt
        );

        $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->openaiApiKey,
                'Content-Type'  => 'application/json',
            ],
            'json' => [
                'model'           => 'gpt-4o-mini',
                'temperature'     => 0.65,
                'response_format' => ['type' => 'json_object'],
                'messages'        => [['role' => 'user', 'content' => $prompt]],
            ],
            'timeout' => 22,
        ]);

        $body    = $response->toArray();
        $content = $body['choices'][0]['message']['content'] ?? '{}';
        $data    = json_decode($content, true);

        if (!is_array($data) || empty($data['sante'])) {
            return $this->localAnalysis($score, $roi, $sectors, count($investments));
        }

        return [
            'sante'            => substr(trim((string)($data['sante'] ?? '')), 0, 600),
            'risques'          => array_slice((array)($data['risques'] ?? []), 0, 4),
            'forces'           => array_slice((array)($data['forces'] ?? []), 0, 4),
            'recommandations'  => array_slice((array)($data['recommandations'] ?? []), 0, 4),
            'source'           => 'openai',
        ];
    }

    private function localAnalysis(int $score, float $roi, array $sectors, int $nbInvestments): array
    {
        $nbSectors = count($sectors);

        // Health summary
        if ($score >= 70) {
            $sante = "Votre portefeuille affiche une bonne santé globale avec un score de $score/100. Le ROI estimé de $roi % sur 5 ans est supérieur aux benchmarks du marché. La structure actuelle est solide.";
        } elseif ($score >= 40) {
            $sante = "Votre portefeuille présente des performances correctes (score $score/100, ROI estimé $roi %). Quelques ajustements pourraient améliorer sensiblement votre rendement global.";
        } else {
            $sante = "Votre portefeuille nécessite une révision stratégique (score $score/100). Le ROI estimé de $roi % reste en-dessous des standards du marché. Une diversification et un meilleur ciblage sectoriel s'imposent.";
        }

        // Risks
        $risques = [];
        if ($nbSectors <= 1) {
            $risques[] = 'Concentration sectorielle élevée — tout votre capital est exposé à un seul secteur.';
        }
        if ($roi < 20) {
            $risques[] = 'ROI estimé faible — les projets choisis présentent un potentiel de rendement limité.';
        }
        if ($nbInvestments < 3) {
            $risques[] = 'Portefeuille peu diversifié — un faible nombre d\'investissements augmente la volatilité.';
        }
        if (empty($risques)) {
            $risques[] = 'Risque de marché général lié à la volatilité des startups en phase de croissance.';
        }

        // Strengths
        $forces = [];
        if ($roi >= 30) {
            $forces[] = "ROI estimé attractif de $roi % sur 5 ans, supérieur à la moyenne du marché.";
        }
        if ($nbSectors >= 3) {
            $forces[] = "Bonne diversification sectorielle sur $nbSectors secteurs.";
        }
        if ($nbInvestments >= 5) {
            $forces[] = "Volume d'investissements solide ($nbInvestments), réduisant le risque individuel.";
        }
        if (empty($forces)) {
            $forces[] = "Engagement actif sur la plateforme avec des projets en phase de développement.";
        }

        // Recommendations
        $reco = [];
        if ($nbSectors < 3) {
            $reco[] = 'Diversifiez vers des secteurs à fort potentiel (FinTech, Technologie, IA) pour réduire votre exposition sectorielle.';
        }
        if ($roi < 25) {
            $reco[] = "Ciblez des projets avec un taux de rendement sectoriel supérieur à 15 % pour améliorer votre ROI global.";
        }
        $reco[] = 'Initiez des négociations sur vos investissements actifs pour optimiser les conditions (equity %, clauses de sortie).';
        $reco[] = 'Réexaminez trimestriellement chaque startup pour détecter les signaux de sous-performance précoces.';

        return [
            'sante'           => $sante,
            'risques'         => array_slice($risques, 0, 4),
            'forces'          => array_slice($forces, 0, 4),
            'recommandations' => array_slice($reco, 0, 4),
            'source'          => 'local',
        ];
    }
}
