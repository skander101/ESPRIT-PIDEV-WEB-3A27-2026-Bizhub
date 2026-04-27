<?php

namespace App\Controller\Marketplace;

use App\Service\Marketplace\StatisticsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/marketplace/statistics', name: 'marketplace_statistics_')]
class StatisticsController extends AbstractController
{
    public function __construct(
        private readonly StatisticsService $statisticsService,
    ) {}

    /**
     * Page HTML dédiée aux statistiques marketplace.
     *
     * GET /marketplace/statistiques
     */
    #[Route('/page', name: 'page', methods: ['GET'])]
    public function page(Request $request): Response
    {
        $top    = max(1, min(20, (int) $request->query->get('top', 5)));
        $months = max(1, min(24, (int) $request->query->get('months', 6)));

        $topOrders   = $this->statisticsService->getTopOrdersByAmount($top);
        $topFreq     = $this->statisticsService->getTopProductsByFrequency($top);
        $topRevenue  = $this->statisticsService->getTopProductsByRevenue($top);
        $monthly     = $this->statisticsService->getMonthlyRevenue($months);
        $byStatut    = $this->statisticsService->getStatsByStatut();

        // Prepare chart data
        $revenueLabels = array_column($monthly, 'mois');
        $revenueData   = array_column($monthly, 'total');
        $revenueNb     = array_column($monthly, 'nb');

        $statutLabels  = array_column($byStatut, 'statut');
        $statutNb      = array_column($byStatut, 'nb');

        $totalCommandes = array_sum($statutNb);
        $totalRevenue   = array_sum(array_column($byStatut, 'totalTtc'));

        return $this->render('front/Marketplace/statistics/index.html.twig', [
            'top_commandes'   => $topOrders,
            'top_freq'        => $topFreq,
            'top_revenue'     => $topRevenue,
            'monthly'         => $monthly,
            'by_statut'       => $byStatut,
            'revenue_labels'  => $revenueLabels,
            'revenue_data'    => $revenueData,
            'revenue_nb'      => $revenueNb,
            'statut_labels'   => $statutLabels,
            'statut_nb'       => $statutNb,
            'total_commandes' => $totalCommandes,
            'total_revenue'   => $totalRevenue,
            'top'             => $top,
            'months'          => $months,
        ]);
    }

    /**
     * Top 5 commandes par montant TTC décroissant.
     *
     * GET /marketplace/statistics/top-orders
     * GET /marketplace/statistics/top-orders?limit=10
     *
     * @return JsonResponse
     */
    #[Route('/top-orders', name: 'top_orders', methods: ['GET'])]
    public function topOrders(Request $request): JsonResponse
    {
        $limit = max(1, min(50, (int) $request->query->get('limit', 5)));

        $data = $this->statisticsService->getTopOrdersByAmount($limit);

        return $this->json([
            'success' => true,
            'metric'  => 'top_orders_by_amount',
            'limit'   => $limit,
            'count'   => count($data),
            'data'    => $data,
        ]);
    }

    /**
     * Top 5 produits les plus commandés (par quantité totale vendue).
     *
     * GET /marketplace/statistics/top-products
     * GET /marketplace/statistics/top-products?limit=10&sort=revenue
     *
     * @return JsonResponse
     */
    #[Route('/top-products', name: 'top_products', methods: ['GET'])]
    public function topProducts(Request $request): JsonResponse
    {
        $limit  = max(1, min(50, (int) $request->query->get('limit', 5)));
        $sort   = $request->query->get('sort', 'frequency');

        $data = match ($sort) {
            'revenue'   => $this->statisticsService->getTopProductsByRevenue($limit),
            default     => $this->statisticsService->getTopProductsByFrequency($limit),
        };

        return $this->json([
            'success' => true,
            'metric'  => 'top_products_by_' . $sort,
            'limit'   => $limit,
            'count'   => count($data),
            'data'    => $data,
        ]);
    }

    /**
     * Chiffre d'affaires mensuel des N derniers mois.
     *
     * GET /marketplace/statistics/monthly-revenue
     * GET /marketplace/statistics/monthly-revenue?months=12
     *
     * @return JsonResponse
     */
    #[Route('/monthly-revenue', name: 'monthly_revenue', methods: ['GET'])]
    public function monthlyRevenue(Request $request): JsonResponse
    {
        $months = max(1, min(24, (int) $request->query->get('months', 6)));

        $data = $this->statisticsService->getMonthlyRevenue($months);

        return $this->json([
            'success' => true,
            'metric'  => 'monthly_revenue',
            'months'  => $months,
            'count'   => count($data),
            'data'    => $data,
        ]);
    }

    /**
     * Répartition des commandes par statut avec montants cumulés.
     *
     * GET /marketplace/statistics/by-status
     *
     * @return JsonResponse
     */
    #[Route('/by-status', name: 'by_status', methods: ['GET'])]
    public function byStatus(): JsonResponse
    {
        $data = $this->statisticsService->getStatsByStatut();

        return $this->json([
            'success' => true,
            'metric'  => 'orders_by_status',
            'count'   => count($data),
            'data'    => $data,
        ]);
    }

    /**
     * Dashboard complet : tous les KPIs en une seule requête.
     *
     * GET /marketplace/statistics/dashboard
     * GET /marketplace/statistics/dashboard?top=10&months=12
     *
     * @return JsonResponse
     */
    #[Route('/dashboard', name: 'dashboard', methods: ['GET'])]
    public function dashboard(Request $request): JsonResponse
    {
        $top    = max(1, min(50, (int) $request->query->get('top', 5)));
        $months = max(1, min(24, (int) $request->query->get('months', 6)));

        $data = $this->statisticsService->getDashboardSummary($top, $months);

        return $this->json([
            'success'    => true,
            'metric'     => 'dashboard_summary',
            'parameters' => ['top' => $top, 'months' => $months],
            'data'       => $data,
        ]);
    }
}
