<?php

declare(strict_types=1);

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
use App\Entity\Elearning\FormationRecommendationEvent;
use App\Service\Elearning\FormationAiBestPickService;
use App\Service\Elearning\FormationLocationPresentationService;
use App\Service\Elearning\FormationRecommendationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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
        private readonly FormationLocationPresentationService $formationLocationPresentationService,
        private readonly FormationRecommendationService $formationRecommendationService,
        private readonly FormationAiBestPickService $formationAiBestPickService,
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
        $awaitingPayment = [];
        $userReviews = [];
        $trainerRequests = [];
        $approvedFormateursByFormation = [];
        foreach ($formations as $f) {
            $formationId = $f->getFormation_id();
            $approvedFormateursByFormation[$formationId] = $this->trainingRequestRepository->countApprovedByFormation($f);

            if ($isFormateur) {
                $already[$formationId] = false;
                $awaitingPayment[$formationId] = null;
                $userReviews[$formationId] = null;
                $trainerRequests[$formationId] = $this->trainingRequestRepository->findOneByUserAndFormation($user, $f);
                continue;
            }

            $paid = $this->participationRepository->findPaidByUserAndFormation($user, $f);
            $await = $this->participationRepository->findAwaitingPaymentByUserAndFormation($user, $f);
            $already[$formationId] = $paid !== null;
            $awaitingPayment[$formationId] = $await?->getId_candidature();
            $userReview = $this->avisRepository->findOneByUserAndFormation($user, $f);
            $userReviews[$formationId] = $userReview;
        }

        return $this->render('front/elearning/formations/index.html.twig', [
            'formations' => $formations,
            'already_participating' => $already,
            'awaiting_payment_participation' => $awaitingPayment,
            'search_query' => $search,
            'reviews_by_formation' => $reviewsByFormation,
            'user_reviews' => $userReviews,
            'is_formateur' => $isFormateur,
            'trainer_requests' => $trainerRequests,
            'approved_formateurs_by_formation' => $approvedFormateursByFormation,
            'reco' => $this->formationRecommendationService->getFormationsIndexBlocksForUser($user),
        ]);
    }

    #[Route('/ai-meilleure-formation', name: 'app_front_formation_ai_best_pick', methods: ['POST'])]
    public function aiMeilleureFormation(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['ok' => false, 'message' => 'Non authentifié.'], 401);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            $payload = [];
        }

        $token = $payload['_token'] ?? $request->headers->get('X-CSRF-TOKEN') ?? '';
        if (!$this->isCsrfTokenValid('formation_ai_best_pick', (string) $token)) {
            return $this->json(['ok' => false, 'message' => 'Session expirée. Rechargez la page.'], 403);
        }

        $notes = isset($payload['notes']) && is_string($payload['notes']) ? $payload['notes'] : '';
        $result = $this->formationAiBestPickService->suggestForUser($user, $notes);

        if (!($result['ok'] ?? false)) {
            return $this->json($result);
        }

        $fid = (int) ($result['formation_id'] ?? 0);
        if ($fid <= 0) {
            return $this->json(['ok' => false, 'message' => 'Réponse invalide.'], 500);
        }

        $result['url'] = $this->generateUrl('app_front_formation_show', ['formation_id' => $fid]);

        return $this->json($result);
    }

    #[Route('/{formation_id}/location-qr.svg', name: 'app_front_formation_location_qr_svg', methods: ['GET'], requirements: ['formation_id' => '\d+'])]
    public function locationQrSvg(
        #[MapEntity(mapping: ['formation_id' => 'formation_id'])] Formation $formation,
    ): Response {
        $svg = $this->formationLocationPresentationService->locationQrSvgStringForFormation($formation);
        if ($svg === null) {
            throw $this->createNotFoundException();
        }

        return new Response($svg, Response::HTTP_OK, [
            'Content-Type' => 'image/svg+xml; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
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

        if ($this->participationRepository->findPaidByUserAndFormation($user, $formation) === null) {
            $this->addFlash('warning', 'Vous devez participer (paiement validé) à cette formation avant de laisser un avis.');

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

        if ($this->participationRepository->findPaidByUserAndFormation($user, $formation) !== null) {
            $this->addFlash('warning', 'Vous êtes déjà inscrit (payé) à cette formation.');

            return $this->redirectToFormationShow($formation);
        }

        $awaiting = $this->participationRepository->findAwaitingPaymentByUserAndFormation($user, $formation);
        if ($awaiting !== null) {
            $this->addFlash('info', 'Finalisez votre paiement pour confirmer votre inscription.');

            return $this->redirectToRoute('app_payment_checkout', ['id' => $awaiting->getId_candidature()]);
        }

        $participation = new Participation();
        $participation->setUser($user);
        $participation->setFormation($formation);
        $participation->setStatus(Participation::STATUS_AWAITING_PAYMENT);
        $participation->setPaymentStatus('PENDING');
        $cost = $formation->getCost();
        $participation->setAmount($cost !== null && $cost !== '' ? (string) $cost : '0.00');

        $form = $this->createForm(ParticipationType::class, $participation, ['admin_mode' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($participation);
            $this->entityManager->flush();

            $recoSection = $request->request->getString('reco_section');
            $allowedReco = [
                FormationRecommendationEvent::SECTION_PERSONALIZED,
                FormationRecommendationEvent::SECTION_TRENDING,
                FormationRecommendationEvent::SECTION_POPULAR,
                FormationRecommendationEvent::SECTION_NEW,
            ];
            if ($recoSection !== '' && in_array($recoSection, $allowedReco, true)) {
                $ev = new FormationRecommendationEvent();
                $ev->setUser($user);
                $ev->setFormation($formation);
                $ev->setSection($recoSection);
                $ev->setEventType(FormationRecommendationEvent::EVENT_ENROLL);
                $ev->setCreatedAt(new \DateTimeImmutable());
                $this->entityManager->persist($ev);
                $this->entityManager->flush();
            }

            $this->addFlash('success', 'Inscription créée — procédez au paiement sécurisé.');

            return $this->redirectToRoute('app_payment_checkout', ['id' => (int) $participation->getId_candidature()]);
        }

        foreach ($form->getErrors(true) as $error) {
            $this->addFlash('danger', $error->getMessage());
        }

        return $this->redirectToFormationShow($formation);
    }

    #[Route('/{formation_id}', name: 'app_front_formation_show', methods: ['GET'], requirements: ['formation_id' => '\d+'])]
    public function show(
        #[MapEntity(mapping: ['formation_id' => 'formation_id'])] Formation $formation,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $isFormateur = $user->getUserType() === 'formateur';
        $paid = $this->participationRepository->findPaidByUserAndFormation($user, $formation);
        $await = $this->participationRepository->findAwaitingPaymentByUserAndFormation($user, $formation);

        $participationFormView = null;
        if (!$isFormateur && $paid === null && $await === null) {
            $p = new Participation();
            $p->setUser($user);
            $p->setFormation($formation);
            $participationFormView = $this->createForm(ParticipationType::class, $p, ['admin_mode' => false])->createView();
        }

        return $this->render('front/elearning/formations/show.html.twig', [
            'formation' => $formation,
            'is_formateur' => $isFormateur,
            'already_paid' => $paid !== null,
            'awaiting_participation_id' => $await?->getId_candidature(),
            'participation_form' => $participationFormView,
        ]);
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

    private function redirectToFormationShow(Formation $formation): Response
    {
        return $this->redirectToRoute(
            'app_front_formation_show',
            ['formation_id' => $formation->getFormation_id()],
            Response::HTTP_SEE_OTHER
        );
    }
}
