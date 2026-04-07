<?php

namespace App\Controller\Investissement;

use App\Entity\Investissement\Investment;
use App\Form\Investissement\InvestissementFrontType;
use App\Repository\InvestmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/front/investissement')]
class FrontInvestissementController extends AbstractController
{
    public function __construct(
        private InvestmentRepository $investmentRepository,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/mes-investissements', name: 'app_front_investissement_index', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->getUser();

        if (!$user) {
            $this->addFlash('error', 'Vous devez être connecté.');
            return $this->redirectToRoute('app_login');
        }

        $investissements = $this->investmentRepository->findBy(
            ['user' => $user],
            ['created_at' => 'DESC']
        );

        $totalInvesti = 0;
        foreach ($investissements as $investissement) {
            $totalInvesti += (float) $investissement->getAmount();
        }

        return $this->render('front/investissement/index.html.twig', [
            'investissements' => $investissements,
            'total_investi' => $totalInvesti,
        ]);
    }

    #[Route('/mes-investissements/{id}/modifier', name: 'app_front_investissement_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        #[MapEntity(mapping: ['id' => 'investment_id'])]
        Investment $investment
    ): Response
    {
        $user = $this->getUser();

        if (!$user || $investment->getUser() !== $user) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }

        if (!$investment->getProject() || $investment->getProject()->getStatus() !== 'en_cours') {
            $this->addFlash('error', 'Seuls les investissements dont le projet est en cours peuvent être modifiés.');
            return $this->redirectToRoute('app_front_investissement_index');
        }

        $form = $this->createForm(InvestissementFrontType::class, $investment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', 'Investissement modifié avec succès.');
            return $this->redirectToRoute('app_front_investissement_index');
        }

        return $this->render('front/investissement/edit.html.twig', [
            'investment' => $investment,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/mes-investissements/{id}/supprimer', name: 'app_front_investissement_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        #[MapEntity(mapping: ['id' => 'investment_id'])]
        Investment $investment
    ): Response
    {
        $user = $this->getUser();

        if (!$user || $investment->getUser() !== $user) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }

        if (!$investment->getProject() || $investment->getProject()->getStatus() !== 'en_cours') {
            $this->addFlash('error', 'Seuls les investissements dont le projet est en cours peuvent être supprimés.');
            return $this->redirectToRoute('app_front_investissement_index');
        }

        if ($this->isCsrfTokenValid('delete_investment_' . $investment->getInvestment_id(), $request->request->get('_token'))) {
            $this->entityManager->remove($investment);
            $this->entityManager->flush();

            $this->addFlash('success', 'Investissement supprimé avec succès.');
        }

        return $this->redirectToRoute('app_front_investissement_index');
    }
}
