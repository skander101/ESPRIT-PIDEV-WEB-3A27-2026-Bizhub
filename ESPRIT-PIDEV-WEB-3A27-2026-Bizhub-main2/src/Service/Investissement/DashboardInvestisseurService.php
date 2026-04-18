<?php

namespace App\Service\Investissement;

use App\Entity\Investissement\Investment;
use App\Entity\Investissement\Project;
use App\Entity\UsersAvis\User;
use App\Repository\InvestmentRepository;
use App\Repository\NegotiationRepository;
use App\Repository\Investissement\DealRepository;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class DashboardInvestisseurService
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
    //  Main public method
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Build the complete dashboard portfolio for an investor.
     */
    public function buildPortfolio(User $user): array
    {
        $investments = $this->investmentRepo->findAllByUser($user);
        $buyerId     = $user->getUserId();

        // ── KPI aggregation ────────────────────────────────────────────────
        $totalInvesti   = 0.0;
        $totalGains     = 0.0;
        $projetsActifs  = 0;
        $sectorMap      = [];  // sector => ['amount' => float, 'count' => int]

        foreach ($investments as $inv) {
            $amount  = (float) $inv->getAmount();
            $project = $inv->getProject();
            $sector  = $project ? ($project->getSecteur() ?? 'autre') : 'autre';
            $rate    = (self::SECTOR_RATES[$sector] ?? 11.0) / 100;

            // 5-year compound gain (réaliste scenario)
            $estimatedGain = $amount * ((1 + $rate) ** 5) - $amount;

            $totalInvesti += $amount;
            $totalGains   += $estimatedGain;

            if ($project && in_array($project->getStatus(), ['en_cours', 'publie', 'in_progress'], true)) {
                $projetsActifs++;
            }

            if (!isset($sectorMap[$sector])) {
                $sectorMap[$sector] = ['amount' => 0.0, 'count' => 0, 'label' => self::SECTOR_LABELS[$sector] ?? ucfirst($sector)];
            }
            $sectorMap[$sector]['amount'] += $amount;
            $sectorMap[$sector]['count']++;
        }

        $roiGlobal = $totalInvesti > 0 ? round($totalGains / $totalInvesti * 100, 1) : 0.0;

        // ── Negotiations & deals ───────────────────────────────────────────
        $negotiations = $this->negotiationRepo->findBy(['investor' => $user], ['created_at' => 'DESC']);
        $openNegs     = array_filter($negotiations, fn($n) => $n->getStatus() === 'open');
        $nbDeals      = $this->dealRepo->countActiveByBuyerId($buyerId);

        // ── Chart data (last 6 months) ─────────────────────────────────────
        $monthlyRaw  = $this->investmentRepo->getMonthlyTotalByUser($user, 6);
        $chartLabels = [];
        $chartData   = [];
        $chartCumul  = [];
        $cumul       = 0.0;

        for ($i = 5; $i >= 0; $i--) {
            $dt    = (new \DateTime())->modify("-{$i} months");
            $key   = $dt->format('Y-m');
            $label = $dt->format('M Y');
            // French abbreviation
            $frMonths = ['Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'];
            $label = $frMonths[(int)$dt->format('n') - 1] . ' ' . $dt->format('Y');

            $amount  = $monthlyRaw[$key] ?? 0.0;
            $cumul  += $amount;
            $chartLabels[] = $label;
            $chartData[]   = round($amount, 2);
            $chartCumul[]  = round($cumul, 2);
        }

        // ── Per-investment details ─────────────────────────────────────────
        $investmentDetails = [];
        foreach ($investments as $inv) {
            $amount  = (float) $inv->getAmount();
            $project = $inv->getProject();
            $sector  = $project ? ($project->getSecteur() ?? 'autre') : 'autre';
            $rate    = (self::SECTOR_RATES[$sector] ?? 11.0) / 100;
            $gain    = $amount * ((1 + $rate) ** 5) - $amount;
            $roi     = round($gain / $amount * 100, 1);

            // Find linked negotiation
            $neg = $project ? $this->negotiationRepo->findOneBy([
                'project'  => $project,
                'investor' => $user,
            ]) : null;

            // Find linked deal
            $deal = null;
            if ($neg) {
                $deal = $this->dealRepo->findOneBy(['negotiation_id' => $neg->getNegotiation_id()]);
            }
            if (!$deal) {
                $deal = $this->dealRepo->findOneBy(['project_id' => $project?->getProject_id(), 'buyer_id' => $buyerId]);
            }

            $investmentDetails[] = [
                'investment'   => $inv,
                'project'      => $project,
                'neg'          => $neg,
                'deal'         => $deal,
                'roi_estim'    => $roi,
                'gain_estim'   => round($gain, 2),
                'sector_label' => self::SECTOR_LABELS[$sector] ?? ucfirst($sector),
            ];
        }

        // ── Alerts ────────────────────────────────────────────────────────
        $alerts = $this->buildAlerts($investments, $negotiations, $nbDeals, $roiGlobal, $sectorMap);

        // ── Local recommendations (AI reco loaded lazily via AJAX) ────────
        $recommendations = $this->localRecommendations($totalInvesti, $roiGlobal, $sectorMap, count($investments));

        return [
            // KPIs
            'total_investi'       => round($totalInvesti, 2),
            'gains_estimes'       => round($totalGains, 2),
            'roi_global'          => $roiGlobal,
            'projets_actifs'      => $projetsActifs,
            'nb_investissements'  => count($investments),
            'nb_negs_ouvertes'    => count($openNegs),
            'nb_deals'            => $nbDeals,

            // Chart
            'chart_labels'        => $chartLabels,
            'chart_data'          => $chartData,
            'chart_cumul'         => $chartCumul,

            // Sector breakdown
            'sectors'             => $sectorMap,

            // Detail list
            'investments'         => $investmentDetails,

            // Alerts & recommendations
            'alerts'              => $alerts,
            'recommendations'     => $recommendations,
        ];
    }

    /**
     * Generate AI-powered portfolio recommendations via OpenAI.
     * Falls back to local heuristics on any failure.
     *
     * @return array{items: string[], source: 'openai'|'local'}
     */
    public function getAiRecommendations(array $portfolio): array
    {
        if (!$this->openaiApiKey) {
            return ['items' => $portfolio['recommendations'], 'source' => 'local'];
        }

        try {
            return $this->callOpenAiReco($portfolio);
        } catch (\Throwable) {
            return ['items' => $portfolio['recommendations'], 'source' => 'local'];
        }
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Private helpers
    // ──────────────────────────────────────────────────────────────────────

    private function buildAlerts(array $investments, array $negotiations, int $nbDeals, float $roi, array $sectorMap): array
    {
        $alerts = [];

        // Open negotiations waiting for action
        $openNegs = array_filter($negotiations, fn($n) => $n->getStatus() === 'open');
        if (count($openNegs) > 0) {
            $alerts[] = [
                'type'    => 'info',
                'icon'    => 'fa-comments',
                'title'   => count($openNegs) . ' négociation(s) en attente',
                'message' => 'Des startups attendent votre réponse dans vos négociations ouvertes.',
                'link'    => null,
            ];
        }

        // Deals ready to sign/pay
        $dealAlerts = array_filter($negotiations, fn($n) => $n->getStatus() === 'accepted');
        if (count($dealAlerts) > 0) {
            $alerts[] = [
                'type'    => 'success',
                'icon'    => 'fa-file-signature',
                'title'   => 'Deal(s) prêt(s) à finaliser',
                'message' => 'Vous avez ' . count($dealAlerts) . ' négociation(s) acceptée(s). Passez à l\'étape deal.',
                'link'    => null,
            ];
        }

        // Poor ROI warning
        if (count($investments) > 0 && $roi < 20) {
            $alerts[] = [
                'type'    => 'warning',
                'icon'    => 'fa-chart-line',
                'title'   => 'ROI estimé faible',
                'message' => 'Votre ROI global estimé est de ' . $roi . ' %. Envisagez de diversifier vers des secteurs plus performants.',
                'link'    => null,
            ];
        }

        // Single-sector concentration risk
        if (count($sectorMap) === 1) {
            $sector = array_key_first($sectorMap);
            $alerts[] = [
                'type'    => 'warning',
                'icon'    => 'fa-exclamation-triangle',
                'title'   => 'Concentration sectorielle',
                'message' => 'Tous vos investissements sont dans le secteur ' . (self::SECTOR_LABELS[$sector] ?? $sector) . '. Une diversification réduirait votre risque.',
                'link'    => null,
            ];
        }

        // No investments yet
        if (count($investments) === 0) {
            $alerts[] = [
                'type'    => 'info',
                'icon'    => 'fa-lightbulb',
                'title'   => 'Commencez à investir',
                'message' => 'Explorez les projets disponibles et réalisez votre premier investissement.',
                'link'    => null,
            ];
        }

        return $alerts;
    }

    private function localRecommendations(float $total, float $roi, array $sectors, int $count): array
    {
        $reco = [];

        if ($count === 0) {
            $reco[] = 'Explorez les projets disponibles dans les secteurs FinTech et Technologie, qui affichent historiquement les meilleurs rendements sur la plateforme.';
            $reco[] = 'Commencez par un investissement modéré (5 000 – 15 000 TND) pour tester le processus avant d\'engager des montants plus importants.';
            $reco[] = 'Utilisez la simulation ROI avant chaque investissement pour estimer votre rendement attendu selon les scénarios pessimiste, réaliste et optimiste.';
            return $reco;
        }

        if (count($sectors) < 3) {
            $reco[] = 'Votre portefeuille est concentré sur ' . count($sectors) . ' secteur(s). Envisagez d\'explorer des projets dans d\'autres secteurs pour réduire votre exposition au risque.';
        } else {
            $reco[] = 'Votre diversification sectorielle est bonne (' . count($sectors) . ' secteurs). Maintenez cet équilibre pour répartir efficacement le risque.';
        }

        if ($roi >= 40) {
            $reco[] = 'Votre portefeuille affiche un ROI estimé attractif de ' . $roi . ' %. Envisagez de réinvestir une partie des gains projetés dans de nouveaux projets à fort potentiel.';
        } elseif ($roi >= 20) {
            $reco[] = 'Votre ROI estimé de ' . $roi . ' % est dans la moyenne. Ciblez des projets en secteur FinTech ou IA pour dynamiser votre rendement global.';
        } else {
            $reco[] = 'Votre ROI estimé de ' . $roi . ' % est en-dessous des benchmarks. Examinez vos investissements les moins performants et envisagez de rééquilibrer votre portefeuille.';
        }

        if ($total > 0) {
            $reco[] = 'Engagez des négociations sur vos projets actifs pour optimiser vos conditions d\'investissement (equity %, taux d\'intérêt, clauses de sortie).';
        }

        $reco[] = 'Suivez régulièrement l\'avancement de chaque startup dans laquelle vous avez investi. Les projets avec une progression rapide méritent un accompagnement renforcé.';

        return $reco;
    }

    private function callOpenAiReco(array $portfolio): array
    {
        $sectorList = implode(', ', array_map(
            fn($k, $v) => $v['label'] . ' (' . round($v['amount']) . ' TND)',
            array_keys($portfolio['sectors']),
            $portfolio['sectors']
        ));

        $prompt = <<<PROMPT
Tu es un conseiller en investissement expert en startups et capital-risque, spécialisé dans le marché tunisien.

Profil de l'investisseur :
- Capital total investi : {$portfolio['total_investi']} TND
- ROI global estimé (5 ans, scénario réaliste) : {$portfolio['roi_global']} %
- Gains estimés : {$portfolio['gains_estimes']} TND
- Nombre d'investissements : {$portfolio['nb_investissements']}
- Projets actifs : {$portfolio['projets_actifs']}
- Négociations ouvertes : {$portfolio['nb_negs_ouvertes']}
- Répartition sectorielle : {$sectorList}

Génère exactement 4 recommandations personnalisées, concrètes et actionnables pour cet investisseur.
Réponds UNIQUEMENT en JSON sans balise Markdown :
{
  "recommendations": [
    "Recommandation 1 (1-2 phrases, concrète, en français)",
    "Recommandation 2",
    "Recommandation 3",
    "Recommandation 4"
  ]
}

Règles :
- Basées sur les données réelles du profil
- Focalisées sur : diversification, ROI, gestion du risque, opportunités manquées
- Ton professionnel, direct, constructif
- Jamais de généralités vides — chaque conseil doit être spécifique aux chiffres fournis
PROMPT;

        $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->openaiApiKey,
                'Content-Type'  => 'application/json',
            ],
            'json' => [
                'model'           => 'gpt-4o-mini',
                'temperature'     => 0.7,
                'response_format' => ['type' => 'json_object'],
                'messages'        => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ],
            'timeout' => 20,
        ]);

        $body    = $response->toArray();
        $content = $body['choices'][0]['message']['content'] ?? '{}';
        $data    = json_decode($content, true);

        $items = $data['recommendations'] ?? [];
        if (!is_array($items) || count($items) === 0) {
            return ['items' => $portfolio['recommendations'], 'source' => 'local'];
        }

        return [
            'items'  => array_map(fn($r) => substr(trim((string)$r), 0, 500), array_slice($items, 0, 4)),
            'source' => 'openai',
        ];
    }
}
