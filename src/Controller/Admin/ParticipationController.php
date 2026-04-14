<?php

namespace App\Controller\Admin;

use App\Entity\Elearning\Formation;
use App\Entity\Elearning\Participation;
use App\Form\Elearning\ParticipationType;
use App\Repository\Elearning\ParticipationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/back')]
#[IsGranted('ROLE_ADMIN')]
class ParticipationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ParticipationRepository $participationRepository,
    ) {
    }

    #[Route('/formations/{formation_id}/participations/new', name: 'app_admin_participation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, #[MapEntity(mapping: ['formation_id' => 'formation_id'])] Formation $formation): Response
    {
        $participation = new Participation();
        $participation->setFormation($formation);
        $participation->setPayment_status('PENDING');
        $cost = $formation->getCost();
        $participation->setAmount($cost !== null && $cost !== '' ? (string) $cost : '0.00');

        $form = $this->createForm(ParticipationType::class, $participation, [
            'admin_mode' => true,
            'hide_formation_field' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $existing = $this->participationRepository->findOneByUserAndFormation(
                $participation->getUser(),
                $participation->getFormation()
            );
            if ($existing !== null) {
                $this->addFlash('danger', 'Ce participant est déjà inscrit à cette formation.');

                return $this->render('back/elearning/participation/new.html.twig', [
                    'formation' => $formation,
                    'participation' => $participation,
                    'form' => $form,
                    'form_js_mode' => 'participation-new',
                ]);
            }

            $this->entityManager->persist($participation);
            $this->entityManager->flush();
            $this->addFlash('success', 'Participation enregistrée.');

            return $this->redirectToRoute('app_admin_formation_participants', [
                'formation_id' => $formation->getFormation_id(),
            ], Response::HTTP_SEE_OTHER);
        }

        return $this->render('back/elearning/participation/new.html.twig', [
            'formation' => $formation,
            'participation' => $participation,
            'form' => $form,
            'form_js_mode' => 'participation-new',
        ]);
    }

    #[Route('/participations/{id_candidature}/edit', name: 'app_admin_participation_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, #[MapEntity(mapping: ['id_candidature' => 'id_candidature'])] Participation $participation): Response
    {
        $formation = $participation->getFormation();
        $form = $this->createForm(ParticipationType::class, $participation, [
            'admin_mode' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'Participation mise à jour.');

            return $this->redirectToRoute('app_admin_formation_participants', [
                'formation_id' => $formation?->getFormation_id() ?? 0,
            ], Response::HTTP_SEE_OTHER);
        }

        return $this->render('back/elearning/participation/edit.html.twig', [
            'participation' => $participation,
            'formation' => $formation,
            'form' => $form,
            'form_js_mode' => 'participation-edit',
        ]);
    }

    #[Route('/participations/{id_candidature}/delete', name: 'app_admin_participation_delete', methods: ['POST'])]
    public function delete(Request $request, #[MapEntity(mapping: ['id_candidature' => 'id_candidature'])] Participation $participation): Response
    {
        $formationId = $participation->getFormation()?->getFormation_id();
        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete_participation_'.$participation->getId_candidature(), $token)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $this->entityManager->remove($participation);
        $this->entityManager->flush();
        $this->addFlash('success', 'Participation supprimée.');

        return $this->redirectToRoute('app_admin_formation_participants', [
            'formation_id' => $formationId ?? 0,
        ], Response::HTTP_SEE_OTHER);
    }
}
