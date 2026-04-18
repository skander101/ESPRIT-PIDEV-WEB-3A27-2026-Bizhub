<?php

namespace App\Service\Investissement;

use App\Entity\Investissement\Deal;
use App\Entity\Investissement\Negotiation;
use App\Entity\Investissement\Project;
use App\Entity\UsersAvis\User;
use App\Repository\InvestmentRepository;
use App\Repository\NegotiationRepository;
use App\Repository\Investissement\DealRepository;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class DashboardInvestisseurService
{
    private const ROI_BY_SECTOR = [
        'tech' => 30, 'fintech' => 25, 'sante' => 20, 'agriculture' => 15,
        'education' => 18, 'commerce' => 15, 'energie' => 22, 'immobilier' => 18,
        'transport' => 16, 'autre' => 12,
    ];

    public function __construct(
        private InvestmentRepository  $investmentRepository,
        private NegotiationRepository $negotiationRepository,
        private DealRepository        $dealRepository,
        private HttpClientInterface   $httpClient,
        private ?string $openaiApiKey,
    ) {}

    /**
     * Construit le portfolio complet de l'investisseur.
     * Appelé par DashboardInvestisseurController.
     */
    public function buildPortfolio(User $user): array
    {
        $userId = $user->getUserId();

        $allInvestments    = $this->investmentRepository->findAllByUser($user);
        $totalInvesti      = $this->investmentRepository->getTotalInvestedByUser($user);
        $nbInvestissements = count($allInvestments);
        $parStatut         = $this->investmentRepository->countByStatutForUser($user);

        $deals          = $this->dealRepository->findByBuyerId($userId);
        $nbDeals        = count($deals);
        $nbDealsActifs  = $this->dealRepository->countActiveByBuyerId($userId);

        $negotiations   = $this->negotiationRepository->findByInvestor($user);
        $nbNegsOuvertes = 0;
        foreach ($negotiations as $neg) {
            if ($neg->getStatus() === Negotiation::STATUS_OPEN) {
                $nbNegsOuvertes++;
            }
        }

        // ROI et secteurs
        $sectorsData    = [];
        $totalRoiWeight = 0.0;
        $totalWeight    = 0.0;
        $projetsActifs  = 0;
        $investmentItems = [];

        $sectorLabels = array_flip(Project::SECTEURS);

        $dealsByNeg = [];
        foreach ($deals as $deal) {
            $dealsByNeg[$deal->getNegotiation_id()] = $deal;
        }
        $negByProject = [];
        foreach ($negotiations as $neg) {
            $pid = $neg->getProject()?->getProject_id();
            if ($pid) {
                $negByProject[$pid] = $neg;
            }
        }

        foreach ($allInvestments as $inv) {
            $project = $inv->getProject();
            $sector  = $project?->getSecteur() ?? 'autre';
            $roi     = self::ROI_BY_SECTOR[$sector] ?? 12;
            $amount  = (float) $inv->getAmount();

            $neg  = $project ? ($negByProject[$project->getProject_id()] ?? null) : null;
            $deal = $neg ? ($dealsByNeg[$neg->getNegotiation_id()] ?? null) : null;

            if ($project && in_array($project->getStatus(), ['in_progress', 'funded'], true)) {
                $projetsActifs++;
            }

            if (!isset($sectorsData[$sector])) {
                $sectorsData[$sector] = [
                    'amount' => 0.0,
                    'label'  => $sectorLabels[$sector] ?? ucfirst($sector),
                ];
            }
            $sectorsData[$sector]['amount'] += $amount;

            $totalRoiWeight += $roi * $amount;
            $totalWeight    += $amount;

            $investmentItems[] = [
                'investment'   => $inv,
                'project'      => $project,
                'neg'          => $neg,
                'deal'         => $deal,
                'roi_estim'    => $roi,
                'sector_label' => $sectorLabels[$sector] ?? ucfirst($sector),
            ];
        }

        $roiGlobal    = $totalWeight > 0 ? round($totalRoiWeight / $totalWeight, 1) : 0;
        $gainsEstimes = round($totalInvesti * $roiGlobal / 100);

        // Alertes
        $alerts = [];
        foreach ($deals as $deal) {
            if ($deal->getStatus() === Deal::STATUS_PENDING_PAYMENT) {
                $alerts[] = [
                    'type'    => 'warning',
                    'icon'    => 'fa-credit-card',
                    'title'   => 'Paiement en attente',
                    'message' => 'Un deal attend votre paiement.',
                ];
                break;
            }
        }
        foreach ($deals as $deal) {
            if ($deal->getStatus() === Deal::STATUS_PENDING_SIGNATURE) {
                $alerts[] = [
                    'type'    => 'info',
                    'icon'    => 'fa-file-signature',
                    'title'   => 'Signature en attente',
                    'message' => 'Un contrat attend votre signature électronique.',
                ];
                break;
            }
        }
        if ($nbNegsOuvertes > 0) {
            $alerts[] = [
                'type'    => 'info',
                'icon'    => 'fa-handshake',
                'title'   => 'Négociation(s) ouverte(s)',
                'message' => "{$nbNegsOuvertes} négociation(s) en cours.",
            ];
        }

        // Graphique mensuel
        $monthly     = $this->investmentRepository->getMonthlyTotalByUser($user, 6);
        $chartLabels = [];
        $chartData   = [];
        $chartCumul  = [];
        $cumul = 0.0;
        for ($i = 5; $i >= 0; $i--) {
            $dt    = (new \DateTime())->modify("-{$i} months");
            $key   = $dt->format('Y-m');
            $amount = $monthly[$key] ?? 0.0;
            $cumul += $amount;
            $chartLabels[] = $dt->format('M y');
            $chartData[]   = $amount;
            $chartCumul[]  = round($cumul);
        }

        return [
            'total_investi'      => $totalInvesti,
            'nb_investissements' => $nbInvestissements,
            'roi_global'         => $roiGlobal,
            'projets_actifs'     => $projetsActifs,
            'gains_estimes'      => $gainsEstimes,
            'nb_negs_ouvertes'   => $nbNegsOuvertes,
            'nb_deals'           => $nbDeals,
            'nb_deals_actifs'    => $nbDealsActifs,
            'alerts'             => $alerts,
            'recommendations'    => [],
            'investments'        => $investmentItems,
            'sectors'            => $sectorsData,
            'chart_labels'       => $chartLabels,
            'chart_data'         => $chartData,
            'chart_cumul'        => $chartCumul,
        ];
    }

    /**
     * Recommandations IA basées sur le portfolio (tableau).
     * Appelé par DashboardInvestisseurController::aiRecommendations().
     */
    public function getAiRecommendations(array $portfolio): array
    {
        if (empty($this->openaiApiKey)) {
            return ['recommendations' => [], 'source' => 'local'];
        }

        $total   = $portfolio['total_investi'] ?? 0;
        $nb      = $portfolio['nb_investissements'] ?? 0;
        $roi     = $portfolio['roi_global'] ?? 0;
        $sectors = implode(', ', array_keys($portfolio['sectors'] ?? []));

        $prompt = "Tu es un conseiller financier spécialisé en startups. "
            . "Donne 3 recommandations concrètes pour ce portefeuille d'investissement :\n"
            . "- Total investi : " . number_format($total, 0, ',', ' ') . " TND\n"
            . "- Nombre de placements : $nb\n"
            . "- ROI estimé moyen : $roi%\n"
            . "- Secteurs : $sectors\n"
            . "Format JSON : {\"recommendations\":[{\"titre\":\"...\",\"conseil\":\"...\",\"priorite\":\"haute|moyenne|basse\"},...]}";

        try {
            $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->openaiApiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model'      => 'gpt-4o-mini',
                    'messages'   => [['role' => 'user', 'content' => $prompt]],
                    'max_tokens' => 500,
                ],
                'timeout' => 15,
            ]);

            $data    = $response->toArray(false);
            $content = $data['choices'][0]['message']['content'] ?? null;

            if ($content) {
                $clean  = preg_replace('/```json\s*|\s*```/', '', $content);
                $parsed = json_decode($clean, true);
                if (is_array($parsed)) {
                    return array_merge($parsed, ['source' => 'ai']);
                }
            }
        } catch (\Throwable) {}

        return ['recommendations' => [], 'source' => 'local'];
    }

    /**
     * @deprecated Use buildPortfolio() instead
     */
    public function getDashboardData(User $user): array
    {
        return $this->buildPortfolio($user);
    }

    /**
     * @deprecated Use getAiRecommendations() instead
     */
    public function getAiRecommendation(User $user): ?string
    {
        $portfolio = $this->buildPortfolio($user);
        $result    = $this->getAiRecommendations($portfolio);
        return ($result['recommendations'][0]['conseil'] ?? null);
    }
}
