<?php

namespace App\Controller\Admin;

use App\Entity\Elearning\Formation;
use App\Entity\Elearning\TrainingRequest;
use App\Form\Elearning\FormationType;
use App\Repository\TrainingRequestRepository;
use App\Repository\Elearning\FormationRepository;
use App\Repository\Elearning\ParticipationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/back/formations')]
#[IsGranted('ROLE_ADMIN')]
class FormationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FormationRepository $formationRepository,
        private readonly ParticipationRepository $participationRepository,
        private readonly TrainingRequestRepository $trainingRequestRepository,
    ) {
    }

    #[Route('', name: 'app_admin_formation_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('back/elearning/formation/index.html.twig', [
            'formations' => $this->formationRepository->findAllOrderedByStartDate(),
        ]);
    }

    #[Route('/new', name: 'app_admin_formation_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $formation = new Formation();
        $form = $this->createForm(FormationType::class, $formation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($formation);
            $this->entityManager->flush();
            $this->addFlash('success', 'Formation créée.');

            return $this->redirectToRoute('app_admin_formation_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('back/elearning/formation/new.html.twig', [
            'formation' => $formation,
            'form' => $form,
            'form_js_mode' => 'formation-new',
        ]);
    }

    #[Route('/{formation_id}/edit', name: 'app_admin_formation_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, #[MapEntity(mapping: ['formation_id' => 'formation_id'])] Formation $formation): Response
    {
        $form = $this->createForm(FormationType::class, $formation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'Formation mise à jour.');

            return $this->redirectToRoute('app_admin_formation_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('back/elearning/formation/edit.html.twig', [
            'formation' => $formation,
            'form' => $form,
            'form_js_mode' => 'formation-edit',
        ]);
    }

    #[Route('/{formation_id}/participants', name: 'app_admin_formation_participants', methods: ['GET'])]
    public function participants(#[MapEntity(mapping: ['formation_id' => 'formation_id'])] Formation $formation): Response
    {
        return $this->render('back/elearning/formation/participants.html.twig', [
            'formation' => $formation,
            'participations' => $this->participationRepository->findByFormationOrdered($formation),
            'training_requests' => $this->trainingRequestRepository->findByFormationOrdered($formation),
            'approved_trainers_count' => $this->trainingRequestRepository->countApprovedByFormation($formation),
        ]);
    }

    #[Route('/{formation_id}/requests/{id}/approve', name: 'app_admin_formation_request_approve', methods: ['POST'])]
    public function approveRequest(
        Request $request,
        #[MapEntity(mapping: ['formation_id' => 'formation_id'])] Formation $formation,
        TrainingRequest $trainingRequest,
    ): Response {
        if ($trainingRequest->getFormation()?->getFormation_id() !== $formation->getFormation_id()) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('approve_training_request_' . $trainingRequest->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $approvedCount = $this->trainingRequestRepository->countApprovedByFormation($formation);
        if ($trainingRequest->getStatus() !== 'accepted' && $approvedCount >= $formation->getMaxFormateurs()) {
            $this->addFlash('warning', 'Quota maximal de formateurs deja atteint pour cette formation.');

            return $this->redirectToRoute('app_admin_formation_participants', ['formation_id' => $formation->getFormation_id()]);
        }

        $trainingRequest->setStatus('accepted');
        $this->entityManager->flush();

        $this->addFlash('success', 'Demande formateur approuvee.');

        return $this->redirectToRoute('app_admin_formation_participants', ['formation_id' => $formation->getFormation_id()]);
    }

    #[Route('/{formation_id}/requests/{id}/reject', name: 'app_admin_formation_request_reject', methods: ['POST'])]
    public function rejectRequest(
        Request $request,
        #[MapEntity(mapping: ['formation_id' => 'formation_id'])] Formation $formation,
        TrainingRequest $trainingRequest,
    ): Response {
        if ($trainingRequest->getFormation()?->getFormation_id() !== $formation->getFormation_id()) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('reject_training_request_' . $trainingRequest->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $trainingRequest->setStatus('rejected');
        $this->entityManager->flush();

        $this->addFlash('success', 'Demande formateur rejetee.');

        return $this->redirectToRoute('app_admin_formation_participants', ['formation_id' => $formation->getFormation_id()]);
    }

    #[Route('/{formation_id}/delete', name: 'app_admin_formation_delete', methods: ['POST'])]
    public function delete(Request $request, #[MapEntity(mapping: ['formation_id' => 'formation_id'])] Formation $formation): Response
    {
        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete_formation_'.$formation->getFormation_id(), $token)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $this->entityManager->remove($formation);
        $this->entityManager->flush();
        $this->addFlash('success', 'Formation supprimée.');

        return $this->redirectToRoute('app_admin_formation_index', [], Response::HTTP_SEE_OTHER);
    }
}
