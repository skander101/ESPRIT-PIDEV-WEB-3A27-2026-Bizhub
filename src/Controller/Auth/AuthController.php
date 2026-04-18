<?php

namespace App\Controller\Auth;

use App\Entity\UsersAvis\User;
use App\Form\Auth\ForgotPasswordRequestType;
use App\Form\Auth\ResetPasswordType;
use App\Repository\UsersAvis\UserRepository;
use App\Service\Auth\UserAuthStateService;
use App\Service\Auth\AuthMailerService;
use App\Service\Auth\GoogleOAuthService;
use App\Service\Auth\SecureTokenService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\FormLoginAuthenticator;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use BaconQrCode\Writer;

#[Route('')]
class AuthController extends AbstractController
{
    private const PASSWORD_RESET_EXPIRY_MINUTES = 15;
    private const EMAIL_VERIFICATION_EXPIRY_MINUTES = 15;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private SecureTokenService $tokenService,
        private UserAuthStateService $userAuthStateService,
        private AuthMailerService $authMailerService,
        private GoogleOAuthService $googleOAuthService,
        private LoggerInterface $logger,
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    /**
     * Endpoint to request password reset links.
     */
    #[Route('/forgot-password', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function requestPasswordReset(Request $request): Response
    {
        $form = $this->createForm(ForgotPasswordRequestType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{email: string} $data */
            $data = $form->getData();
            $user = $this->userRepository->findByEmail((string) $data['email']);

            // Return the same user-facing message regardless of account existence to prevent account enumeration.
            if ($user instanceof User) {
                $token = $this->tokenService->generateToken();
                $expiresAt = (new \DateTimeImmutable())->modify(sprintf('+%d minutes', self::PASSWORD_RESET_EXPIRY_MINUTES));
                $authState = $this->userAuthStateService->getOrCreate($user);

                $authState->setPasswordResetToken($token);
                $authState->setPasswordResetTokenExpiresAt($expiresAt);
                $this->entityManager->flush();

                $resetUrl = $this->generateUrl('app_reset_password', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);
                $this->authMailerService->sendPasswordResetEmail($user, $resetUrl, self::PASSWORD_RESET_EXPIRY_MINUTES);
            }

            $this->addFlash('success', 'If your email is registered, a secure reset link has been sent.');

            return $this->redirectToRoute('app_forgot_password');
        }

        return $this->render('auth/forgot_password.html.twig', [
            'form' => $form,
        ]);
    }

    /**
     * Endpoint to submit a new password using a secure token.
     */
    #[Route('/reset-password/{token}', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function resetPassword(string $token, Request $request): Response
    {
        $authState = $this->userAuthStateService->findByPasswordResetToken($token);
        $user = $authState?->getUser();
        $isTokenInvalid = !$user instanceof User
            || !$authState?->getPasswordResetTokenExpiresAt() instanceof \DateTimeInterface
            || $authState->getPasswordResetTokenExpiresAt() < new \DateTimeImmutable();

        if ($isTokenInvalid) {
            $this->addFlash('error', 'This password reset link is invalid or expired.');
            return $this->redirectToRoute('app_forgot_password');
        }

        $form = $this->createForm(ResetPasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = (string) $form->get('password')->getData();

            $user->setPasswordHash($this->passwordHasher->hashPassword($user, $plainPassword));
            $authState->setPasswordResetToken(null);
            $authState->setPasswordResetTokenExpiresAt(null);
            $this->entityManager->flush();

            $this->addFlash('success', 'Password updated successfully. You can now log in.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('auth/reset_password.html.twig', [
            'form' => $form,
            'token' => $token,
        ]);
    }

