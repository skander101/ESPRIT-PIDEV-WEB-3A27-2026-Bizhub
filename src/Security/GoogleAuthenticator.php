<?php

namespace App\Security;

use App\Entity\UsersAvis\User;
use App\Repository\UsersAvis\UserRepository;
use App\Service\Auth\GoogleOAuthService;
use App\Service\Auth\UserAuthStateService;
use App\Service\Auth\SecureTokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\Token\PostAuthenticationToken;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Psr\Log\LoggerInterface;

class GoogleAuthenticator extends AbstractAuthenticator implements AuthenticationFailureHandlerInterface, AuthenticationEntryPointInterface
{
    public function __construct(
        private GoogleOAuthService $googleOAuthService,
        private UserAuthStateService $userAuthStateService,
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private SecureTokenService $tokenService,
        private RouterInterface $router,
        private LoggerInterface $logger,
    ) {
    }

    public function supports(Request $request): bool
    {
        return $request->getPathInfo() === '/connect/google/check'
            && $request->query->has('code');
    }

    public function authenticate(Request $request): Passport
    {
        $state = (string) $request->query->get('state', '');
        $savedState = (string) $request->getSession()->get('google_oauth_state', '');

        if ($state === '' || $savedState === '' || !$this->tokenService->equals($savedState, $state)) {
            throw new CustomUserMessageAuthenticationException('Invalid OAuth state. Please try again.');
        }

        $request->getSession()->remove('google_oauth_state');

        $code = (string) $request->query->get('code', '');
        if ($code === '') {
            throw new CustomUserMessageAuthenticationException('Google login failed: missing authorization code.');
        }

        try {
            $tokens = $this->googleOAuthService->exchangeCodeForTokens($code);
        } catch (\RuntimeException $e) {
            $this->logger->error('Google token exchange failed', [
                'error' => $e->getMessage(),
            ]);
            throw new CustomUserMessageAuthenticationException($e->getMessage());
        }

        if (!isset($tokens['access_token']) || !is_string($tokens['access_token'])) {
            throw new CustomUserMessageAuthenticationException('Google login failed: token exchange was rejected.');
        }

        try {
            $googleUser = $this->googleOAuthService->fetchUserInfo($tokens['access_token']);
        } catch (\RuntimeException $e) {
            $this->logger->error('Google userinfo fetch failed', [
                'error' => $e->getMessage(),
            ]);
            throw new CustomUserMessageAuthenticationException('Failed to fetch Google user info: ' . $e->getMessage());
        }

        $googleUserId = isset($googleUser['sub']) ? (string) $googleUser['sub'] : '';
        $email = isset($googleUser['email']) ? (string) $googleUser['email'] : '';
        $fullName = isset($googleUser['name']) ? (string) $googleUser['name'] : '';

        if ($googleUserId === '' || $email === '') {
            throw new CustomUserMessageAuthenticationException('Google did not return required profile fields.');
        }

        $user = $this->userAuthStateService->findUserByOauthIdentity('google', $googleUserId)
            ?? $this->userRepository->findByEmail($email);

        if (!$user instanceof User) {
            $user = (new User())
                ->setEmail($email)
                ->setFullName($fullName !== '' ? $fullName : 'Google User')
                ->setUserType('startup')
                ->setCreatedAt(new \DateTime())
                ->setIsActive(true);

            $user->setPasswordHash($this->passwordHasher->hashPassword($user, $this->tokenService->generateToken()));

            $this->entityManager->persist($user);
        }

        $authState = $this->userAuthStateService->getOrCreate($user);
        $authState
            ->setIsVerified(true)
            ->setOauthProvider('google')
            ->setOauthProviderId($googleUserId);

        $this->entityManager->flush();

        return new SelfValidatingPassport(new UserBadge($email, function () use ($user) {
            return $user;
        }));
    }

    public function onAuthenticationFailure(Request $request, ?AuthenticationException $authException = null): RedirectResponse
    {
        $this->logger->warning('Google OAuth authentication failed', [
            'message' => $authException?->getMessage(),
        ]);

        return new RedirectResponse($this->router->generate('app_login'));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): RedirectResponse
    {
        return new RedirectResponse($this->router->generate('app_user_dashboard'));
    }

    public function start(Request $request, ?AuthenticationException $authException = null): RedirectResponse
    {
        return new RedirectResponse($this->router->generate('app_login'));
    }

    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        return new PostAuthenticationToken(
            $passport->getUser(),
            $firewallName,
            $passport->getUser()->getRoles()
        );
    }
}