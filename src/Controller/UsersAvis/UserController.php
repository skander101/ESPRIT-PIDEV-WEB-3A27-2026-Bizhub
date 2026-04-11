<?php

namespace App\Controller\UsersAvis;

use App\Entity\UsersAvis\User;
use App\Form\UsersAvis\UserType;
use App\Form\UsersAvis\AvatarType;
use App\Form\Auth\TotpLoginType;
use App\Repository\UsersAvis\UserRepository;
use App\Service\Auth\AuthMailerService;
use App\Service\Auth\SecureTokenService;
use App\Service\Auth\UserAuthStateService;
use App\Service\FacePlusPlus\FaceRecognitionService;
use App\Service\FacePlusPlus\ImagePreprocessingService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('')]
class UserController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private SluggerInterface $slugger,
        private SecureTokenService $tokenService,
        private UserAuthStateService $userAuthStateService,
        private AuthMailerService $authMailerService,
        private FaceRecognitionService $faceRecognitionService,
        private ImagePreprocessingService $imagePreprocessingService,
        private LoggerInterface $logger,
        private TokenStorageInterface $tokenStorage,
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

            $verificationToken = $this->tokenService->generateToken();
            $verificationExpiresAt = (new \DateTimeImmutable())->modify('+15 minutes');
            $authState = $this->userAuthStateService->getOrCreate($user);
            $authState
                ->setIsVerified(false)
                ->setVerificationToken($verificationToken)
                ->setVerificationTokenExpiresAt($verificationExpiresAt);

            try {
                $this->entityManager->flush();
            } catch (UniqueConstraintViolationException) {
                $form->get('email')->addError(new FormError('This email is already used.'));

                return $this->render('signup.html.twig', ['form' => $form]);
            }

            $verificationUrl = $this->generateUrl('app_verify_email', ['token' => $verificationToken], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL);
            $this->logger->info('Dispatching verification email after registration.', [
                'flow' => 'email_verification_register',
                'recipient' => (string) $user->getEmail(),
            ]);
            $this->authMailerService->sendEmailVerification($user, $verificationUrl, 15);

            $this->addFlash('success', 'Account created successfully! Please verify your email to activate your account.');

            return $this->render('auth/register_success.html.twig', [
                'email' => $user->getEmail(),
            ]);
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

    #[Route('/login/totp', name: 'app_login_totp', methods: ['GET', 'POST'])]
    public function loginWithTotp(Request $request): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_user_dashboard');
        }

        $form = $this->createForm(TotpLoginType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $email = trim((string) $data['email']);
            $otp = (string) $data['otp'];

            $user = $this->userRepository->findByEmail($email);

            if (!$user instanceof User || !$this->userAuthStateService->isVerified($user) || !$this->userAuthStateService->isMfaEnabled($user) || $user->getTotp_secret() === null) {
                $this->addFlash('error', 'TOTP login failed. Check your email/code or use password login.');
                return $this->redirectToRoute('app_login_totp');
            }

            $request->getSession()->set('login_via_totp', true);

            return $this->redirectToRoute('app_login');
        }

        return $this->render('auth/totp_login.html.twig', ['form' => $form]);
    }

    #[Route('/login/face', name: 'app_login_face', methods: ['GET', 'POST'])]
    public function loginWithFace(Request $request): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_user_dashboard');
        }

        if ($request->isMethod('GET')) {
            return $this->render('auth/face_login.html.twig');
        }

        $base64Image = (string) $request->request->get('image', '');
        if ($base64Image === '') {
            $this->addFlash('error', 'Please capture a photo first.');
            return $this->redirectToRoute('app_login_face');
        }

        if (str_starts_with($base64Image, 'data:')) {
            $base64Image = preg_replace('#^data:image/[^;]+;base64,#', '', $base64Image) ?: '';
        }

        try {
            $processedBase64 = $this->imagePreprocessingService->toGrayscaleBase64($base64Image);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            $this->addFlash('error', 'Failed to process image: ' . $e->getMessage());
            return $this->redirectToRoute('app_login_face');
        }

        $faceUsers = $this->userRepository->findActiveWithFaceEnrollment();
        if ($faceUsers === []) {
            $this->addFlash('error', 'No face-enrolled account is available for face login.');
            return $this->redirectToRoute('app_login');
        }

        $bestUser = null;
        $bestConfidence = 0.0;
        $faceDir = $this->getParameter('kernel.project_dir') . '/var/face_images';

        foreach ($faceUsers as $faceUser) {
            if (!$this->userAuthStateService->isVerified($faceUser)) {
                continue;
            }

            $storedImagePath = sprintf('%s/%d.jpg', $faceDir, $faceUser->getUserId());
            if (!file_exists($storedImagePath)) {
                $this->logger->warning('Face image file missing', ['path' => $storedImagePath]);
                continue;
            }

            $storedBase64 = base64_encode((string) file_get_contents($storedImagePath));
            
            $this->logger->debug('Comparing faces', [
                'user_id' => $faceUser->getUserId(),
                'stored_size' => strlen($storedBase64),
                'input_size' => strlen($processedBase64),
            ]);

            try {
                $confidence = $this->faceRecognitionService->compareFaces($storedBase64, $processedBase64);
            } catch (\App\Exception\FacePlusPlus\FaceComparisonException) {
                continue;
            }

            if ($confidence > $bestConfidence) {
                $bestConfidence = $confidence;
                $bestUser = $faceUser;
            }
        }

        if (!$bestUser instanceof User || $bestConfidence < 60.0) {
            $this->addFlash('error', sprintf('Face login failed (confidence: %.1f%%). Please try again or use email/password login.', $bestConfidence));
            return $this->redirectToRoute('app_login_face');
        }

        $request->getSession()->set('login_via_face', true);

        return $this->authenticateUser($bestUser, $request);
    }

    private function authenticateUser(User $user, Request $request): Response
    {
        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
        $this->tokenStorage->setToken($token);
        
        $request->getSession()->set('_security_main', serialize($token));
        $request->getSession()->set('login_via_face', true);
        
        return $this->redirectToRoute('app_user_dashboard');
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

            try {
                $this->entityManager->flush();
            } catch (UniqueConstraintViolationException) {
                $form->get('email')->addError(new FormError('This email is already used.'));

                return $this->render('front/user/edit.html.twig', [
                    'form' => $form,
                    'user' => $user,
                ]);
            }
            $this->addFlash('success', 'Profile updated successfully!');

            return $this->redirectToRoute('app_user_profile');
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('error', 'Profile was not saved. Please correct the highlighted fields and try again.');
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
