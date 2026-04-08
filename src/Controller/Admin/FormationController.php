<?php

namespace App\Controller\Admin;

use App\Entity\Elearning\Formation;
use App\Form\Elearning\FormationType;
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
        ]);
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
