<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\Elearning\FormationRecommendationEventRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/back/elearning/recommendations')]
#[IsGranted('ROLE_ADMIN')]
final class ElearningRecommendationStatsController extends AbstractController
{
    #[Route('/stats', name: 'app_admin_elearning_recommendation_stats', methods: ['GET'])]
    public function stats(FormationRecommendationEventRepository $repository): Response
    {
        $rows = $repository->aggregateStatsByFormation();
        $totals = [
            'impressions' => 0,
            'clicks' => 0,
            'enrolls' => 0,
        ];
        foreach ($rows as $r) {
            $totals['impressions'] += (int) ($r['impressions'] ?? 0);
            $totals['clicks'] += (int) ($r['clicks'] ?? 0);
            $totals['enrolls'] += (int) ($r['enrolls'] ?? 0);
        }
        $globalCtr = $totals['impressions'] > 0 ? round(100 * $totals['clicks'] / $totals['impressions'], 2) : 0.0;
        $conv = $totals['clicks'] > 0 ? round(100 * $totals['enrolls'] / $totals['clicks'], 2) : 0.0;

        return $this->render('back/elearning/recommendations/stats.html.twig', [
            'rows' => $rows,
            'totals' => $totals,
            'global_ctr_percent' => $globalCtr,
            'conversion_click_to_enroll_percent' => $conv,
        ]);
    }
}
