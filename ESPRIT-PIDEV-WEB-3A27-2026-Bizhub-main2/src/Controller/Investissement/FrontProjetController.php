<?php

namespace App\Controller\Investissement;

use App\Entity\Investissement\Project;
// Project::STATUTS et Project::SECTEURS utilisés dans index()
use App\Form\Investissement\ProjetType;
use App\Repository\InvestmentRepository;
use App\Repository\NegotiationRepository;
use App\Repository\ProjectRepository;
use App\Service\AI\AiProjectService;
use App\Service\Investissement\DealWorkflowService;
use App\Service\Investissement\ProjectAdvisorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/front/investissement')]
class FrontProjetController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProjectRepository      $projectRepository,
        private InvestmentRepository   $investmentRepository,
        private NegotiationRepository  $negotiationRepo,
        private DealWorkflowService    $workflow,
    ) {}

    /**
     * Main "Investissement" page in the nav:
     * - Startups see their own projects (index)
     * - Everyone else sees the public browser
     */
    #[Route('', name: 'app_front_projet_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $user = $this->getUser();

        if ($user && $user->getUserType() === 'startup') {
            $projets = $this->projectRepository->findBy(
                ['user' => $user],
                ['created_at' => 'DESC']
            );
            return $this->render('front/projet/index.html.twig', [
                'projets' => $projets,
            ]);
        }

        // Récupérer les filtres depuis l'URL (?q=...&secteur=...&statut=...&budget_min=...&budget_max=...)
        $filters = [
            'q'          => $request->query->get('q', ''),
            'secteur'    => $request->query->get('secteur', ''),
            'statut'     => $request->query->get('statut', ''),
            'budget_min' => $request->query->get('budget_min', ''),
            'budget_max' => $request->query->get('budget_max', ''),
        ];

        // Si aucun filtre, on affiche tout ; sinon on filtre
        $hasFilters = array_filter($filters, fn($v) => $v !== '');
        $projets = $hasFilters
            ? $this->projectRepository->search($filters)
            : $this->projectRepository->findAllWithInvestments();

        // Statuts visibles dans le filtre investisseur (sans brouillon — privé)
        $statutsFiltre = array_filter(
            Project::STATUTS,
            fn($v) => $v !== Project::STATUS_BROUILLON
        );

        // Pour chaque projet, récupérer la négociation et le deal de l'investisseur connecté
        $browseNegMap  = [];
        $browseDealMap = [];
        if ($user && $user->getUserType() === 'investisseur') {
            foreach ($projets as $projet) {
                $neg = $this->negotiationRepo->findOneBy([
                    'project'  => $projet,
                    'investor' => $user,
                ]);
                $browseNegMap[$projet->getProject_id()] = $neg;
                $browseDealMap[$projet->getProject_id()] = $neg
                    ? $this->workflow->findDealByNegotiation($neg)
                    : null;
            }
        }

        return $this->render('front/projet/browse.html.twig', [
            'projets'       => $projets,
            'filters'       => $filters,
            'secteurs'      => Project::SECTEURS,
            'statuts'       => $statutsFiltre,
            'browse_neg_map'  => $browseNegMap,
            'browse_deal_map' => $browseDealMap,
        ]);
    }

    #[Route('/nouveau', name: 'app_front_projet_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $projet = new Project();
        $projet->setCreated_at(new \DateTime());
        $projet->setStatus(Project::STATUS_BROUILLON);

        if ($this->getUser()) {
            $projet->setUser($this->getUser());
        }

        $form = $this->createForm(ProjetType::class, $projet);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($projet);
            $this->entityManager->flush();

            $this->addFlash('success', 'Projet créé avec succès.');
            return $this->redirectToRoute('app_front_projet_index');
        }

        return $this->render('front/projet/new.html.twig', [
            'form'   => $form->createView(),
            'projet' => $projet,
        ]);
    }

    #[Route('/{id}', name: 'app_front_projet_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $projet = $this->projectRepository->find($id);
        if (!$projet) {
            $this->addFlash('error', 'Projet introuvable.');
            return $this->redirectToRoute('app_front_projet_index');
        }

        $investissements = $this->investmentRepository->findByProject($projet);
        $totalInvesti    = $this->investmentRepository->getTotalInvestedByProject($projet);
        $pourcentage     = $projet->getRequiredBudget() > 0
            ? min(100, round(($totalInvesti / $projet->getRequiredBudget()) * 100, 1))
            : 0;

        $user        = $this->getUser();
        $isOwner     = $user && $projet->getUser() && $projet->getUser()->getUserId() === $user->getUserId();
        $dejaInvesti = false;

        if ($user && !$isOwner) {
            foreach ($investissements as $inv) {
                if ($inv->getUser() && $inv->getUser()->getUserId() === $user->getUserId()) {
                    $dejaInvesti = true;
                    break;
                }
            }
        }

        // Maps for startup view (received investments)
        $negMap  = [];
        $dealMap = [];
        foreach ($investissements as $inv) {
            $neg = null;
            if ($inv->getProject() && $inv->getUser()) {
                $neg = $this->negotiationRepo->findOneBy([
                    'project'  => $inv->getProject(),
                    'investor' => $inv->getUser(),
                ]);
            }
            $negMap[$inv->getInvestment_id()]  = $neg;
            $dealMap[$inv->getInvestment_id()] = $this->workflow->findDealByInvestment($inv);
        }

        // Investor's own negotiation/deal on this project
        $myNeg  = null;
        $myDeal = null;
        $myInvestment = null;
        if ($user && !$isOwner) {
            $myNeg = $this->negotiationRepo->findOneBy([
                'project'  => $projet,
                'investor' => $user,
            ]);
            if ($myNeg) {
                $myDeal = $this->workflow->findDealByNegotiation($myNeg);
            }
            // Also find the investor's investment to link to negotiation creation
            foreach ($investissements as $inv) {
                if ($inv->getUser() && $inv->getUser()->getUserId() === $user->getUserId()) {
                    $myInvestment = $inv;
                    if (!$myDeal) {
                        $myDeal = $this->workflow->findDealByInvestment($inv);
                    }
                    break;
                }
            }
        }

        // Toutes les négociations liées à ce projet (pour la vue startup)
        $allNegotiations = $isOwner
            ? $this->negotiationRepo->findBy(['project' => $projet], ['created_at' => 'DESC'])
            : [];

        // Map deal par negotiation_id (pour la vue startup)
        $dealByNegMap = [];
        foreach ($allNegotiations as $neg) {
            $dealByNegMap[$neg->getNegotiation_id()] = $this->workflow->findDealByNegotiation($neg);
        }

        return $this->render('front/projet/show.html.twig', [
            'projet'           => $projet,
            'investissements'  => $investissements,
            'total_investi'    => $totalInvesti,
            'pourcentage'      => $pourcentage,
            'is_owner'         => $isOwner,
            'deja_investi'     => $dejaInvesti,
            'neg_map'          => $negMap,
            'deal_map'         => $dealMap,
            'my_neg'           => $myNeg,
            'my_deal'          => $myDeal,
            'my_investment'    => $myInvestment,
            'all_negotiations' => $allNegotiations,
            'deal_by_neg_map'  => $dealByNegMap,
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_front_projet_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, int $id): Response
    {
        $projet = $this->projectRepository->find($id);
        if (!$projet) {
            $this->addFlash('error', 'Projet introuvable.');
            return $this->redirectToRoute('app_front_projet_index');
        }

        $form = $this->createForm(ProjetType::class, $projet);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'Projet modifié.');
            return $this->redirectToRoute('app_front_projet_show', ['id' => $projet->getProject_id()]);
        }

        return $this->render('front/projet/edit.html.twig', [
            'form'   => $form->createView(),
            'projet' => $projet,
        ]);
    }

    /**
     * Endpoint AJAX : améliore la description d'un projet via IA.
     * Retourne JSON { improved: string } ou { error: string }.
     */
    #[Route('/ai/ameliorer-description', name: 'app_front_projet_ai_improve', methods: ['POST'])]
    public function aiImproveDescription(Request $request, AiProjectService $aiService): JsonResponse
    {
        if (!$this->isCsrfTokenValid('ai_improve_projet', $request->request->get('_token', ''))) {
            return $this->json(['error' => 'Token de sécurité invalide. Rechargez la page.'], 403);
        }

        $description = trim((string) $request->request->get('description', ''));

        if (strlen($description) < 10) {
            return $this->json(['error' => 'La description est trop courte pour être améliorée (minimum 10 caractères).']);
        }

        // Optional: load existing project for richer context (edit form)
        $project    = null;
        $projectId  = (int) $request->request->get('project_id', 0);
        if ($projectId > 0) {
            $project = $this->projectRepository->find($projectId);
            // Security: only owner can use context of their project
            $user = $this->getUser();
            if ($project && (!$user || !$project->getUser() || $project->getUser()->getUserId() !== $user->getUserId())) {
                $project = null;
            }
        }

        try {
            $improved = $aiService->improveDescription($description, $project);
            return $this->json(['improved' => $improved]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()]);
        } catch (\Throwable) {
            return $this->json(['error' => 'Une erreur inattendue est survenue. Veuillez réessayer.']);
        }
    }

    // ── Coach IA ──────────────────────────────────────────────────────────────

    /**
     * Affiche le résultat de l'analyse IA (depuis la session) ou l'état vide.
     * Accessible uniquement par le propriétaire du projet.
     */
    #[Route('/{id}/coach-ia', name: 'app_front_projet_coach', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function coach(Request $request, int $id): Response
    {
        $projet = $this->projectRepository->find($id);
        if (!$projet) {
            $this->addFlash('error', 'Projet introuvable.');
            return $this->redirectToRoute('app_front_projet_index');
        }

        $user = $this->getUser();
        if (!$user || !$projet->getUser() || $projet->getUser()->getUserId() !== $user->getUserId()) {
            throw $this->createAccessDeniedException('Accès réservé au propriétaire du projet.');
        }

        $sessionKey = 'coach_analysis_' . $projet->getProject_id();
        $analysis   = $request->getSession()->get($sessionKey);

        return $this->render('front/projet/coach.html.twig', [
            'projet'   => $projet,
            'analysis' => $analysis,
        ]);
    }

    /**
     * Lance l'analyse IA, stocke le résultat en session et redirige vers la page coach.
     */
    #[Route('/{id}/coach-ia', name: 'app_front_projet_coach_analyze', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function coachAnalyze(Request $request, int $id, ProjectAdvisorService $advisor): Response
    {
        $projet = $this->projectRepository->find($id);
        if (!$projet) {
            $this->addFlash('error', 'Projet introuvable.');
            return $this->redirectToRoute('app_front_projet_index');
        }

        $user = $this->getUser();
        if (!$user || !$projet->getUser() || $projet->getUser()->getUserId() !== $user->getUserId()) {
            throw $this->createAccessDeniedException('Accès réservé au propriétaire du projet.');
        }

        if (!$this->isCsrfTokenValid('coach_analyze_' . $id, $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Token de sécurité invalide. Veuillez réessayer.');
            return $this->redirectToRoute('app_front_projet_coach', ['id' => $id]);
        }

        try {
            $analysis = $advisor->analyzeProject($projet);
            $request->getSession()->set('coach_analysis_' . $projet->getProject_id(), $analysis);
            $this->addFlash('success', 'Analyse IA générée avec succès !');
        } catch (\Throwable) {
            $this->addFlash('error', 'Une erreur est survenue lors de l\'analyse. Veuillez réessayer.');
        }

        return $this->redirectToRoute('app_front_projet_coach', ['id' => $id]);
    }

    /**
     * Applique la description améliorée générée par l'IA au projet.
     */
    #[Route('/{id}/apply-description', name: 'app_front_projet_apply_description', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function applyDescription(Request $request, int $id): Response
    {
        $projet = $this->projectRepository->find($id);
        if (!$projet) {
            $this->addFlash('error', 'Projet introuvable.');
            return $this->redirectToRoute('app_front_projet_index');
        }

        $user = $this->getUser();
        if (!$user || !$projet->getUser() || $projet->getUser()->getUserId() !== $user->getUserId()) {
            throw $this->createAccessDeniedException('Accès réservé au propriétaire du projet.');
        }

        if (!$this->isCsrfTokenValid('apply_description_' . $id, $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('app_front_projet_coach', ['id' => $id]);
        }

        $newDescription = trim($request->request->get('description', ''));
        if (strlen($newDescription) >= 20) {
            $projet->setDescription($newDescription);
            $this->entityManager->flush();
            $this->addFlash('success', 'Description améliorée appliquée au projet.');
        } else {
            $this->addFlash('error', 'La description fournie est trop courte.');
        }

        return $this->redirectToRoute('app_front_projet_coach', ['id' => $id]);
    }

    // ── Suppression ───────────────────────────────────────────────────────────

    #[Route('/{id}/supprimer', name: 'app_front_projet_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, int $id): Response
    {
        $projet = $this->projectRepository->find($id);
        if ($projet && $this->isCsrfTokenValid('delete_projet_' . $id, $request->request->get('_token'))) {
            $this->entityManager->remove($projet);
            $this->entityManager->flush();
            $this->addFlash('success', 'Projet supprimé.');
        }
        return $this->redirectToRoute('app_front_projet_index');
    }

}

