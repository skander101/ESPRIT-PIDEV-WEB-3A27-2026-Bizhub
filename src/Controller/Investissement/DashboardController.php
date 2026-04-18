<?php

namespace App\Controller\Investissement;

use App\Entity\Investissement\Deal;
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
        private ProjectRepository     $projectRepository,
        private InvestmentRepository  $investmentRepository,
        private NegotiationRepository $negotiationRepository,
        private DealRepository        $dealRepository,
        private MarketDataService     $marketData,
        private NewsService           $newsService,
    ) {}

    #[Route('', name: 'app_biz_dashboard', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        if ($user->getUserType() === 'startup') {
            return $this->dashboardStartup($user, $request);
        }

        return $this->redirectToRoute('app_front_dashboard_investisseur');
    }

    // ── Dashboard Startup ─────────────────────────────────────────────────────

    private function dashboardStartup($user, Request $request): Response
    {
        $projets = $this->projectRepository->findByUser($user);

        $parStatut = $this->projectRepository->countByStatusForUser($user);

        $totalDemande = $this->projectRepository->getTotalBudgetByUser($user);
        $totalFinance = $this->investmentRepository->getTotalInvestedByProjects($projets);

        $pourcentageGlobal = $totalDemande > 0
            ? min(100, round(($totalFinance / $totalDemande) * 100, 1))
            : 0;

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

        $derniersRecus = $this->investmentRepository->findLastReceivedByProjects($projets, 5);

        $negotiations = $this->negotiationRepository->findBy(
            ['startup' => $user],
            ['updated_at' => 'DESC']
        );

        $parSecteur = $this->projectRepository->countBySecteur();

        return $this->render('front/dashboard/startup.html.twig', [
            'projets'                  => $projets,
            'par_statut'               => $parStatut,
            'total_demande'            => $totalDemande,
            'total_finance'            => $totalFinance,
            'pourcentage_global'       => $pourcentageGlobal,
            'projets_avec_progression' => $projetsAvecProgression,
            'derniers_recus'           => $derniersRecus,
            'negotiations'             => $negotiations,
            'par_secteur'              => $parSecteur,
            'market'                   => $this->marketData->getMarketData(),
            'news'                     => $this->newsService->getLatestWorldNews($request->query->get('news_cat', 'business')),
            'news_category'            => $request->query->get('news_cat', 'business'),
        ]);
    }
}
