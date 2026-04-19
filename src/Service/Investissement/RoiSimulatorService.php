<?php

namespace App\Service\Investissement;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class RoiSimulatorService
{
    /** Annual base rates per sector (percentage, e.g. 18 = 18 %) */
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

    /** Scenario multipliers [rate_multiplier, horizon_offset] */
    private const SCENARIOS = [
        'pessimiste' => [0.35, +2],
        'realiste'   => [1.00,  0],
        'optimiste'  => [2.10, -1],
    ];

    private const BASE_HORIZON = 5; // years

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $openaiApiKey,
    ) {}

    // ──────────────────────────────────────────────────────────────────────
    //  Public API
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Run ROI simulation for the three scenarios.
     *
     * @return array{
     *   invested: float,
     *   required_budget: float,
     *   coverage_pct: float,
     *   sector: string,
     *   sector_label: string,
     *   base_rate: float,
     *   scenarios: array,
     * }
     */
    public function simulate(float $invested, float $budget, string $sector): array
    {
        $baseRate     = self::SECTOR_RATES[$sector] ?? 11.0;
        $sectorLabel  = $this->sectorLabel($sector);
        $coveragePct  = $budget > 0 ? min(100, round($invested / $budget * 100, 1)) : 0;

        $scenarios = [];
        foreach (self::SCENARIOS as $key => [$rateMultiplier, $horizonOffset]) {
            $rate    = $baseRate * $rateMultiplier / 100;          // as decimal
            $horizon = max(2, self::BASE_HORIZON + $horizonOffset);  // at least 2 years
            $final   = $invested * (1 + $rate) ** $horizon;
            $gain    = $final - $invested;

            $scenarios[$key] = [
                'annual_rate'  => round($baseRate * $rateMultiplier, 2),  // %
                'horizon'      => $horizon,                                 // years
                'gain'         => round($gain, 2),
                'total'        => round($final, 2),
                'roi_pct'      => round($gain / $invested * 100, 1),
                'multiplier'   => round($final / $invested, 2),
                'monthly_avg'  => round($gain / ($horizon * 12), 2),
            ];
        }

        return [
            'invested'        => $invested,
            'required_budget' => $budget,
            'coverage_pct'    => $coveragePct,
            'sector'          => $sector,
            'sector_label'    => $sectorLabel,
            'base_rate'       => $baseRate,
            'scenarios'       => $scenarios,
        ];
    }

    /**
     * Call OpenAI to get a professional interpretation of the simulation.
     * Falls back to a local heuristic if the API is unavailable.
     *
     * @return array{verdict: string, verdict_key: string, text: string, advice: string}
     */
    public function getAiInterpretation(array $sim, string $projectTitle): array
    {
        if (!$this->openaiApiKey) {
            return $this->localInterpretation($sim);
        }

        try {
            return $this->callOpenAi($sim, $projectTitle);
        } catch (\Throwable) {
            return $this->localInterpretation($sim);
        }
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Private helpers
    // ──────────────────────────────────────────────────────────────────────

    private function callOpenAi(array $sim, string $projectTitle): array
    {
        $real  = $sim['scenarios']['realiste'];
        $opti  = $sim['scenarios']['optimiste'];
        $pessi = $sim['scenarios']['pessimiste'];

        $prompt = <<<PROMPT
Tu es un analyste financier expert en capital-risque et en startup.

Un investisseur envisage d'investir dans le projet « {$projectTitle} » (secteur : {$sim['sector_label']}).

Données de simulation :
- Montant investi : {$sim['invested']} TND
- Budget total du projet : {$sim['required_budget']} TND
- Couverture : {$sim['coverage_pct']} %
- Taux annuel sectoriel de référence : {$sim['base_rate']} %

Scénarios :
- Pessimiste : ROI {$pessi['roi_pct']} % sur {$pessi['horizon']} ans, gain {$pessi['gain']} TND, multiplicateur ×{$pessi['multiplier']}
- Réaliste   : ROI {$real['roi_pct']} % sur {$real['horizon']} ans, gain {$real['gain']} TND, multiplicateur ×{$real['multiplier']}
- Optimiste  : ROI {$opti['roi_pct']} % sur {$opti['horizon']} ans, gain {$opti['gain']} TND, multiplicateur ×{$opti['multiplier']}

Réponds UNIQUEMENT en JSON (sans balise Markdown) avec :
{
  "verdict_key": "opportunite" | "prudence" | "incertain",
  "verdict": "Opportunité attractive" | "À considérer avec prudence" | "Résultat incertain",
  "text": "2-3 phrases d'analyse professionnelle (en français)",
  "advice": "1 conseil concret et actionnable pour l'investisseur (en français, 1-2 phrases)"
}

Règles :
- verdict_key "opportunite" si ROI réaliste ≥ 40 % ou multiplicateur ≥ 1.4
- verdict_key "prudence"    si ROI réaliste entre 20 % et 39 %
- verdict_key "incertain"   si ROI réaliste < 20 %
- Sois concis, professionnel, objectif
PROMPT;

        $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->openaiApiKey,
                'Content-Type'  => 'application/json',
            ],
            'json' => [
                'model'           => 'gpt-4o-mini',
                'temperature'     => 0.6,
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

        if (!is_array($data) || empty($data['verdict_key'])) {
            return $this->localInterpretation($sim);
        }

        return $this->sanitizeInterpretation($data);
    }

    private function localInterpretation(array $sim): array
    {
        $roi = $sim['scenarios']['realiste']['roi_pct'];

        if ($roi >= 40) {
            return [
                'verdict_key' => 'opportunite',
                'verdict'     => 'Opportunité attractive',
                'text'        => sprintf(
                    'Avec un ROI réaliste de %.1f %% sur %d ans dans le secteur %s, cet investissement présente un potentiel de rentabilité solide. Le ratio risque/rendement semble favorable au regard des standards du marché.',
                    $roi, $sim['scenarios']['realiste']['horizon'], $sim['sector_label']
                ),
                'advice' => 'Diversifiez votre exposition en n\'allouant pas plus de 20 % de votre portefeuille à ce seul actif, et prévoyez un suivi trimestriel des KPI de la startup.',
            ];
        } elseif ($roi >= 20) {
            return [
                'verdict_key' => 'prudence',
                'verdict'     => 'À considérer avec prudence',
                'text'        => sprintf(
                    'Le scénario réaliste projette un ROI de %.1f %% sur %d ans. Ce rendement reste dans la moyenne du secteur %s, mais la marge entre le scénario pessimiste et optimiste indique une volatilité non négligeable.',
                    $roi, $sim['scenarios']['realiste']['horizon'], $sim['sector_label']
                ),
                'advice' => 'Analysez en détail le plan de trésorerie de la startup et exigez des jalons contractuels avant de vous engager définitivement.',
            ];
        } else {
            return [
                'verdict_key' => 'incertain',
                'verdict'     => 'Résultat incertain',
                'text'        => sprintf(
                    'Le scénario réaliste génère un ROI limité de %.1f %% sur %d ans. Dans le secteur %s, des alternatives plus performantes méritent d\'être évaluées avant toute décision.',
                    $roi, $sim['scenarios']['realiste']['horizon'], $sim['sector_label']
                ),
                'advice' => 'Demandez à la startup un plan de développement détaillé et des garanties sur les jalons clés avant d\'investir.',
            ];
        }
    }

    private function sanitizeInterpretation(array $data): array
    {
        $allowed = ['opportunite', 'prudence', 'incertain'];
        $key = in_array($data['verdict_key'] ?? '', $allowed, true) ? $data['verdict_key'] : 'incertain';

        return [
            'verdict_key' => $key,
            'verdict'     => substr(trim($data['verdict'] ?? ''), 0, 100),
            'text'        => substr(trim($data['text'] ?? ''), 0, 800),
            'advice'      => substr(trim($data['advice'] ?? ''), 0, 400),
        ];
    }

    private function sectorLabel(string $sector): string
    {
        $labels = [
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
        return $labels[$sector] ?? ucfirst($sector);
    }
}