    /**
     * Pending verification page shown when user is authenticated but not verified.
     */
    #[Route('/verify/pending', name: 'app_verify_pending', methods: ['GET'])]
    public function verifyPending(): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        /** @var User $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('verify_email.html.twig', [
            'email' => $user->getEmail(),
        ]);
    }

    /**
     * Endpoint to verify user email from the signed link token.
     */
    #[Route('/verify-email/{token}', name: 'app_verify_email', methods: ['GET'])]
    public function verifyEmail(
        string $token,
        Request $request,
    ): Response
    {
        $this->logger->info('Email verification attempt started.', [
            'token_prefix' => substr($token, 0, 8) . '...',
            'ip' => $request->getClientIp(),
        ]);

        $authState = $this->userAuthStateService->findByVerificationToken($token);
        $user = $authState?->getUser();

        if (!$user instanceof User) {
            $this->logger->warning('Email verification failed: user not found for token.');
            $this->addFlash('error', 'Email verification link is invalid or expired.');
            return $this->redirectToRoute('app_login');
        }

        $this->logger->info('User found for verification.', [
            'user_id' => $user->getUserId(),
            'email' => $user->getEmail(),
            'auth_state_id' => $authState?->getId(),
            'is_verified_before' => $authState?->isVerified(),
            'token_expires' => $authState?->getVerificationTokenExpiresAt()?->format('Y-m-d H:i:s'),
        ]);

        $isTokenInvalid = !$authState?->getVerificationTokenExpiresAt() instanceof \DateTimeInterface
            || $authState->getVerificationTokenExpiresAt() < new \DateTimeImmutable();

        if ($isTokenInvalid) {
            if ($authState?->isVerified()) {
                $this->logger->info('Email already verified, user can log in.');
                $this->addFlash('info', 'Your email is already verified. Please log in.');
                return $this->redirectToRoute('app_login');
            }
            $this->logger->warning('Email verification failed: token expired or invalid.');
            $this->addFlash('error', 'Email verification link is invalid or expired.');
            return $this->redirectToRoute('app_login');
        }

        if ($authState->isVerified()) {
            $this->logger->info('Email already verified (second check).');
            $this->addFlash('info', 'Your email is already verified. Please log in.');
            return $this->redirectToRoute('app_login');
        }

        $authState->setIsVerified(true);
        $authState->setVerificationToken(null);
        $authState->setVerificationTokenExpiresAt(null);
        $this->entityManager->flush();

        $this->logger->info('Email verified successfully in database.', [
            'user_id' => $user->getUserId(),
            'email' => $user->getEmail(),
            'is_verified_after' => $authState->isVerified(),
        ]);

        $this->addFlash('success', 'Email verified successfully! You can now log in.');

        return $this->redirectToRoute('app_login');
    }

    /**
     * Endpoint to resend email verification links for not-yet-verified users.
     */
    #[Route('/verify-email/resend', name: 'app_verify_email_resend', methods: ['POST'])]
    public function resendVerificationEmail(Request $request): RedirectResponse
    {
        $this->logger->debug('Resend verification email requested.', [
            'flow' => 'email_verification_resend',
            'ip' => $request->getClientIp(),
        ]);

        if (!$this->isCsrfTokenValid('resend_verification_email', (string) $request->request->get('_token'))) {
            $this->logger->warning('Resend verification email rejected: invalid CSRF token.', [
                'flow' => 'email_verification_resend',
            ]);
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_login');
        }

        $email = trim((string) $request->request->get('email', ''));
        $user = $this->userRepository->findByEmail($email);

        $this->logger->debug('Resend verification email eligibility evaluated.', [
            'flow' => 'email_verification_resend',
            'email' => $email,
            'user_found' => $user instanceof User,
            'already_verified' => $user instanceof User ? $this->userAuthStateService->isVerified($user) : null,
        ]);

        if ($user instanceof User && !$this->userAuthStateService->isVerified($user)) {
            $verificationToken = $this->tokenService->generateToken();
            $verificationExpiresAt = (new \DateTimeImmutable())->modify(sprintf('+%d minutes', self::EMAIL_VERIFICATION_EXPIRY_MINUTES));
            $authState = $this->userAuthStateService->getOrCreate($user);

            $authState->setVerificationToken($verificationToken);
            $authState->setVerificationTokenExpiresAt($verificationExpiresAt);
            $this->entityManager->flush();

            $verificationUrl = $this->generateUrl('app_verify_email', ['token' => $verificationToken], UrlGeneratorInterface::ABSOLUTE_URL);
            $this->logger->info('Dispatching verification email from resend endpoint.', [
                'flow' => 'email_verification_resend',
                'recipient' => (string) $user->getEmail(),
                'verification_url' => $verificationUrl,
            ]);
            $this->authMailerService->sendEmailVerification($user, $verificationUrl, self::EMAIL_VERIFICATION_EXPIRY_MINUTES);
        } else {
            $this->logger->info('Verification email not dispatched from resend endpoint.', [
                'flow' => 'email_verification_resend',
                'email' => $email,
                'reason' => !$user instanceof User ? 'user_not_found' : 'already_verified',
            ]);
        }

        $this->addFlash('success', 'If your account is eligible, a verification email has been sent.');

        return $this->redirectToRoute('app_login');
    }

