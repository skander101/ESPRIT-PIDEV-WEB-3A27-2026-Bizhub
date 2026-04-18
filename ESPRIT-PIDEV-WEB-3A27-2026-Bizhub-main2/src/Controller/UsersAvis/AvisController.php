<?php

namespace App\Controller\UsersAvis;

use App\Entity\UsersAvis\Avis;
use App\Entity\UsersAvis\User;
use App\Form\UsersAvis\AvisType;
use App\Repository\UsersAvis\AvisRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/avis')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class AvisController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AvisRepository $avisRepository,
    ) {
    }

    #[Route('', name: 'app_avis_list', methods: ['GET'])]
    public function userReviews(): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $avis = $this->avisRepository->findByUser($user);

        return $this->render('front/avis/list.html.twig', [
            'avis' => $avis,
        ]);
    }

    #[Route('/new', name: 'app_avis_new', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $avi = new Avis();
        $avi->setUser($user);
        $avi->setCreated_at(new \DateTime());

        $form = $this->createForm(AvisType::class, $avi, [
            'allow_admin_fields' => $this->isGranted('ROLE_ADMIN'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($avi);
            $this->entityManager->flush();

            $this->addFlash('success', 'Review created successfully!');

            return $this->redirectToRoute('app_avis_list');
        }

        return $this->render('front/avis/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_avis_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Avis $avi): Response
    {
        return $this->render('front/avis/show.html.twig', [
            'avis' => $avi,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_avis_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Avis $avi): Response
    {
        $user = $this->getUser();

        // Check if user owns the review or is admin
        if ($avi->getUser() !== $user && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'You cannot edit this review.');
            return $this->redirectToRoute('app_avis_list');
        }

        $form = $this->createForm(AvisType::class, $avi, [
            'allow_admin_fields' => $this->isGranted('ROLE_ADMIN'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $avi->setIsEdited(true);
            $this->entityManager->flush();

            $this->addFlash('success', 'Review updated successfully!');

            return $this->redirectToRoute('app_avis_show', ['id' => $avi->getAvis_id()]);
        }

        return $this->render('front/avis/edit.html.twig', [
            'form' => $form,
            'avis' => $avi,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_avis_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Avis $avi): Response
    {
        $user = $this->getUser();

        // Check if user owns the review or is admin
        if ($avi->getUser() !== $user && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'You cannot delete this review.');
            return $this->redirectToRoute('app_avis_list');
        }

        // Verify CSRF token
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete' . $avi->getAvis_id(), $token)) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_avis_list');
        }

        $this->entityManager->remove($avi);
        $this->entityManager->flush();

        $this->addFlash('success', 'Review deleted successfully!');

        return $this->redirectToRoute('app_avis_list');
    }
}
