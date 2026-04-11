<?php

namespace App\Controller\Front;

use App\Entity\Elearning\Formation;
use App\Entity\Elearning\Participation;
use App\Entity\Elearning\TrainingRequest;
use App\Entity\UsersAvis\Avis;
use App\Entity\UsersAvis\User;
use App\Form\Elearning\ParticipationType;
use App\Repository\Elearning\FormationRepository;
use App\Repository\Elearning\ParticipationRepository;
use App\Repository\TrainingRequestRepository;
use App\Repository\UsersAvis\AvisRepository;
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
        private readonly TrainingRequestRepository $trainingRequestRepository,
        private readonly AvisRepository $avisRepository,
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
        $isFormateur = $user->getUserType() === 'formateur';
        $reviewsByFormation = $this->avisRepository->findVisibleGroupedByFormations($formations);
        $already = [];
        $forms = [];
        $userReviews = [];
        $trainerRequests = [];
        $approvedFormateursByFormation = [];
        foreach ($formations as $f) {
            $formationId = $f->getFormation_id();
            $approvedFormateursByFormation[$formationId] = $this->trainingRequestRepository->countApprovedByFormation($f);

            if ($isFormateur) {
                $already[$formationId] = false;
                $userReviews[$formationId] = null;
                $trainerRequests[$formationId] = $this->trainingRequestRepository->findOneByUserAndFormation($user, $f);
                continue;
            }

            $p = $this->participationRepository->findOneByUserAndFormation($user, $f);
            $already[$formationId] = $p !== null;
            $userReview = $this->avisRepository->findOneByUserAndFormation($user, $f);
            $userReviews[$formationId] = $userReview;
            if ($p === null) {
                $participation = new Participation();
                $participation->setUser($user);
                $participation->setFormation($f);
                $forms[$formationId] = $this->createForm(ParticipationType::class, $participation, [
                    'admin_mode' => false,
                ])->createView();
            }
        }

        return $this->render('front/elearning/formations/index.html.twig', [
            'formations' => $formations,
            'already_participating' => $already,
            'forms' => $forms,
            'search_query' => $search,
            'reviews_by_formation' => $reviewsByFormation,
            'user_reviews' => $userReviews,
            'is_formateur' => $isFormateur,
            'trainer_requests' => $trainerRequests,
            'approved_formateurs_by_formation' => $approvedFormateursByFormation,
        ]);
    }

    #[Route('/{formation_id}/apply', name: 'app_front_formation_apply', methods: ['POST'])]
    public function apply(
        Request $request,
        #[MapEntity(mapping: ['formation_id' => 'formation_id'])] Formation $formation,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if ($user->getUserType() !== 'formateur') {
            $this->addFlash('warning', 'Seuls les comptes formateur peuvent demander une affectation.');

            return $this->redirectToFormationsIndex($request);
        }

        if (!$this->isCsrfTokenValid('formation_apply_' . (string) $formation->getFormation_id(), $request->request->getString('_token', ''))) {
            $this->addFlash('danger', 'Action invalide (token CSRF).');

            return $this->redirectToFormationsIndex($request);
        }

        $existing = $this->trainingRequestRepository->findOneByUserAndFormation($user, $formation);
        if ($existing instanceof TrainingRequest) {
            if ($existing->getStatus() === 'accepted') {
                $this->addFlash('info', 'Votre demande est deja approuvee pour cette formation.');

                return $this->redirectToFormationsIndex($request);
            }

            if ($existing->getStatus() === 'pending') {
                $this->addFlash('info', 'Votre demande est deja en attente de validation admin.');

                return $this->redirectToFormationsIndex($request);
            }

            $existing->setStatus('pending');
            $this->entityManager->flush();
            $this->addFlash('success', 'Votre demande a ete reenvoyee.');

            return $this->redirectToFormationsIndex($request);
        }

        $trainingRequest = new TrainingRequest();
        $trainingRequest->setUser($user);
        $trainingRequest->setFormation($formation);
        $trainingRequest->setStatus('pending');
        $this->entityManager->persist($trainingRequest);
        $this->entityManager->flush();

        $this->addFlash('success', 'Votre demande a ete envoyee a l\'administrateur.');

        return $this->redirectToFormationsIndex($request);
    }

    #[Route('/{formation_id}/avis', name: 'app_front_formation_review', methods: ['POST'])]
    public function review(
        Request $request,
        #[MapEntity(mapping: ['formation_id' => 'formation_id'])] Formation $formation,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if ($user->getUserType() === 'formateur') {
            $this->addFlash('warning', 'Les formateurs ne peuvent pas publier d\'avis.');

            return $this->redirectToFormationsIndex($request);
        }

        if ($this->participationRepository->findOneByUserAndFormation($user, $formation) === null) {
            $this->addFlash('warning', 'Vous devez participer à cette formation avant de laisser un avis.');

            return $this->redirectToFormationsIndex($request);
        }

        $token = $request->request->getString('_token', '');
        if (!$this->isCsrfTokenValid('formation_review_' . (string) $formation->getFormation_id(), $token)) {
            $this->addFlash('danger', 'Action invalide (token CSRF).');

            return $this->redirectToFormationsIndex($request);
        }

        $rating = (int) $request->request->get('rating', 0);
        if ($rating < 1 || $rating > 5) {
            $this->addFlash('danger', 'La note doit être comprise entre 1 et 5.');

            return $this->redirectToFormationsIndex($request);
        }

        $comment = trim($request->request->getString('comment', ''));
        if (mb_strlen($comment) > 1000) {
            $this->addFlash('danger', 'Le commentaire ne doit pas dépasser 1000 caractères.');

            return $this->redirectToFormationsIndex($request);
        }

        $existing = $this->avisRepository->findOneByUserAndFormation($user, $formation);
        $avis = $existing ?? new Avis();
        if ($existing === null) {
            $avis->setUser($user);
            $avis->setFormation($formation);
            $avis->setCreatedAt(new \DateTime());
            $avis->setIsRemoved(false);
            $avis->setIsEdited(false);
            $avis->setIsVerified(false);
        } else {
            $avis->setIsEdited(true);
        }

        $avis->setRating($rating);
        $avis->setComment($comment !== '' ? $comment : null);

        $this->entityManager->persist($avis);
        $this->entityManager->flush();

        $this->addFlash('success', $existing ? 'Votre avis a été mis à jour.' : 'Votre avis a été publié.');

        return $this->redirectToFormationsIndex($request);
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

        if ($user->getUserType() === 'formateur') {
            $this->addFlash('warning', 'Les formateurs doivent utiliser la demande d\'affectation.');

            return $this->redirectToFormationsIndex($request);
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
