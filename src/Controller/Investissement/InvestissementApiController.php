<?php

namespace App\Controller\Investissement;

use App\Entity\Investissement\Investment;
use App\Entity\UsersAvis\User;
use App\Entity\Investissement\Project;
use App\Repository\InvestmentRepository;
use App\Repository\NegotiationRepository;
use App\Repository\ProjectRepository;
use App\Service\Investissement\DealWorkflowService;
use App\Service\Investissement\RoiSimulatorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * API JSON pure pour le module investissement.
 *
 * Toutes les routes retournent du JSON — pas de Twig.
 * Routes publiques : GET /api/investissement/roi-simuler
 * Routes protégées : reste (connexion obligatoire)
 */
#[Route('/api/investissement')]
class InvestissementApiController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private ProjectRepository      $projectRepo,
        private InvestmentRepository   $investmentRepo,
        private NegotiationRepository  $negRepo,
        private DealWorkflowService    $dealWorkflow,
        private RoiSimulatorService    $roiSimulator,
    ) {}

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function requireAuth(): ?JsonResponse
    {
        if (!$this->getUser()) {
            return $this->json(['error' => 'Non authentifié.'], 401);
        }
        return null;
    }

    private function userId(): int
    {
        return (int) $this->getUser()?->getUserId();
    }

    // =========================================================================
    //  ROI SIMULATOR
    // =========================================================================

    /**
     * GET /api/investissement/roi-simuler
     *
     * Paramètres (query string) :
     *   montant  float   (obligatoire)
     *   type     string  prise_participation | pret_convertible | pret_simple | don
     *   duree    string  3m | 6m | 12m | 24m | 36m | 60m
     *   secteur  string  (optionnel)
     *
     * Exemple : /api/investissement/roi-simuler?montant=50000&type=prise_participation&duree=24m&secteur=tech
     */
    #[Route('/roi-simuler', name: 'api_investissement_roi_simuler', methods: ['GET'])]
    public function roiSimuler(Request $request): JsonResponse
    {
        $montant = (float) $request->query->get('montant', 0);
        $type    = $request->query->get('type', 'prise_participation');
        $duree   = $request->query->get('duree', '12m');
        $secteur = $request->query->get('secteur', '');

        if ($montant <= 0) {
            return $this->json(['error' => 'Le montant doit être positif.'], 422);
        }

        $typesValides = ['prise_participation', 'pret_convertible', 'pret_simple', 'don'];
        if (!in_array($type, $typesValides, true)) {
            return $this->json(['error' => 'Type invalide. Valeurs acceptées : ' . implode(', ', $typesValides)], 422);
        }

        $dureesValides = ['3m', '6m', '12m', '24m', '36m', '60m'];
        if (!in_array($duree, $dureesValides, true)) {
            return $this->json(['error' => 'Durée invalide. Valeurs acceptées : ' . implode(', ', $dureesValides)], 422);
        }

        try {
            $result = $this->roiSimulator->simulate($montant, $type, $duree, $secteur);
        } catch (\Throwable) {
            return $this->json(['error' => 'Erreur du simulateur ROI.'], 503);
        }

        return $this->json($result);
    }

    // =========================================================================
    //  GESTION DES PROJETS (startup)
    // =========================================================================

    /**
     * POST /api/investissement/projet/{id}/publier
     * Passe un projet de "pending" à "in_progress" (ouvert aux investissements).
     * Réservé à la startup propriétaire du projet.
     */
    #[Route('/projet/{id}/publier', name: 'api_investissement_projet_publier', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function publierProjet(int $id, Request $request): JsonResponse
    {
        if ($err = $this->requireAuth()) return $err;

        if (!$this->isCsrfTokenValid('publier_projet_' . $id, $request->request->get('_token'))) {
            return $this->json(['error' => 'Token de sécurité invalide.'], 403);
        }

        $projet = $this->projectRepo->find($id);
        if (!$projet) {
            return $this->json(['error' => 'Projet introuvable.'], 404);
        }

        if ($projet->getUser()?->getUserId() !== $this->userId()) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        if ($projet->getStatus() !== Project::STATUS_BROUILLON) {
            return $this->json(['error' => 'Seuls les projets en attente peuvent être publiés.'], 422);
        }

        $projet->setStatus(Project::STATUS_EN_COURS);
        $this->em->flush();

        return $this->json([
            'success'    => true,
            'project_id' => $projet->getProject_id(),
            'status'     => $projet->getStatus(),
            'message'    => 'Projet publié avec succès.',
        ]);
    }

    /**
     * POST /api/investissement/projet/{id}/archiver
     * Passe le projet au statut "completed" (fermé aux nouvelles demandes).
     * Réservé à la startup propriétaire.
     */
    #[Route('/projet/{id}/archiver', name: 'api_investissement_projet_archiver', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function archiverProjet(int $id, Request $request): JsonResponse
    {
        if ($err = $this->requireAuth()) return $err;

        if (!$this->isCsrfTokenValid('archiver_projet_' . $id, $request->request->get('_token'))) {
            return $this->json(['error' => 'Token de sécurité invalide.'], 403);
        }

        $projet = $this->projectRepo->find($id);
        if (!$projet) {
            return $this->json(['error' => 'Projet introuvable.'], 404);
        }

        if ($projet->getUser()?->getUserId() !== $this->userId()) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $statutsArchivables = [Project::STATUS_BROUILLON, Project::STATUS_EN_COURS, Project::STATUS_FINANCE];
        if (!in_array($projet->getStatus(), $statutsArchivables, true)) {
            return $this->json(['error' => 'Ce projet ne peut plus être archivé.'], 422);
        }

        $projet->setStatus(Project::STATUS_FERME);
        $this->em->flush();

        return $this->json([
            'success'    => true,
            'project_id' => $projet->getProject_id(),
            'status'     => $projet->getStatus(),
            'message'    => 'Projet archivé.',
        ]);
    }

    /**
     * POST /api/investissement/projet/{id}/financer
     * Marque le projet comme "funded" (objectif atteint).
     * Réservé à la startup propriétaire.
     */
    #[Route('/projet/{id}/financer', name: 'api_investissement_projet_financer', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function financerProjet(int $id, Request $request): JsonResponse
    {
        if ($err = $this->requireAuth()) return $err;

        if (!$this->isCsrfTokenValid('financer_projet_' . $id, $request->request->get('_token'))) {
            return $this->json(['error' => 'Token de sécurité invalide.'], 403);
        }

        $projet = $this->projectRepo->find($id);
        if (!$projet) {
            return $this->json(['error' => 'Projet introuvable.'], 404);
        }

        if ($projet->getUser()?->getUserId() !== $this->userId()) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $projet->setStatus(Project::STATUS_FINANCE);
        $this->em->flush();

        return $this->json([
            'success'    => true,
            'project_id' => $projet->getProject_id(),
            'status'     => $projet->getStatus(),
            'message'    => 'Projet marqué comme financé.',
        ]);
    }

    // =========================================================================
    //  GESTION DES INVESTISSEMENTS (startup accepte/refuse)
    // =========================================================================

    /**
     * POST /api/investissement/investment/{id}/accepter
     * La startup accepte un investissement reçu :
     *   - passe statut Investment → "accepte"
     *   - crée un Deal via DealWorkflowService::createFromInvestment()
     */
    #[Route('/investment/{id}/accepter', name: 'api_investissement_investment_accepter', methods: ['POST'])]
    public function accepterInvestissement(
        #[MapEntity(mapping: ['id' => 'investment_id'])]
        Investment $investment,
        Request    $request
    ): JsonResponse {
        if ($err = $this->requireAuth()) return $err;

        if (!$this->isCsrfTokenValid('accepter_inv_' . $investment->getInvestment_id(), $request->request->get('_token'))) {
            return $this->json(['error' => 'Token de sécurité invalide.'], 403);
        }

        $projet = $investment->getProject();
        if (!$projet || $projet->getUser()?->getUserId() !== $this->userId()) {
            return $this->json(['error' => 'Accès refusé. Seule la startup propriétaire peut accepter cet investissement.'], 403);
        }

        if ($investment->getStatut() !== 'en_attente') {
            return $this->json(['error' => 'Cet investissement ne peut plus être accepté (statut : ' . $investment->getStatut() . ').'], 422);
        }

        // Crée le deal via le service existant
        $deal = $this->dealWorkflow->createFromInvestment($investment, $this->userId());

        return $this->json([
            'success'       => true,
            'investment_id' => $investment->getInvestment_id(),
            'deal_id'       => $deal->getDeal_id(),
            'deal_status'   => $deal->getStatus(),
            'message'       => 'Investissement accepté. Deal créé.',
        ]);
    }

    /**
     * POST /api/investissement/investment/{id}/refuser
     * La startup refuse un investissement reçu.
     */
    #[Route('/investment/{id}/refuser', name: 'api_investissement_investment_refuser', methods: ['POST'])]
    public function refuserInvestissement(
        #[MapEntity(mapping: ['id' => 'investment_id'])]
        Investment $investment,
        Request    $request
    ): JsonResponse {
        if ($err = $this->requireAuth()) return $err;

        if (!$this->isCsrfTokenValid('refuser_inv_' . $investment->getInvestment_id(), $request->request->get('_token'))) {
            return $this->json(['error' => 'Token de sécurité invalide.'], 403);
        }

        $projet = $investment->getProject();
        if (!$projet || $projet->getUser()?->getUserId() !== $this->userId()) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        if ($investment->getStatut() !== 'en_attente') {
            return $this->json(['error' => 'Cet investissement ne peut plus être refusé.'], 422);
        }

        $investment->setStatut('refuse');
        $this->em->flush();

        return $this->json([
            'success'       => true,
            'investment_id' => $investment->getInvestment_id(),
            'statut'        => 'refuse',
            'message'       => 'Investissement refusé.',
        ]);
    }

    // =========================================================================
    //  STATS GLOBALES (dashboard JSON)
    // =========================================================================

    /**
     * GET /api/investissement/stats
     *
     * Retourne des statistiques agrégées pour l'utilisateur connecté :
     *   - investisseur : total investi, nb projets, répartition par statut
     *   - startup      : total levé, nb investisseurs, nb négociations ouvertes
     */
    #[Route('/stats', name: 'api_investissement_stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        if ($err = $this->requireAuth()) return $err;

        $user     = $this->getUser();
        $userType = ($user instanceof User ? $user->getUserType() : null);

        if ($userType === 'investisseur') {
            $total        = $this->investmentRepo->getTotalInvestedByUser($user);
            $nbProjets    = $this->investmentRepo->countDistinctProjectsByUser($user);
            $parStatut    = $this->investmentRepo->countByStatutForUser($user);
            $negOuvertes  = count($this->negRepo->findByInvestor($user));

            return $this->json([
                'user_type'             => 'investisseur',
                'total_investi_tnd'     => $total,
                'nb_projets'            => $nbProjets,
                'repartition_statut'    => $parStatut,
                'negociations_ouvertes' => $negOuvertes,
            ]);
        }

        if ($userType === 'startup') {
            $projets      = $this->projectRepo->findBy(['user' => $user]);
            $totalLeve    = $this->investmentRepo->getTotalInvestedByProjects($projets);
            $nbInvestisseurs = 0;
            foreach ($projets as $p) {
                $nbInvestisseurs += count($this->investmentRepo->findByProject($p));
            }
            $negOuvertes = count($this->negRepo->findByStartup($user));

            return $this->json([
                'user_type'             => 'startup',
                'nb_projets'            => count($projets),
                'total_leve_tnd'        => $totalLeve,
                'nb_investisseurs'      => $nbInvestisseurs,
                'negociations_ouvertes' => $negOuvertes,
            ]);
        }

        return $this->json(['error' => 'Type d\'utilisateur non pris en charge.'], 422);
    }

    // =========================================================================
    //  DÉTAIL D'UN PROJET (JSON)
    // =========================================================================

    /**
     * GET /api/investissement/projet/{id}
     * Retourne les infos d'un projet + progression du financement.
     */
    #[Route('/projet/{id}', name: 'api_investissement_projet_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function projetShow(int $id): JsonResponse
    {
        $projet = $this->projectRepo->find($id);
        if (!$projet) {
            return $this->json(['error' => 'Projet introuvable.'], 404);
        }

        $totalInvesti = $this->investmentRepo->getTotalInvestedByProject($projet);
        $budget       = $projet->getRequiredBudget() ?? 0;
        $pourcentage  = $budget > 0 ? min(100, round(($totalInvesti / $budget) * 100, 1)) : 0;

        $negCount = count($this->negRepo->findBy([
            'project' => $projet,
            'status'  => 'open',
        ]));

        return $this->json([
            'project_id'          => $projet->getProject_id(),
            'title'               => $projet->getTitle(),
            'status'              => $projet->getStatus(),
            'secteur'             => $projet->getSecteur(),
            'required_budget'     => $budget,
            'total_investi'       => $totalInvesti,
            'pourcentage'         => $pourcentage,
            'negociations_ouvertes' => $negCount,
            'startup'             => $projet->getUser() ? [
                'id'        => $projet->getUser()->getUserId(),
                'full_name' => $projet->getUser()->getFullName(),
            ] : null,
        ]);
    }
}
