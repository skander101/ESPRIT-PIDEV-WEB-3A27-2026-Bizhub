<?php

namespace App\Controller\UsersAvis;

use App\Entity\UsersAvis\Avis;
use App\Entity\UsersAvis\User;
use App\Form\UsersAvis\AvisType;
use App\Repository\Elearning\ParticipationRepository;
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
        private ParticipationRepository $participationRepository,
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

        if ($user->getUserType() === 'formateur' && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'Formateur accounts cannot publish reviews.');

            return $this->redirectToRoute('app_front_formations_index');
        }

        $avi = new Avis();
        $avi->setUser($user);
        $avi->setCreated_at(new \DateTime());

        $form = $this->createForm(AvisType::class, $avi, [
            'allow_admin_fields' => $this->isGranted('ROLE_ADMIN'),
            'include_formation' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $formation = $avi->getFormation();
            if ($formation === null || $this->participationRepository->findOneByUserAndFormation($user, $formation) === null) {
                $this->addFlash('error', 'You must participate in this formation before leaving a review.');

                return $this->redirectToRoute('app_avis_new');
            }

            $existing = $this->avisRepository->findOneByUserAndFormation($user, $formation);
            if ($existing !== null) {
                $this->addFlash('warning', 'You already reviewed this formation. You can edit your existing review.');
                $request->getSession()->set('avis_edit_id', $existing->getAvis_id());

                return $this->redirectToRoute('app_avis_edit');
            }

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

    #[Route('/edit', name: 'app_avis_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request): Response
    {
        $selectedId = (int) $request->getSession()->get('avis_edit_id', 0);
        if ($selectedId <= 0) {
            $this->addFlash('warning', 'Please select a review to edit from your list.');

            return $this->redirectToRoute('app_avis_list');
        }

        $avi = $this->avisRepository->find($selectedId);
        if (!$avi instanceof Avis) {
            $request->getSession()->remove('avis_edit_id');
            $this->addFlash('error', 'Review not found.');

            return $this->redirectToRoute('app_avis_list');
        }

        return $this->handleEdit($request, $avi);
    }

    #[Route('/{id}/edit', name: 'app_avis_edit_legacy', methods: ['GET'], requirements: ['id' => '\\d+'])]
    public function editLegacy(Request $request, Avis $avi): Response
    {
        $user = $this->getUser();
        if ($avi->getUser() !== $user && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'You cannot edit this review.');

            return $this->redirectToRoute('app_avis_list');
        }

        $request->getSession()->set('avis_edit_id', $avi->getAvis_id());

        return $this->redirectToRoute('app_avis_edit');
    }

    #[Route('/edit/select', name: 'app_avis_edit_select', methods: ['POST'])]
    public function selectEdit(Request $request): Response
    {
        $id = (int) $request->request->get('review_id', 0);
        if ($id <= 0) {
            $this->addFlash('error', 'Invalid review selection.');

            return $this->redirectToRoute('app_avis_list');
        }

        if (!$this->isCsrfTokenValid('select_edit_' . $id, $request->request->getString('_token', ''))) {
            $this->addFlash('error', 'Invalid CSRF token.');

            return $this->redirectToRoute('app_avis_list');
        }

        $avi = $this->avisRepository->find($id);
        if (!$avi instanceof Avis) {
            $this->addFlash('error', 'Review not found.');

            return $this->redirectToRoute('app_avis_list');
        }

        $user = $this->getUser();
        if ($avi->getUser() !== $user && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'You cannot edit this review.');

            return $this->redirectToRoute('app_avis_list');
        }

        $request->getSession()->set('avis_edit_id', $avi->getAvis_id());

        return $this->redirectToRoute('app_avis_edit');
    }

    private function handleEdit(Request $request, Avis $avi): Response
    {
        $user = $this->getUser();

        if ($user instanceof User && $user->getUserType() === 'formateur' && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'Formateur accounts cannot edit reviews.');

            return $this->redirectToRoute('app_front_formations_index');
        }

        // Check if user owns the review or is admin
        if ($avi->getUser() !== $user && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'You cannot edit this review.');
            return $this->redirectToRoute('app_avis_list');
        }

        $form = $this->createForm(AvisType::class, $avi, [
            'allow_admin_fields' => $this->isGranted('ROLE_ADMIN'),
            'include_formation' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $formation = $avi->getFormation();
            if ($formation === null || ($user instanceof User && $this->participationRepository->findOneByUserAndFormation($user, $formation) === null && !$this->isGranted('ROLE_ADMIN'))) {
                $this->addFlash('error', 'You must participate in this formation before leaving a review.');

                return $this->redirectToRoute('app_avis_list');
            }

            if ($user instanceof User && $formation !== null) {
                $existing = $this->avisRepository->findOneByUserAndFormation($user, $formation);
                if ($existing !== null && $existing->getAvis_id() !== $avi->getAvis_id()) {
                    $this->addFlash('error', 'You already have a review for this formation.');
                    $request->getSession()->set('avis_edit_id', $existing->getAvis_id());

                    return $this->redirectToRoute('app_avis_edit');
                }
            }

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

        if ($user instanceof User && $user->getUserType() === 'formateur' && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'Formateur accounts cannot delete reviews.');

            return $this->redirectToRoute('app_front_formations_index');
        }

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
