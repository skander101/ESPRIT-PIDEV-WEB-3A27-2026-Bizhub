<?php

namespace App\Controller\Investissement;

use App\Entity\UsersAvis\User;
use App\Repository\InvestmentRepository;
use App\Repository\ProjectRepository;
use App\Service\Investissement\DashboardInvestisseurService;
use App\Service\Investissement\MatchingService;
use App\Service\Investissement\PortfolioAnalysisService;
use App\Service\MarketDataService;
use App\Service\NewsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/front/dashboard')]
class DashboardInvestisseurController extends AbstractController
{
    public function __construct(
        private DashboardInvestisseurService $dashboardService,
        private PortfolioAnalysisService     $portfolioAnalysis,
        private MatchingService              $matchingService,
        private InvestmentRepository         $investmentRepository,
        private ProjectRepository            $projectRepository,
        private MarketDataService            $marketData,
        private NewsService                  $newsService,
    ) {}

    #[Route('/investisseur', name: 'app_front_dashboard_investisseur', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        if (($user instanceof User ? $user->getUserType() : null) !== 'investisseur') {
            return $this->redirectToRoute('app_front_dashboard');
        }

        $portfolio = $this->dashboardService->buildPortfolio($user);

        // Read portfolio analysis from session (set by the analyser route)
        $sessionKey = 'portfolio_analysis_' . ($user instanceof User ? $user->getUserId() : null);
        $analysis   = $request->getSession()->get($sessionKey);

        // Matching: recommended projects for this investor
        $matches = $this->matchingService->matchProjects($user);

        // Legacy variables used by included partials
        $derniers       = $this->investmentRepository->findLastByUser($user, 5);
        $parStatut      = $this->investmentRepository->countByStatutForUser($user);
        $projetsEnCours = $this->projectRepository->findEnCours(4);
        $parSecteur     = $this->projectRepository->countBySecteur();

        return $this->render('front/dashboard/investisseur.html.twig', [
            'portfolio'           => $portfolio,
            'analysis'            => $analysis,
            'matches'             => $matches,
            // Legacy variables (used by included partials & existing sections)
            'total_investi'       => $portfolio['total_investi'],
            'nb_investissements'  => $portfolio['nb_investissements'],
            'nb_projets'          => $portfolio['nb_investissements'],
            'par_statut'          => $parStatut,
            'derniers'            => $derniers,
            'projets_en_cours'    => $projetsEnCours,
            'par_secteur'         => $parSecteur,
            'market'              => $this->marketData->getMarketData(),
            'news'                => $this->newsService->getLatestWorldNews($request->query->get('news_cat', 'business')),
            'news_category'       => $request->query->get('news_cat', 'business'),
        ]);
    }

    /**
     * Server-side portfolio analysis — stores result in session, then redirects.
     */
    #[Route('/investisseur/analyser', name: 'app_front_dashboard_investisseur_analyser', methods: ['POST'])]
    public function analyser(Request $request): Response
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('portfolio_analyse', $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Token de sécurité invalide. Veuillez réessayer.');
            return $this->redirectToRoute('app_front_dashboard_investisseur');
        }

        try {
            $result = $this->portfolioAnalysis->analyzePortfolio($user);
            $request->getSession()->set('portfolio_analysis_' . ($user instanceof User ? $user->getUserId() : null), $result);
            $this->addFlash('success', 'Analyse du portefeuille générée avec succès.');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Erreur lors de l\'analyse. Veuillez réessayer.');
        }

        return $this->redirectToRoute('app_front_dashboard_investisseur');
    }

    /**
     * AJAX endpoint — returns AI-generated portfolio recommendations.
     */
    #[Route('/investisseur/ai-reco', name: 'app_front_dashboard_investisseur_ai', methods: ['POST'])]
    public function aiRecommendations(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Non authentifié.'], 401);
        }

        if (!$this->isCsrfTokenValid('dashboard_ai_reco', $request->request->get('_token', ''))) {
            return $this->json(['error' => 'Token de sécurité invalide.'], 403);
        }

        $portfolio = $this->dashboardService->buildPortfolio($user);
        $result    = $this->dashboardService->getAiRecommendations($portfolio);

        return $this->json($result);
    }
}
