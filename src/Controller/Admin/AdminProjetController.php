<?php

namespace App\Controller\Admin;

use App\Entity\Investissement\Project;
use App\Repository\ProjectRepository;
use App\Repository\InvestmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/back/investissement/projets')]
#[IsGranted('ROLE_ADMIN')]
class AdminProjetController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProjectRepository $projectRepository,
        private InvestmentRepository $investmentRepository,
    ) {}

    #[Route('', name: 'app_admin_projet_index', methods: ['GET'])]
    public function index(): Response
    {
        $projets = $this->projectRepository->findBy([], ['created_at' => 'DESC']);

        $totalProjets      = count($projets);
        $projetsEnAttente  = count(array_filter($projets, fn($p) => $p->getStatus() === 'pending'));
        $totalInvestments  = $this->investmentRepository->count([]);
        $montantTotal      = $this->investmentRepository->getTotalInvested();

        return $this->render('back/projet/index.html.twig', [
            'projets'           => $projets,
            'total_projets'     => $totalProjets,
            'projets_attente'   => $projetsEnAttente,
            'total_investments' => $totalInvestments,
            'montant_total'     => $montantTotal,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_projet_show', methods: ['GET'], requirements: ['id' => '\\d+'])]
    public function show(Project $projet): Response
    {
        $investments = $this->investmentRepository->findByProject($projet);
        $montant     = $this->investmentRepository->getTotalInvestedByProject($projet);

        return $this->render('back/projet/show.html.twig', [
            'projet'      => $projet,
            'investments' => $investments,
            'montant'     => $montant,
        ]);
    }

    #[Route('/{id}/statut', name: 'app_admin_projet_statut', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function changeStatut(Request $request, Project $projet): Response
    {
        if (!$this->isCsrfTokenValid('statut_projet_' . $projet->getProject_id(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_admin_projet_show', ['id' => $projet->getProject_id()]);
        }

        $statut = $request->request->get('statut');
        $allowed = ['pending', 'in_progress', 'funded', 'completed'];

        if (!in_array($statut, $allowed, true)) {
            $this->addFlash('error', 'Statut invalide.');
            return $this->redirectToRoute('app_admin_projet_show', ['id' => $projet->getProject_id()]);
        }

        $projet->setStatus($statut);
        $this->entityManager->flush();
        $this->addFlash('success', 'Statut du projet mis a jour.');

        return $this->redirectToRoute('app_admin_projet_show', ['id' => $projet->getProject_id()]);
    }

    #[Route('/{id}/delete', name: 'app_admin_projet_delete', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function delete(Request $request, Project $projet): Response
    {
        if (!$this->isCsrfTokenValid('delete_projet_' . $projet->getProject_id(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_admin_projet_index');
        }

        $this->entityManager->remove($projet);
        $this->entityManager->flush();
        $this->addFlash('success', 'Projet supprime avec succes.');

        return $this->redirectToRoute('app_admin_projet_index');
    }
}
