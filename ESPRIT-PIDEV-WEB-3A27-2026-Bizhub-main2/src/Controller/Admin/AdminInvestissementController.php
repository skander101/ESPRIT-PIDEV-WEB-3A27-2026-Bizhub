<?php

namespace App\Controller\Admin;

use App\Entity\Investissement\Investment;
use App\Repository\InvestmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/back/investissement/investissements')]
#[IsGranted('ROLE_ADMIN')]
class AdminInvestissementController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private InvestmentRepository $investmentRepository,
    ) {}

    #[Route('', name: 'app_admin_investment_index', methods: ['GET'])]
    public function index(): Response
    {
        $investments = $this->investmentRepository->findAllWithProject();

        return $this->render('back/investissement/index.html.twig', [
            'investments' => $investments,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_investment_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Investment $investment): Response
    {
        return $this->render('back/investissement/show.html.twig', [
            'investment' => $investment,
        ]);
    }

    #[Route('/{id}/statut', name: 'app_admin_investment_statut', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function changeStatut(Request $request, Investment $investment): Response
    {
        if (!$this->isCsrfTokenValid('statut_investment_' . $investment->getInvestment_id(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_admin_investment_show', ['id' => $investment->getInvestment_id()]);
        }

        $statut = $request->request->get('statut');
        $allowed = ['en_attente', 'en_negociation', 'accepte', 'refuse', 'contrat_genere', 'signe', 'termine'];

        if (!in_array($statut, $allowed, true)) {
            $this->addFlash('error', 'Statut invalide.');
            return $this->redirectToRoute('app_admin_investment_show', ['id' => $investment->getInvestment_id()]);
        }

        $investment->setStatut($statut);
        $this->entityManager->flush();
        $this->addFlash('success', 'Statut de l\'investissement mis à jour.');

        return $this->redirectToRoute('app_admin_investment_show', ['id' => $investment->getInvestment_id()]);
    }

    #[Route('/{id}/delete', name: 'app_admin_investment_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Investment $investment): Response
    {
        if (!$this->isCsrfTokenValid('delete_investment_' . $investment->getInvestment_id(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_admin_investment_index');
        }

        $this->entityManager->remove($investment);
        $this->entityManager->flush();
        $this->addFlash('success', 'Investissement supprimé avec succès.');

        return $this->redirectToRoute('app_admin_investment_index');
    }
}
