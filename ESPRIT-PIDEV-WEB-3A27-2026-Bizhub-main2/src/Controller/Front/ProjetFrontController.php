<?php

namespace App\Controller\Front;

use App\Entity\Investissement\Project;
use App\Entity\UsersAvis\User;
use App\Form\Investissement\ProjetFrontType;
use App\Repository\ProjectRepository;
use App\Repository\InvestmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/front/projets')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class ProjetFrontController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private ProjectRepository $projectRepository,
        private InvestmentRepository $investmentRepository,
    ) {}

    /**
     * Startup: liste de ses propres projets
     * Investisseur: liste de tous les projets disponibles
     */
    #[Route('', name: 'app_front_projet_index', methods: ['GET'])]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($user->getUserType() === 'startup') {
            $projets = $this->projectRepository->findBy(['user' => $user], ['created_at' => 'DESC']);
            return $this->render('front/projet/index.html.twig', [
                'projets' => $projets,
            ]);
        }

        // Investisseur: tous les projets
        $projets = $this->projectRepository->findAllWithInvestments();
        return $this->render('front/projet/browse.html.twig', [
            'projets' => $projets,
        ]);
    }

    /**
     * Startup uniquement: créer un nouveau projet
     */
    #[Route('/nouveau', name: 'app_front_projet_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($user->getUserType() !== 'startup') {
            $this->addFlash('error', 'Seules les startups peuvent créer un projet.');
            return $this->redirectToRoute('app_front_projet_index');
        }

        $projet = new Project();
        $projet->setUser($user);
        $projet->setCreatedAt(new \DateTime());
        $projet->setStatus('pending');

        $form = $this->createForm(ProjetFrontType::class, $projet);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($projet);
            $this->em->flush();

            $this->addFlash('success', 'Projet créé avec succès !');
            return $this->redirectToRoute('app_front_projet_show', ['id' => $projet->getProject_id()]);
        }

        return $this->render('front/projet/new.html.twig', [
            'form' => $form,
        ]);
    }

    /**
     * Détail d'un projet (accessible à tous les utilisateurs connectés)
     */
    #[Route('/{id}', name: 'app_front_projet_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $projet = $this->projectRepository->find($id);

        if (!$projet) {
            $this->addFlash('error', 'Projet introuvable.');
            return $this->redirectToRoute('app_front_projet_index');
        }

        /** @var User $user */
        $user = $this->getUser();

        $investissements = $this->investmentRepository->findByProject($projet);
        $totalInvesti = $this->investmentRepository->getTotalInvestedByProject($projet);
        $pourcentage = $projet->getRequiredBudget() > 0
            ? min(100, round(($totalInvesti / $projet->getRequiredBudget()) * 100))
            : 0;

        // Vérifier si l'investisseur a déjà investi dans ce projet
        $dejaInvesti = false;
        if ($user->getUserType() === 'investisseur') {
            foreach ($investissements as $inv) {
                if ($inv->getUser() && $inv->getUser()->getUserId() === $user->getUserId()) {
                    $dejaInvesti = true;
                    break;
                }
            }
        }

        return $this->render('front/projet/show.html.twig', [
            'projet' => $projet,
            'investissements' => $investissements,
            'total_investi' => $totalInvesti,
            'pourcentage' => $pourcentage,
            'deja_investi' => $dejaInvesti,
            'is_owner' => ($projet->getUser() && $projet->getUser()->getUserId() === $user->getUserId()),
        ]);
    }

    /**
     * Startup uniquement: modifier son projet
     */
    #[Route('/{id}/modifier', name: 'app_front_projet_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, int $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $projet = $this->projectRepository->find($id);

        if (!$projet) {
            $this->addFlash('error', 'Projet introuvable.');
            return $this->redirectToRoute('app_front_projet_index');
        }

        if (!$projet->getUser() || $projet->getUser()->getUserId() !== $user->getUserId()) {
            $this->addFlash('error', 'Vous ne pouvez modifier que vos propres projets.');
            return $this->redirectToRoute('app_front_projet_index');
        }

        $form = $this->createForm(ProjetFrontType::class, $projet);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->addFlash('success', 'Projet mis à jour avec succès !');
            return $this->redirectToRoute('app_front_projet_show', ['id' => $projet->getProject_id()]);
        }

        return $this->render('front/projet/edit.html.twig', [
            'form' => $form,
            'projet' => $projet,
        ]);
    }

    /**
     * Startup uniquement: supprimer son projet
     */
    #[Route('/{id}/supprimer', name: 'app_front_projet_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, int $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $projet = $this->projectRepository->find($id);

        if (!$projet) {
            $this->addFlash('error', 'Projet introuvable.');
            return $this->redirectToRoute('app_front_projet_index');
        }

        if (!$projet->getUser() || $projet->getUser()->getUserId() !== $user->getUserId()) {
            $this->addFlash('error', 'Vous ne pouvez supprimer que vos propres projets.');
            return $this->redirectToRoute('app_front_projet_index');
        }

        if (!$this->isCsrfTokenValid('delete_projet_' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_front_projet_index');
        }

        $this->em->remove($projet);
        $this->em->flush();

        $this->addFlash('success', 'Projet supprimé avec succès.');
        return $this->redirectToRoute('app_front_projet_index');
    }
}
