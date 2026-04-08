<?php

namespace App\Controller\Front;

use App\Entity\Elearning\Formation;
use App\Entity\Elearning\Participation;
use App\Entity\UsersAvis\User;
use App\Form\Elearning\ParticipationType;
use App\Repository\Elearning\FormationRepository;
use App\Repository\Elearning\ParticipationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/front/formations')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class FormationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FormationRepository $formationRepository,
        private readonly ParticipationRepository $participationRepository,
    ) {
    }

    #[Route('', name: 'app_front_formations_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $search = $request->query->getString('q', '');
        $formations = $this->formationRepository->findOrderedByStartDateWithSearch($search);
        $already = [];
        $forms = [];
        foreach ($formations as $f) {
            $p = $this->participationRepository->findOneByUserAndFormation($user, $f);
            $already[$f->getFormation_id()] = $p !== null;
            if ($p === null) {
                $participation = new Participation();
                $participation->setUser($user);
                $participation->setFormation($f);
                $forms[$f->getFormation_id()] = $this->createForm(ParticipationType::class, $participation, [
                    'admin_mode' => false,
                ])->createView();
            }
        }

        return $this->render('front/elearning/formations/index.html.twig', [
            'formations' => $formations,
            'already_participating' => $already,
            'forms' => $forms,
            'search_query' => $search,
        ]);
    }

    #[Route('/{formation_id}/participation', name: 'app_front_formation_participate', methods: ['POST'])]
    public function participate(
        Request $request,
        #[MapEntity(mapping: ['formation_id' => 'formation_id'])] Formation $formation,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }
        if ($this->participationRepository->findOneByUserAndFormation($user, $formation) !== null) {
            $this->addFlash('warning', 'Vous participez déjà à cette formation.');

            return $this->redirectToFormationsIndex($request);
        }

        $participation = new Participation();
        $participation->setUser($user);
        $participation->setFormation($formation);
        $participation->setPayment_status('PENDING');
        $cost = $formation->getCost();
        $participation->setAmount($cost !== null && $cost !== '' ? (string) $cost : '0.00');

        $form = $this->createForm(ParticipationType::class, $participation, ['admin_mode' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($participation);
            $this->entityManager->flush();
            $this->addFlash('success', 'Votre demande de participation a été enregistrée.');

            return $this->redirectToFormationsIndex($request);
        }

        foreach ($form->getErrors(true) as $error) {
            $this->addFlash('danger', $error->getMessage());
        }

        return $this->redirectToFormationsIndex($request);
    }

    /**
     * @return array<string, string>
     */
    private function formationsIndexQueryParams(Request $request): array
    {
        $q = $request->query->getString('q', '');
        if ($q === '') {
            $q = $request->request->getString('q', '');
        }
        if ($q === '') {
            return [];
        }

        return ['q' => $q];
    }

    private function redirectToFormationsIndex(Request $request): Response
    {
        return $this->redirectToRoute(
            'app_front_formations_index',
            $this->formationsIndexQueryParams($request),
            Response::HTTP_SEE_OTHER
        );
    }
}
