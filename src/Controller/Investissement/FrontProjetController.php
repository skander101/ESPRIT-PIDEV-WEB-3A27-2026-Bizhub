<?php

namespace App\Controller\Investissement;

use App\Entity\Investissement\Investment;
use App\Entity\Investissement\Project;
// Project::STATUTS et Project::SECTEURS utilisés dans index()
use App\Form\Investissement\InvestissementFrontType;
use App\Form\Investissement\ProjetType;
use App\Repository\InvestmentRepository;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/front/investissement')]
class FrontProjetController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProjectRepository $projectRepository,
        private InvestmentRepository $investmentRepository,
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

        return $this->render('front/projet/browse.html.twig', [
            'projets'  => $projets,
            'filters'  => $filters,
            'secteurs' => Project::SECTEURS,
            'statuts'  => Project::STATUTS,
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

        return $this->render('front/projet/show.html.twig', [
            'projet'          => $projet,
            'investissements' => $investissements,
            'total_investi'   => $totalInvesti,
            'pourcentage'     => $pourcentage,
            'is_owner'        => $isOwner,
            'deja_investi'    => $dejaInvesti,
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

    #[Route('/{id}/investir', name: 'app_front_investir', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function investir(Request $request, int $id): Response
    {
        // 1. L'utilisateur doit être connecté
        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('warning', 'Vous devez être connecté pour investir.');
            return $this->redirectToRoute('app_login');
        }

        // 2. Le projet doit exister
        $projet = $this->projectRepository->find($id);
        if (!$projet) {
            $this->addFlash('error', 'Projet introuvable.');
            return $this->redirectToRoute('app_front_projet_index');
        }

        // 3. Seuls les projets publiés ou en cours acceptent des investissements
        $statutsOuverts = [Project::STATUS_PUBLIE, Project::STATUS_EN_COURS];
        if (!in_array($projet->getStatus(), $statutsOuverts)) {
            $this->addFlash('error', 'Ce projet n\'accepte pas d\'investissements pour l\'instant.');
            return $this->redirectToRoute('app_front_projet_show', ['id' => $id]);
        }

        // 4. Une startup ne peut pas investir dans son propre projet
        if ($projet->getUser() && $projet->getUser()->getUserId() === $user->getUserId()) {
            $this->addFlash('error', 'Vous ne pouvez pas investir dans votre propre projet.');
            return $this->redirectToRoute('app_front_projet_show', ['id' => $id]);
        }

        // 5. Créer l'investissement
        $investment = new Investment();
        $investment->setProject($projet);
        $investment->setUser($user);
        $investment->setCreated_at(new \DateTime());
        $investment->setInvestment_date(new \DateTime());

        $form = $this->createForm(InvestissementFrontType::class, $investment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($investment);
            $this->entityManager->flush();

            $this->addFlash('success', 'Investissement enregistré avec succès ! Merci pour votre confiance.');
            // Redirection vers "Mes investissements" après succès
            return $this->redirectToRoute('app_front_investissement_index');
        }

        $totalInvesti = $this->investmentRepository->getTotalInvestedByProject($projet);
        $pourcentage  = $projet->getRequiredBudget() > 0
            ? min(100, round(($totalInvesti / $projet->getRequiredBudget()) * 100, 1))
            : 0;

        return $this->render('front/investissement/invest.html.twig', [
            'form'          => $form->createView(),
            'projet'        => $projet,
            'total_investi' => $totalInvesti,
            'pourcentage'   => $pourcentage,
        ]);
    }
}
