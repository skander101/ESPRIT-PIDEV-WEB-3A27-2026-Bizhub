<?php

namespace App\Controller\Investissement;

use App\Entity\Investissement\Project;
use App\Repository\InvestmentRepository;
use App\Repository\ProjectRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/front/dashboard')]
class DashboardController extends AbstractController
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private InvestmentRepository $investmentRepository,
    ) {}

    #[Route('', name: 'app_biz_dashboard', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $type = $user->getUserType();

        if ($type === 'startup') {
            return $this->dashboardStartup($user);
        }

        return $this->dashboardInvestisseur($user);
    }

    // ── Dashboard Investisseur ────────────────────────────────────────────────

    private function dashboardInvestisseur($user): Response
    {
        // Statistiques globales
        $totalInvesti    = $this->investmentRepository->getTotalInvestedByUser($user);
        $nbInvestissements = $this->investmentRepository->countByUser($user);
        $nbProjets       = $this->investmentRepository->countDistinctProjectsByUser($user);

        // Répartition par statut : ['en_attente' => 3, 'accepte' => 2, ...]
        $parStatut = $this->investmentRepository->countByStatutForUser($user);

        // Derniers 5 investissements
        $derniers = $this->investmentRepository->findLastByUser($user, 5);

        // 4 projets en cours qui pourraient l'intéresser
        $projetsEnCours = $this->projectRepository->findEnCours(4);

        // Graphique : répartition par secteur (tous projets de la plateforme)
        $parSecteur = $this->projectRepository->countBySecteur();

        return $this->render('front/dashboard/investisseur.html.twig', [
            'total_investi'       => $totalInvesti,
            'nb_investissements'  => $nbInvestissements,
            'nb_projets'          => $nbProjets,
            'par_statut'          => $parStatut,
            'derniers'            => $derniers,
            'projets_en_cours'    => $projetsEnCours,
            'par_secteur'         => $parSecteur,
        ]);
    }

    // ── Dashboard Startup ─────────────────────────────────────────────────────

    private function dashboardStartup($user): Response
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
            'par_secteur'             => $parSecteur,
        ]);
    }
}