    /**
     * Initiates Google OAuth login flow.
     */
    #[Route('/oauth/google/connect', name: 'app_google_oauth_connect', methods: ['GET'])]
    public function googleConnect(Request $request): RedirectResponse
    {
        $state = $this->tokenService->generateToken(32);
        $request->getSession()->set('google_oauth_state', $state);

        $redirectUri = $this->generateUrl('app_google_oauth_callback', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $authorizationUrl = $this->googleOAuthService->buildAuthorizationUrl($redirectUri, $state);

        return $this->redirect($authorizationUrl);
    }

    /**
     * Handles Google OAuth callback and authenticates or provisions local users.
     */
    #[Route('/oauth/google/callback', name: 'app_google_oauth_callback', methods: ['GET'])]
    public function googleCallback(
        Request $request,
        UserAuthenticatorInterface $userAuthenticator,
        FormLoginAuthenticator $formLoginAuthenticator,
    ): Response {
        $state = (string) $request->query->get('state', '');
        $savedState = (string) $request->getSession()->get('google_oauth_state', '');

        if ($state === '' || $savedState === '' || !$this->tokenService->equals($savedState, $state)) {
            $this->addFlash('error', 'Invalid OAuth state. Please try again.');
            return $this->redirectToRoute('app_login');
        }

        $request->getSession()->remove('google_oauth_state');

        $code = (string) $request->query->get('code', '');
        if ($code === '') {
            $this->addFlash('error', 'Google login failed: missing authorization code.');
            return $this->redirectToRoute('app_login');
        }

        $redirectUri = $this->generateUrl('app_google_oauth_callback', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $tokens = $this->googleOAuthService->exchangeCodeForTokens($code, $redirectUri);

        if (!isset($tokens['access_token']) || !is_string($tokens['access_token'])) {
            $this->addFlash('error', 'Google login failed: token exchange was rejected.');
            return $this->redirectToRoute('app_login');
        }

        $googleUser = $this->googleOAuthService->fetchUserInfo($tokens['access_token']);
        $googleUserId = isset($googleUser['sub']) ? (string) $googleUser['sub'] : '';
        $email = isset($googleUser['email']) ? (string) $googleUser['email'] : '';
        $fullName = isset($googleUser['name']) ? (string) $googleUser['name'] : '';

        if ($googleUserId === '' || $email === '') {
            $this->addFlash('error', 'Google did not return required profile fields.');
            return $this->redirectToRoute('app_login');
        }

        $user = $this->userAuthStateService->findUserByOauthIdentity('google', $googleUserId) ?? $this->userRepository->findByEmail($email);

        if (!$user instanceof User) {
            // New users created from OAuth login are marked as verified by provider trust.
            $user = (new User())
                ->setEmail($email)
                ->setFullName($fullName !== '' ? $fullName : 'Google User')
                ->setUserType('startup')
                ->setCreatedAt(new \DateTime())
                ->setIsActive(true);

            $user->setPasswordHash($this->passwordHasher->hashPassword($user, $this->tokenService->generateToken()));
        }

        $this->entityManager->persist($user);

        $authState = $this->userAuthStateService->getOrCreate($user);
        $authState
            ->setIsVerified(true)
            ->setOauthProvider('google')
            ->setOauthProviderId($googleUserId);

        $this->entityManager->flush();

        return $userAuthenticator->authenticateUser($user, $formLoginAuthenticator, $request);
    }
}
