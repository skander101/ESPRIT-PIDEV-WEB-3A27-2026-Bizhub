<?php

namespace App\Controller\UsersAvis;

use App\Entity\UsersAvis\User;
use App\Form\UsersAvis\UserType;
use App\Form\UsersAvis\AvatarType;
use App\Repository\UsersAvis\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('')]
class UserController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private SluggerInterface $slugger,
    ) {
    }

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(Request $request): Response
    {
        // Already logged in users can't register
        if ($this->getUser()) {
            return $this->redirectToRoute('app_user_dashboard');
        }

        $user = new User();
        $form = $this->createForm(UserType::class, $user, ['mode' => 'register']);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('password')->getData();

            // Hash password
            $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
            $user->setPassword_hash($hashedPassword);

            // Set default values
            $user->setCreated_at(new \DateTime());
            $user->setIs_active(true);

            // Save user
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $this->addFlash('success', 'Account created successfully! Please log in with your credentials.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('signup.html.twig', ['form' => $form]);
    }

    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_user_dashboard');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route('/logout', name: 'app_logout', methods: ['GET'])]
    public function logout(): Response
    {
        // This method will be intercepted by the logout key on the firewall
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on the firewall.');
    }

    #[Route('/dashboard', name: 'app_user_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Redirect based on user role
        if ($user->getUser_type() === 'admin') {
            return $this->redirectToRoute('app_admin_index');
        }

        return $this->redirectToRoute('app_front_index');
    }

    #[Route('/front/dashboard', name: 'app_front_dashboard', methods: ['GET'])]
    public function frontDashboard(): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('front/user/dashboard.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/profile', name: 'app_user_profile', methods: ['GET'])]
    public function profile(): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('front/user/profile.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/profile/edit', name: 'app_user_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $form = $this->createForm(UserType::class, $user, ['mode' => 'edit']);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle avatar upload
            $avatarFile = $form->get('avatar')->getData();

            if ($avatarFile) {
                $originalFilename = pathinfo($avatarFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $this->slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $avatarFile->guessExtension();

                try {
                    $avatarFile->move(
                        $this->getParameter('kernel.project_dir') . '/public/assets/images/avatars',
                        $newFilename
                    );

                    // Delete old avatar if exists
                    $oldAvatar = $user->getAvatarUrl();
                    if ($oldAvatar && file_exists($this->getParameter('kernel.project_dir') . '/public' . $oldAvatar)) {
                        unlink($this->getParameter('kernel.project_dir') . '/public' . $oldAvatar);
                    }

                    // Set new avatar URL
                    $user->setAvatarUrl('/assets/images/avatars/' . $newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Failed to upload avatar: ' . $e->getMessage());
                    return $this->render('front/user/edit.html.twig', [
                        'form' => $form,
                        'user' => $user,
                    ]);
                }
            }

            $this->entityManager->flush();
            $this->addFlash('success', 'Profile updated successfully!');

            return $this->redirectToRoute('app_user_profile');
        }

        return $this->render('front/user/edit.html.twig', [
            'form' => $form,
            'user' => $user,
        ]);
    }

    #[Route('/profile/edit-specific', name: 'app_user_edit_specific', methods: ['GET', 'POST'])]
    public function editSpecific(Request $request): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $form = $this->createForm(UserType::class, $user, ['mode' => 'editSpecific']);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'Your role-specific information has been updated successfully!');

            return $this->redirectToRoute('app_user_profile');
        }

        return $this->render('front/user/specific.html.twig', [
            'form' => $form,
            'user' => $user,
        ]);
    }

    #[Route('/profile/avatar', name: 'app_user_avatar', methods: ['GET', 'POST'])]
    public function avatar(Request $request): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $form = $this->createForm(AvatarType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $avatarFile = $form->get('avatar')->getData();

            if ($avatarFile) {
                $originalFilename = pathinfo($avatarFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $this->slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $avatarFile->guessExtension();

                try {
                    $avatarFile->move(
                        $this->getParameter('kernel.project_dir') . '/public/assets/images/avatars',
                        $newFilename
                    );

                    // Delete old avatar if exists
                    $oldAvatar = $user->getAvatarUrl();
                    if ($oldAvatar && file_exists($this->getParameter('kernel.project_dir') . '/public' . $oldAvatar)) {
                        unlink($this->getParameter('kernel.project_dir') . '/public' . $oldAvatar);
                    }

                    // Set new avatar URL
                    $user->setAvatarUrl('/assets/images/avatars/' . $newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Failed to upload avatar: ' . $e->getMessage());
                    return $this->render('front/user/avatar.html.twig', [
                        'form' => $form,
                        'user' => $user,
                    ]);
                }
            }

            $this->entityManager->flush();
            $this->addFlash('success', 'Profile picture updated successfully!');

            return $this->redirectToRoute('app_user_profile');
        }

        return $this->render('front/user/avatar.html.twig', [
            'form' => $form,
            'user' => $user,
        ]);
    }
}
