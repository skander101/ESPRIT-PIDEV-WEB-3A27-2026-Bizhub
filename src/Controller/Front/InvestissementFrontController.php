<?php

namespace App\Controller\Front;

use App\Entity\Investissement\Investment;
use App\Entity\UsersAvis\User;
use App\Form\Investissement\InvestissementFrontType;
use App\Repository\ProjectRepository;
use App\Repository\InvestmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/front/investissements')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class InvestissementFrontController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private InvestmentRepository $investmentRepository,
        private ProjectRepository $projectRepository,
    ) {}

    /**
     * Investisseur: liste de ses investissements
     */
    #[Route('', name: 'app_front_investissement_index', methods: ['GET'])]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($user->getUserType() !== 'investisseur') {
            $this->addFlash('error', 'Accès réservé aux investisseurs.');
            return $this->redirectToRoute('app_front_projet_index');
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
            'total_investi' => $totalInvesti,
        ]);
    }

    /**
     * Investisseur: formulaire pour investir dans un projet
     */
    #[Route('/projet/{id}/investir', name: 'app_front_investir', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function investir(Request $request, int $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($user->getUserType() !== 'investisseur') {
            $this->addFlash('error', 'Seuls les investisseurs peuvent investir dans un projet.');
            return $this->redirectToRoute('app_front_projet_index');
        }

        $projet = $this->projectRepository->find($id);
        if (!$projet) {
            $this->addFlash('error', 'Projet introuvable.');
            return $this->redirectToRoute('app_front_projet_index');
        }

        if (in_array($projet->getStatus(), ['funded', 'completed'], true)) {
            $this->addFlash('error', 'Ce projet n\'accepte plus d\'investissements.');
            return $this->redirectToRoute('app_front_projet_show', ['id' => $id]);
        }

        $investment = new Investment();
        $investment->setProject($projet);
        $investment->setUser($user);
        $investment->setCreatedAt(new \DateTime());
        $investment->setInvestmentDate(new \DateTime());

        $form = $this->createForm(InvestissementFrontType::class, $investment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($investment);
            $this->em->flush();

            $this->addFlash('success', 'Votre investissement a été enregistré avec succès !');
            return $this->redirectToRoute('app_front_investissement_index');
        }

        $totalInvesti = $this->investmentRepository->getTotalInvestedByProject($projet);
        $pourcentage = $projet->getRequiredBudget() > 0
            ? min(100, round(($totalInvesti / $projet->getRequiredBudget()) * 100))
            : 0;

        return $this->render('front/investissement/invest.html.twig', [
            'form' => $form,
            'projet' => $projet,
            'total_investi' => $totalInvesti,
            'pourcentage' => $pourcentage,
        ]);
    }
}
