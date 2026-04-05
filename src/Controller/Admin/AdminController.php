<?php

namespace App\Controller\Admin;

use App\Entity\UsersAvis\User;
use App\Entity\UsersAvis\Avis;
use App\Repository\UsersAvis\UserRepository;
use App\Repository\UsersAvis\AvisRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/back')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private AvisRepository $avisRepository,
    ) {
    }

    #[Route('', name: 'app_admin_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('back/index.html.twig');
    }

    #[Route('/dashboard', name: 'app_admin_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        $totalUsers = $this->userRepository->countAll();
        $activeUsers = $this->userRepository->countActive();
        $totalAvis = $this->avisRepository->countAll();
        $verifiedAvis = $this->avisRepository->countVerified();
        $recentUsers = $this->userRepository->findAllRecent(10);

        return $this->render('back/user/dashboard.html.twig', [
            'total_users' => $totalUsers,
            'active_users' => $activeUsers,
            'total_avis' => $totalAvis,
            'verified_avis' => $verifiedAvis,
            'recent_users' => $recentUsers,
        ]);
    }

    #[Route('/user', name: 'app_admin_user_index', methods: ['GET'])]
    public function userIndex(): Response
    {
        $users = $this->userRepository->findActive();

        return $this->render('back/user/index.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/user/{id}', name: 'app_admin_user_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function userShow(User $user): Response
    {
        return $this->render('back/user/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/user/{id}/edit', name: 'app_admin_user_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function userEdit(Request $request, User $user): Response
    {
        if ($request->isMethod('POST')) {
            // Validate CSRF token
            $token = $request->request->get('_token');
            if (!$this->isCsrfTokenValid('edit_user', $token)) {
                $this->addFlash('error', 'Invalid CSRF token.');
                return $this->redirectToRoute('app_admin_user_show', ['id' => $user->getUser_id()]);
            }

            $isActive = $request->request->get('is_active');
            $user->setIs_active((bool)$isActive);

            $this->entityManager->flush();
            $this->addFlash('success', 'User updated successfully!');

            return $this->redirectToRoute('app_admin_user_show', ['id' => $user->getUser_id()]);
        }

        return $this->render('back/user/edit.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/avis', name: 'app_admin_avis_index', methods: ['GET'])]
    public function avisIndex(): Response
    {
        $avis = $this->avisRepository->findAllWithUser();

        return $this->render('back/avis/index.html.twig', [
            'avis' => $avis,
        ]);
    }

    #[Route('/avis/{id}', name: 'app_admin_avis_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function avisShow(Avis $avi): Response
    {
        return $this->render('back/avis/show.html.twig', [
            'avi' => $avi,
        ]);
    }

    #[Route('/avis/{id}/edit', name: 'app_admin_avis_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function avisEdit(Request $request, Avis $avi): Response
    {
        if ($request->isMethod('POST')) {
            // Validate CSRF token
            $token = $request->request->get('_token');
            if (!$this->isCsrfTokenValid('edit_avis', $token)) {
                $this->addFlash('error', 'Invalid CSRF token.');
                return $this->redirectToRoute('app_admin_avis_show', ['id' => $avi->getAvis_id()]);
            }

            $isVerified = $request->request->get('is_verified');
            $avi->setIs_verified((bool)$isVerified);

            $this->entityManager->flush();
            $this->addFlash('success', 'Review updated successfully!');

            return $this->redirectToRoute('app_admin_avis_show', ['id' => $avi->getAvis_id()]);
        }

        return $this->render('back/avis/edit.html.twig', [
            'avi' => $avi,
        ]);
    }

    #[Route('/avis/{id}/delete', name: 'app_admin_avis_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function avisDelete(Request $request, Avis $avi): Response
    {
        // Validate CSRF token
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete_avis', $token)) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_admin_avis_index');
        }

        $this->entityManager->remove($avi);
        $this->entityManager->flush();
        $this->addFlash('success', 'Review deleted successfully!');

        return $this->redirectToRoute('app_admin_avis_index');
    }
}
