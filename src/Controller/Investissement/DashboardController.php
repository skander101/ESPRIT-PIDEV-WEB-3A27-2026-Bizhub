<?php

namespace App\Controller\Investissement;

use App\Entity\Investissement\Deal;
use App\Entity\UsersAvis\User;
use App\Entity\Investissement\Negotiation;
use App\Entity\Investissement\Project;
use App\Repository\InvestmentRepository;
use App\Repository\Investissement\DealRepository;
use App\Repository\NegotiationRepository;
use App\Repository\ProjectRepository;
use App\Service\MarketDataService;
use App\Service\NewsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/front/dashboard')]
class DashboardController extends AbstractController
{
    public function __construct(
        private ProjectRepository    $projectRepository,
        private InvestmentRepository $investmentRepository,
        private NegotiationRepository $negotiationRepository,
        private DealRepository        $dealRepository,
        private MarketDataService    $marketData,
        private NewsService          $newsService,
    ) {}

    #[Route('', name: 'app_biz_dashboard', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $type = ($user instanceof User ? $user->getUserType() : null);

        if ($type === 'startup') {
            return $this->dashboardStartup($user, $request);
        }

        return $this->redirectToRoute('app_front_dashboard_investisseur');
    }

    // ── Dashboard Investisseur ────────────────────────────────────────────────

    private function dashboardInvestisseur($user): Response
    {
        $userId = ($user instanceof User ? $user->getUserId() : null);

        // All investments for this investor
        $allInvestments = $this->investmentRepository->findAllByUser($user);

        // Basic stats
        $totalInvesti      = $this->investmentRepository->getTotalInvestedByUser($user);
        $nbInvestissements = count($allInvestments);
        $nbProjets         = $this->investmentRepository->countDistinctProjectsByUser($user);
        $parStatut         = $this->investmentRepository->countByStatutForUser($user);

        // Deals for this investor
        $deals      = $this->dealRepository->findByBuyerId($userId);
        $nbDeals    = count($deals);
        $dealsByNeg = [];
        foreach ($deals as $deal) {
            $dealsByNeg[$deal->getNegotiation_id()] = $deal;
        }

        // Negotiations for this investor
        $negotiations   = $this->negotiationRepository->findBy(['investor' => $user]);
        $nbNegsOuvertes = 0;
        $negByProject   = [];
        foreach ($negotiations as $neg) {
            $pid = $neg->getProject()?->getProject_id();
            if ($pid) {
                $negByProject[$pid] = $neg;
            }
            if ($neg->getStatus() === Negotiation::STATUS_OPEN) {
                $nbNegsOuvertes++;
            }
        }

        // ROI heuristics per sector
        $roiBySector = [
            'tech' => 30, 'fintech' => 25, 'sante' => 20, 'agriculture' => 15,
            'education' => 18, 'commerce' => 15, 'energie' => 22, 'immobilier' => 18,
            'transport' => 16, 'autre' => 12,
        ];
        $sectorLabels = array_flip(Project::SECTEURS);

        // Build enriched investments list + sector aggregation
        $investmentItems = [];
        $sectorsData     = [];
        $totalRoiWeight  = 0.0;
        $totalWeight     = 0.0;
        $projetsActifs   = 0;

        foreach ($allInvestments as $inv) {
            $project = $inv->getProject();
            $sector  = $project?->getSecteur() ?? 'autre';
            $roi     = $roiBySector[$sector] ?? 12;

            $neg  = $project ? ($negByProject[$project->getProject_id()] ?? null) : null;
            $deal = $neg ? ($dealsByNeg[$neg->getNegotiation_id()] ?? null) : null;

            // Count active projects
            if ($project && in_array($project->getStatus(), ['in_progress', 'funded'], true)) {
                $projetsActifs++;
            }

            // Sector aggregation (amount by sector)
            if (!isset($sectorsData[$sector])) {
                $sectorsData[$sector] = ['amount' => 0.0, 'label' => $sectorLabels[$sector] ?? ucfirst($sector)];
            }
            $sectorsData[$sector]['amount'] += (float) $inv->getAmount();

            // Weighted ROI
            $amount = (float) $inv->getAmount();
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

        // Alerts
        $alerts = [];
        foreach ($deals as $deal) {
            if ($deal->getStatus() === Deal::STATUS_PENDING_PAYMENT) {
                $alerts[] = [
                    'type'    => 'warning',
                    'icon'    => 'fa-credit-card',
                    'title'   => 'Paiement en attente',
                    'message' => 'Un deal attend votre paiement pour continuer.',
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
                'message' => "{$nbNegsOuvertes} négociation(s) en cours avec des startups.",
            ];
        }

        // Monthly chart data (last 6 months)
        $monthly = $this->investmentRepository->getMonthlyTotalByUser($user, 6);
        $chartLabels = [];
        $chartData   = [];
        $chartCumul  = [];
        $cumul = 0.0;
        for ($i = 5; $i >= 0; $i--) {
            $dt    = (new \DateTime())->modify("-{$i} months");
            $key   = $dt->format('Y-m');
            $label = $dt->format('M y');
            $amount = $monthly[$key] ?? 0.0;
            $cumul += $amount;
            $chartLabels[] = $label;
            $chartData[]   = $amount;
            $chartCumul[]  = round($cumul);
        }

        // Projets en cours for discovery
        $projetsEnCours = $this->projectRepository->findEnCours(4);
        $parSecteur     = $this->projectRepository->countBySecteur();

        $portfolio = [
            'total_investi'      => $totalInvesti,
            'roi_global'         => $roiGlobal,
            'nb_investissements' => $nbInvestissements,
            'projets_actifs'     => $projetsActifs,
            'gains_estimes'      => $gainsEstimes,
            'nb_negs_ouvertes'   => $nbNegsOuvertes,
            'nb_deals'           => $nbDeals,
            'alerts'             => $alerts,
            'recommendations'    => [],
            'investments'        => $investmentItems,
            'sectors'            => $sectorsData,
            'chart_labels'       => $chartLabels,
            'chart_data'         => $chartData,
            'chart_cumul'        => $chartCumul,
        ];

        return $this->render('front/dashboard/investisseur.html.twig', [
            'portfolio'           => $portfolio,
            'analysis'            => null,
            'total_investi'       => $totalInvesti,
            'nb_investissements'  => $nbInvestissements,
            'nb_projets'          => $nbProjets,
            'par_statut'          => $parStatut,
            'derniers'            => array_slice($allInvestments, 0, 5),
            'projets_en_cours'    => $projetsEnCours,
            'par_secteur'         => $parSecteur,
            'market'              => $this->marketData->getMarketData(),
        ]);
    }

    // ── Dashboard Startup ─────────────────────────────────────────────────────

    private function dashboardStartup($user, Request $request): Response
    {
        // Tous les projets du startup
        $projets = $this->projectRepository->findByUser($user);

        // Répartition par statut : ['brouillon' => 1, 'en_cours' => 2, ...]
        $parStatut = $this->projectRepository->countByStatusForUser($user);

        // Totaux
        $totalDemande = $this->projectRepository->getTotalBudgetByUser($user);
        $totalFinance = $this->investmentRepository->getTotalInvestedByProjects($projets);

        // Pourcentage global de financement
        $pourcentageGlobal = $totalDemande > 0
            ? min(100, round(($totalFinance / $totalDemande) * 100, 1))
            : 0;

        // Progression par projet (chaque projet + son montant investi)
        $projetsAvecProgression = [];
        foreach ($projets as $projet) {
            $investi = $this->investmentRepository->getTotalInvestedByProject($projet);
            $pct = $projet->getRequiredBudget() > 0
                ? min(100, round(($investi / $projet->getRequiredBudget()) * 100, 1))
                : 0;
            $projetsAvecProgression[] = [
                'projet'  => $projet,
                'investi' => $investi,
                'pct'     => $pct,
            ];
        }

        // Derniers 5 investissements reçus
        $derniersRecus = $this->investmentRepository->findLastReceivedByProjects($projets, 5);

        // Négociations reçues par la startup (investisseurs qui veulent négocier)
        $negotiations = $this->negotiationRepository->findBy(
            ['startup' => $user],
            ['updated_at' => 'DESC']
        );

        // Graphique : répartition par secteur (tous projets de la plateforme)
        $parSecteur = $this->projectRepository->countBySecteur();

        return $this->render('front/dashboard/startup.html.twig', [
            'projets'                 => $projets,
            'par_statut'              => $parStatut,
            'total_demande'           => $totalDemande,
            'total_finance'           => $totalFinance,
            'pourcentage_global'      => $pourcentageGlobal,
            'projets_avec_progression'=> $projetsAvecProgression,
            'derniers_recus'          => $derniersRecus,
            'negotiations'            => $negotiations,
            'par_secteur'             => $parSecteur,
            'market'                  => $this->marketData->getMarketData(),
            'news'                    => $this->newsService->getLatestWorldNews($request->query->get('news_cat', 'business')),
            'news_category'           => $request->query->get('news_cat', 'business'),
        ]);
    }
}
