<?php

namespace App\Controller\Investissement;

use App\Entity\Investissement\Investment;
use App\Entity\UsersAvis\User;
use App\Entity\Investissement\Project;
use App\Form\Investissement\InvestisseurType;
use App\Form\Investissement\InvestissementFrontType;
use App\Repository\InvestmentRepository;
use App\Repository\ProjectRepository;
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
        private InvestmentRepository   $investmentRepository,
        private ProjectRepository      $projectRepository,
        private EntityManagerInterface $entityManager,
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
    // INVESTIR (formulaire d'investissement pour un projet)
    // ────────────────────────────────────────────────────────────────────────

    #[Route('/{id}/investir', name: 'app_front_investir', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function investir(Request $request, int $id): Response
    {
        // 1. Connexion obligatoire
        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('warning', 'Vous devez être connecté pour investir.');
            return $this->redirectToRoute('app_login');
        }

        // 2. Projet existant
        $projet = $this->projectRepository->find($id);
        if (!$projet) {
            $this->addFlash('error', 'Projet introuvable.');
            return $this->redirectToRoute('app_front_projet_index');
        }

        // 3. Seuls les projets publiés / en cours acceptent des investissements.
        // Support des anciens statuts (publie, en_cours) pendant la transition.
        $statutsOuverts = [
            Project::STATUS_PUBLIE,
            Project::STATUS_EN_COURS,
            'publie',
            'en_cours',
        ];
        if (!in_array($projet->getStatus(), $statutsOuverts)) {
            $this->addFlash('error', 'Ce projet n\'accepte pas d\'investissements pour l\'instant.');
            return $this->redirectToRoute('app_front_projet_show', ['id' => $id]);
        }

        // 4. Une startup ne peut pas investir dans son propre projet
        if ($projet->getUser() && $projet->getUser()->getUserId() === ($user instanceof User ? $user->getUserId() : null)) {
            $this->addFlash('error', 'Vous ne pouvez pas investir dans votre propre projet.');
            return $this->redirectToRoute('app_front_projet_show', ['id' => $id]);
        }

        // 5. Créer l'investissement avec statut par défaut
        $investment = new Investment();
        $investment->setProject($projet);
        $investment->setUser($user);
        $investment->setCreated_at(new \DateTime());
        $investment->setInvestment_date(new \DateTime());
        $investment->setStatut('en_attente');

        $form = $this->createForm(InvestisseurType::class, $investment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($investment);
            $this->entityManager->flush();

            $this->addFlash('success', 'Votre investissement a été enregistré avec succès ! Merci pour votre confiance.');
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

    // ────────────────────────────────────────────────────────────────────────
    // MODIFIER UN INVESTISSEMENT
    // ────────────────────────────────────────────────────────────────────────

    #[Route('/mes-investissements/{id}/modifier', name: 'app_front_investissement_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        #[MapEntity(mapping: ['id' => 'investment_id'])]
        Investment $investment
    ): Response {
        $user = $this->getUser();

        if (!$user || $investment->getUser() !== $user) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }

        // Seuls les investissements EN ATTENTE peuvent être modifiés
        if ($investment->getStatut() !== 'en_attente') {
            $this->addFlash('error', 'Seuls les investissements en attente peuvent être modifiés.');
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
            'form'       => $form->createView(),
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // SUPPRIMER UN INVESTISSEMENT
    // ────────────────────────────────────────────────────────────────────────

    #[Route('/mes-investissements/{id}/supprimer', name: 'app_front_investissement_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        #[MapEntity(mapping: ['id' => 'investment_id'])]
        Investment $investment
    ): Response {
        $user = $this->getUser();

        if (!$user || $investment->getUser() !== $user) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }

        // Seuls les investissements EN ATTENTE peuvent être supprimés
        if ($investment->getStatut() !== 'en_attente') {
            $this->addFlash('error', 'Seuls les investissements en attente peuvent être supprimés.');
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
