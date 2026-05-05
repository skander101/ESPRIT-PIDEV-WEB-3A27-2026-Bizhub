<?php

namespace App\Controller\Investissement;

use App\Entity\Investissement\Investment;
use App\Repository\InvestmentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/front/investissement')]
class FrontInvestissementController extends AbstractController
{
    public function __construct(
        private InvestmentRepository $investmentRepository,
    ) {}

    // ────────────────────────────────────────────────────────────────────────
    // MES INVESTISSEMENTS
    // ────────────────────────────────────────────────────────────────────────

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
        foreach ($investissements as $inv) {
            $totalInvesti += (float) $inv->getAmount();
        }

        return $this->render('front/investissement/index.html.twig', [
            'investissements' => $investissements,
            'total_investi'   => $totalInvesti,
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // INVESTIR (redirige vers la négociation - investissement direct interdit)
    // ────────────────────────────────────────────────────────────────────────

    #[Route('/{id}/investir', name: 'app_front_investir', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function investir(int $id): Response
    {
        $this->addFlash('info', 'L\'investissement direct n\'est plus possible. Veuillez passer par une négociation avec la startup.');
        return $this->redirectToRoute('app_negociation_creer_par_projet', ['id' => $id]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // MODIFIER UN INVESTISSEMENT (redirige vers la négociation)
    // ────────────────────────────────────────────────────────────────────────

    #[Route('/mes-investissements/{id}/modifier', name: 'app_front_investissement_edit', methods: ['GET', 'POST'])]
    public function edit(): Response
    {
        $this->addFlash('info', 'La modification d\'investissement se fait via la négociation.');
        return $this->redirectToRoute('app_negociation_index');
    }

    // ────────────────────────────────────────────────────────────────────────
    // SUPPRIMER UN INVESTISSEMENT (redirige vers la négociation)
    // ────────────────────────────────────────────────────────────────────────

    #[Route('/mes-investissements/{id}/supprimer', name: 'app_front_investissement_delete', methods: ['POST'])]
    public function delete(): Response
    {
        $this->addFlash('info', 'La suppression d\'investissement se fait via la négociation.');
        return $this->redirectToRoute('app_negociation_index');
    }
}
