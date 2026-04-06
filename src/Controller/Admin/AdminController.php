<?php

namespace App\Controller\Admin;

use App\Entity\UsersAvis\User;
use App\Entity\UsersAvis\Avis;
use App\Repository\UsersAvis\UserRepository;
use App\Repository\UsersAvis\AvisRepository;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/back')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private AvisRepository $avisRepository,
    ) {
    }

    #[Route('', name: 'app_admin_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('back/index.html.twig');
    }

    #[Route('/dashboard', name: 'app_admin_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        $totalUsers = $this->userRepository->countAll();
        $activeUsers = $this->userRepository->countActive();
        $totalAvis = $this->avisRepository->countAll();
        $verifiedAvis = $this->avisRepository->countVerified();
        $recentUsers = $this->userRepository->findAllRecent(10);

        return $this->render('back/user/dashboard.html.twig', [
            'total_users' => $totalUsers,
            'active_users' => $activeUsers,
            'total_avis' => $totalAvis,
            'verified_avis' => $verifiedAvis,
            'recent_users' => $recentUsers,
        ]);
    }

    #[Route('/user', name: 'app_admin_user_index', methods: ['GET'])]
    public function userIndex(Request $request): Response
    {
        $search = trim((string) $request->query->get('search', ''));
        $sort = (string) $request->query->get('sort', 'created_at');
        $direction = strtoupper((string) $request->query->get('direction', 'DESC'));

        $allowedSorts = ['username', 'email', 'role', 'created_at'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }

        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            $direction = 'DESC';
        }

        $users = $this->userRepository->findBySearchAndSort($search, $sort, $direction);

        return $this->render('back/user/index.html.twig', [
            'users' => $users,
            'search' => $search,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    #[Route('/user/export/pdf', name: 'app_admin_user_export_pdf', methods: ['GET'])]
    public function userExportPdf(Request $request): Response
    {
        $search = trim((string) $request->query->get('search', ''));
        $sort = (string) $request->query->get('sort', 'created_at');
        $direction = strtoupper((string) $request->query->get('direction', 'DESC'));

        $allowedSorts = ['username', 'email', 'role', 'created_at'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }

        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            $direction = 'DESC';
        }

        $users = $this->userRepository->findBySearchAndSort($search, $sort, $direction);

        $roleDistribution = $this->userRepository->getRoleDistribution();
        $ratingDistributionRaw = $this->avisRepository->getRatingDistribution();
        $monthlyRegistrations = $this->userRepository->getMonthlyRegistrations(6);

        $ratingsMap = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        foreach ($ratingDistributionRaw as $row) {
            if (isset($ratingsMap[$row['rating']])) {
                $ratingsMap[$row['rating']] = $row['total'];
            }
        }
        $ratingMax = max(1, ...array_values($ratingsMap));

        $roleTotal = array_sum(array_map(static fn (array $role): int => $role['total'], $roleDistribution));

        $stats = [
            'total_users' => $this->userRepository->countAll(),
            'active_users' => $this->userRepository->countActive(),
            'inactive_users' => $this->userRepository->countInactive(),
            'total_avis' => $this->avisRepository->countAll(),
            'verified_avis' => $this->avisRepository->countVerified(),
            'unverified_avis' => $this->avisRepository->countUnverified(),
            'reviewers_count' => $this->avisRepository->countDistinctReviewers(),
            'average_rating' => $this->avisRepository->getAverageRating(),
            'role_total' => $roleTotal,
        ];

        $rolePieChart = $this->buildPieSlices($roleDistribution, 130, 130, 95);
        $verificationPieChart = $this->buildPieSlices([
            ['role' => 'Active', 'total' => $stats['active_users']],
            ['role' => 'Inactive', 'total' => $stats['inactive_users']],
        ], 110, 110, 80);
        $monthlyLineChart = $this->buildLineChartPoints($monthlyRegistrations, 600, 240);

        $roleChartUri = $this->buildPieChartImageUri($rolePieChart, 260, 260, 38, (string) $stats['role_total'], 'roles');
        $verificationChartUri = $this->buildPieChartImageUri($verificationPieChart, 220, 220, 34, '', '');
        $ratingsChartUri = $this->buildRatingsBarChartImageUri($ratingsMap, 350, 240);
        $monthlyChartUri = $this->buildMonthlyLineChartImageUri($monthlyLineChart, 620, 260);

        $html = $this->renderView('back/user/export_pdf.html.twig', [
            'users' => $users,
            'search' => $search,
            'sort' => $sort,
            'direction' => $direction,
            'role_distribution' => $roleDistribution,
            'ratings_map' => $ratingsMap,
            'rating_max' => $ratingMax,
            'monthly_registrations' => $monthlyRegistrations,
            'role_pie_chart' => $rolePieChart,
            'verification_pie_chart' => $verificationPieChart,
            'monthly_line_chart' => $monthlyLineChart,
            'role_chart_uri' => $roleChartUri,
            'verification_chart_uri' => $verificationChartUri,
            'ratings_chart_uri' => $ratingsChartUri,
            'monthly_chart_uri' => $monthlyChartUri,
            'stats' => $stats,
            'generated_at' => new \DateTimeImmutable(),
        ]);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $response = new Response($dompdf->output());
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', 'attachment; filename="users-analytics.pdf"');

        return $response;
    }

    /**
     * @param array<int, array{role: string, total: int}> $distribution
     * @return array{cx: int, cy: int, radius: int, slices: array<int, array{label: string, value: int, percent: float, color: string, path: string|null, full: bool}>}
     */
    private function buildPieSlices(array $distribution, int $cx, int $cy, int $radius): array
    {
        $palette = ['#2563eb', '#f97316', '#10b981', '#8b5cf6', '#ef4444', '#14b8a6', '#eab308'];
        $total = array_sum(array_map(static fn (array $item): int => max(0, (int) ($item['total'] ?? 0)), $distribution));

        if ($total <= 0) {
            return [
                'cx' => $cx,
                'cy' => $cy,
                'radius' => $radius,
                'slices' => [],
            ];
        }

        $slices = [];
        $startAngle = -90.0;
        $index = 0;

        foreach ($distribution as $item) {
            $value = max(0, (int) ($item['total'] ?? 0));
            if ($value === 0) {
                ++$index;
                continue;
            }

            $label = (string) ($item['role'] ?? 'Unknown');
            $color = $palette[$index % count($palette)];
            $percent = ($value / $total) * 100;
            $sweep = ($value / $total) * 360;

            if ($percent >= 100) {
                $slices[] = [
                    'label' => $label,
                    'value' => $value,
                    'percent' => round($percent, 2),
                    'color' => $color,
                    'path' => null,
                    'full' => true,
                ];
                break;
            }

            $endAngle = $startAngle + $sweep;
            $largeArcFlag = $sweep > 180 ? 1 : 0;

            $x1 = $cx + $radius * cos(deg2rad($startAngle));
            $y1 = $cy + $radius * sin(deg2rad($startAngle));
            $x2 = $cx + $radius * cos(deg2rad($endAngle));
            $y2 = $cy + $radius * sin(deg2rad($endAngle));

            $path = sprintf(
                'M %d %d L %.2f %.2f A %d %d 0 %d 1 %.2f %.2f Z',
                $cx,
                $cy,
                $x1,
                $y1,
                $radius,
                $radius,
                $largeArcFlag,
                $x2,
                $y2
            );

            $slices[] = [
                'label' => $label,
                'value' => $value,
                'percent' => round($percent, 2),
                'color' => $color,
                'path' => $path,
                'full' => false,
            ];

            $startAngle = $endAngle;
            ++$index;
        }

        return [
            'cx' => $cx,
            'cy' => $cy,
            'radius' => $radius,
            'slices' => $slices,
        ];
    }

    /**
     * @param array<int, array{period: string, total: int}> $monthlyRegistrations
     * @return array{width: int, height: int, points: string, nodes: array<int, array{x: float, y: float, label: string, value: int}>}
     */
    private function buildLineChartPoints(array $monthlyRegistrations, int $width, int $height): array
    {
        if ($monthlyRegistrations === []) {
            return [
                'width' => $width,
                'height' => $height,
                'points' => '',
                'nodes' => [],
            ];
        }

        $paddingX = 40;
        $paddingY = 25;
        $plotWidth = $width - ($paddingX * 2);
        $plotHeight = $height - ($paddingY * 2);
        $maxValue = max(1, ...array_map(static fn (array $row): int => (int) $row['total'], $monthlyRegistrations));
        $count = count($monthlyRegistrations);

        $nodes = [];
        $parts = [];

        foreach ($monthlyRegistrations as $index => $row) {
            $x = $paddingX + ($count === 1 ? $plotWidth / 2 : ($index * ($plotWidth / ($count - 1))));
            $y = $paddingY + $plotHeight - (((int) $row['total'] / $maxValue) * $plotHeight);

            $nodes[] = [
                'x' => round($x, 2),
                'y' => round($y, 2),
                'label' => (string) $row['period'],
                'value' => (int) $row['total'],
            ];
            $parts[] = round($x, 2) . ',' . round($y, 2);
        }

        return [
            'width' => $width,
            'height' => $height,
            'points' => implode(' ', $parts),
            'nodes' => $nodes,
        ];
    }

    /**
     * @param array{cx: int, cy: int, radius: int, slices: array<int, array{label: string, value: int, percent: float, color: string, path: string|null, full: bool}>} $pie
     */
    private function buildPieChartImageUri(array $pie, int $width, int $height, int $innerRadius, string $centerTopText, string $centerBottomText): string
    {
        $parts = [
            sprintf('<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d">', $width, $height, $width, $height),
        ];

        if ($pie['slices'] !== []) {
            foreach ($pie['slices'] as $slice) {
                if ($slice['full']) {
                    $parts[] = sprintf(
                        '<circle cx="%d" cy="%d" r="%d" fill="%s"/>',
                        $pie['cx'],
                        $pie['cy'],
                        $pie['radius'],
                        $slice['color']
                    );
                } elseif ($slice['path'] !== null) {
                    $parts[] = sprintf('<path d="%s" fill="%s"/>', $slice['path'], $slice['color']);
                }
            }

            $parts[] = sprintf('<circle cx="%d" cy="%d" r="%d" fill="#ffffff"/>', $pie['cx'], $pie['cy'], $innerRadius);

            if ($centerTopText !== '') {
                $parts[] = sprintf(
                    '<text x="%d" y="%d" text-anchor="middle" font-size="13" font-family="DejaVu Sans" font-weight="700" fill="#111827">%s</text>',
                    $pie['cx'],
                    $pie['cy'] - 2,
                    htmlspecialchars($centerTopText, ENT_QUOTES)
                );
            }

            if ($centerBottomText !== '') {
                $parts[] = sprintf(
                    '<text x="%d" y="%d" text-anchor="middle" font-size="10" font-family="DejaVu Sans" fill="#6b7280">%s</text>',
                    $pie['cx'],
                    $pie['cy'] + 14,
                    htmlspecialchars($centerBottomText, ENT_QUOTES)
                );
            }
        }

        $parts[] = '</svg>';

        return 'data:image/svg+xml;base64,' . base64_encode(implode('', $parts));
    }

    /**
     * @param array<int, int> $ratingsMap
     */
    private function buildRatingsBarChartImageUri(array $ratingsMap, int $width, int $height): string
    {
        $max = max(1, ...array_values($ratingsMap));
        $parts = [
            sprintf('<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d">', $width, $height, $width, $height),
            '<line x1="35" y1="200" x2="330" y2="200" stroke="#cbd5e1" stroke-width="1"/>',
        ];

        for ($rating = 1; $rating <= 5; ++$rating) {
            $value = (int) ($ratingsMap[$rating] ?? 0);
            $barHeight = ($value / $max) * 160;
            $x = 45 + (($rating - 1) * 56);
            $y = 200 - $barHeight;

            $parts[] = sprintf('<rect x="%.2f" y="%.2f" width="32" height="%.2f" fill="#0ea5a4"/>', $x, $y, $barHeight);
            $parts[] = sprintf('<text x="%.2f" y="215" text-anchor="middle" font-size="11" font-family="DejaVu Sans" fill="#64748b">%d</text>', $x + 16, $rating);
            $parts[] = sprintf('<text x="%.2f" y="%.2f" text-anchor="middle" font-size="10" font-family="DejaVu Sans" fill="#0f172a">%d</text>', $x + 16, $y - 5, $value);
        }

        $parts[] = '</svg>';

        return 'data:image/svg+xml;base64,' . base64_encode(implode('', $parts));
    }

    /**
     * @param array{width: int, height: int, points: string, nodes: array<int, array{x: float, y: float, label: string, value: int}>} $line
     */
    private function buildMonthlyLineChartImageUri(array $line, int $width, int $height): string
    {
        $parts = [
            sprintf('<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d">', $width, $height, $width, $height),
            '<line x1="40" y1="215" x2="560" y2="215" stroke="#cbd5e1" stroke-width="1"/>',
            '<line x1="40" y1="20" x2="40" y2="215" stroke="#cbd5e1" stroke-width="1"/>',
        ];

        if ($line['points'] !== '') {
            $parts[] = sprintf('<polyline fill="none" stroke="#2563eb" stroke-width="3" points="%s"/>', $line['points']);
        }

        foreach ($line['nodes'] as $node) {
            $parts[] = sprintf('<circle cx="%.2f" cy="%.2f" r="4" fill="#2563eb"/>', $node['x'], $node['y']);
            $parts[] = sprintf('<text x="%.2f" y="%.2f" text-anchor="middle" font-size="10" font-family="DejaVu Sans" fill="#0f172a">%d</text>', $node['x'], $node['y'] - 8, $node['value']);
            $parts[] = sprintf('<text x="%.2f" y="232" text-anchor="middle" font-size="9" font-family="DejaVu Sans" fill="#64748b">%s</text>', $node['x'], htmlspecialchars($node['label'], ENT_QUOTES));
        }

        $parts[] = '</svg>';

        return 'data:image/svg+xml;base64,' . base64_encode(implode('', $parts));
    }

    #[Route('/user/{id}', name: 'app_admin_user_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function userShow(User $user): Response
    {
        return $this->render('back/user/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/user/{id}/edit', name: 'app_admin_user_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function userEdit(Request $request, User $user): Response
    {
        if ($request->isMethod('POST')) {
            // Validate CSRF token
            $token = $request->request->get('_token');
            if (!$this->isCsrfTokenValid('edit_user', $token)) {
                $this->addFlash('error', 'Invalid CSRF token.');
                return $this->redirectToRoute('app_admin_user_show', ['id' => $user->getUser_id()]);
            }

            $isActive = $request->request->get('is_active');
            $user->setIs_active((bool)$isActive);

            $this->entityManager->flush();
            $this->addFlash('success', 'User updated successfully!');

            return $this->redirectToRoute('app_admin_user_show', ['id' => $user->getUser_id()]);
        }

        return $this->render('back/user/edit.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/avis', name: 'app_admin_avis_index', methods: ['GET'])]
    public function avisIndex(): Response
    {
        $avis = $this->avisRepository->findAllWithUser();

        return $this->render('back/avis/index.html.twig', [
            'avis' => $avis,
        ]);
    }

    #[Route('/avis/{id}', name: 'app_admin_avis_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function avisShow(Avis $avi): Response
    {
        return $this->render('back/avis/show.html.twig', [
            'avi' => $avi,
        ]);
    }

    #[Route('/avis/{id}/edit', name: 'app_admin_avis_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function avisEdit(Request $request, Avis $avi): Response
    {
        if ($request->isMethod('POST')) {
            // Validate CSRF token
            $token = $request->request->get('_token');
            if (!$this->isCsrfTokenValid('edit_avis', $token)) {
                $this->addFlash('error', 'Invalid CSRF token.');
                return $this->redirectToRoute('app_admin_avis_show', ['id' => $avi->getAvis_id()]);
            }

            $isVerified = $request->request->get('is_verified');
            $avi->setIs_verified((bool)$isVerified);

            $this->entityManager->flush();
            $this->addFlash('success', 'Review updated successfully!');

            return $this->redirectToRoute('app_admin_avis_show', ['id' => $avi->getAvis_id()]);
        }

        return $this->render('back/avis/edit.html.twig', [
            'avi' => $avi,
        ]);
    }

    #[Route('/avis/{id}/delete', name: 'app_admin_avis_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function avisDelete(Request $request, Avis $avi): Response
    {
        // Validate CSRF token
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete_avis', $token)) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_admin_avis_index');
        }

        $this->entityManager->remove($avi);
        $this->entityManager->flush();
        $this->addFlash('success', 'Review deleted successfully!');

        return $this->redirectToRoute('app_admin_avis_index');
    }
}
