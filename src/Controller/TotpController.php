<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\UsersAvis\User;
use App\Service\Auth\UserAuthStateService;
use Doctrine\ORM\EntityManagerInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

#[Route('/2fa')]
class TotpController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserAuthStateService $userAuthStateService,
    ) {
    }

    public function render2faForm(Request $request): Response
    {
        $error = $request->attributes->get('authenticationError');
        $errorMessage = $error ? $error->getMessageKey() : null;
        
        return $this->render('security/2fa_form.html.twig', [
            'authenticationError' => $errorMessage,
            'check_path_url' => $this->generateUrl('2fa_login_check'),
        ]);
    }

    #[Route('/setup', name: 'app_2fa_setup', methods: ['GET'])]
    public function setup(Request $request, TotpAuthenticator $totpAuthenticator): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        /** @var User $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $secret = $totpAuthenticator->generateSecret();
        $request->getSession()->set('totp_secret_pending', $secret);

        $tempUser = new class($secret, $user) implements \Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface {
            private string $secret;
            private User $user;

            public function __construct(string $secret, User $user) {
                $this->secret = $secret;
                $this->user = $user;
            }

            public function getTotpAuthenticationConfiguration(): \Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration {
                return new \Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration(
                    $this->secret,
                    \Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration::ALGORITHM_SHA1,
                    30,
                    6
                );
            }

            public function isTotpAuthenticationEnabled(): bool {
                return true;
            }

            public function getTotpAuthenticationUsername(): string {
                return $this->user->getEmail() ?? '';
            }
        };

        $qrContent = $totpAuthenticator->getQRContent($tempUser);

        $renderer = new \BaconQrCode\Renderer\ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);
        $qrCode = $writer->writeString($qrContent);

        return $this->render('auth/totp_setup.html.twig', [
            'secret' => $secret,
            'qrCode' => base64_encode($qrCode),
            'qrContent' => $qrContent,
            'user' => $user,
        ]);
    }

    #[Route('/setup/confirm', name: 'app_2fa_setup_confirm', methods: ['POST'])]
    public function setupConfirm(Request $request, TotpAuthenticator $totpAuthenticator): RedirectResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        /** @var User $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $secret = $request->getSession()->get('totp_secret_pending');
        if ($secret === null) {
            $this->addFlash('error', 'No TOTP setup in progress. Please start over.');
            return $this->redirectToRoute('app_2fa_setup');
        }

        $code = (string) $request->request->get('code');
        if (!$this->verifyCodeWithSecret($user, $code, $secret, $totpAuthenticator)) {
            $this->addFlash('error', 'Invalid authentication code. Please try again.');
            return $this->redirectToRoute('app_2fa_setup');
        }

        $user->setTotp_secret($secret);
        $this->entityManager->flush();

        $authState = $this->userAuthStateService->getOrCreate($user);
        $authState->setMfaEnabled(true);
        $this->entityManager->flush();

        $request->getSession()->remove('totp_secret_pending');

        $this->addFlash('success', 'TOTP is now enabled.');

        return $this->redirectToRoute('app_user_dashboard');
    }

    private function verifyCodeWithSecret(User $user, string $code, string $secret, TotpAuthenticator $totpAuthenticator): bool
    {
        $code = str_replace(' ', '', $code);
        if (strlen($code) !== 6) {
            return false;
        }

        $tempUser = new class($secret, $user) implements \Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface {
            private string $secret;
            private User $user;

            public function __construct(string $secret, User $user) {
                $this->secret = $secret;
                $this->user = $user;
            }

            public function getTotpAuthenticationConfiguration(): \Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration {
                return new \Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration(
                    $this->secret,
                    \Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration::ALGORITHM_SHA1,
                    30,
                    6
                );
            }

            public function isTotpAuthenticationEnabled(): bool {
                return true;
            }

            public function getTotpAuthenticationUsername(): string {
                return $this->user->getEmail() ?? '';
            }
        };

        $valid = $totpAuthenticator->checkCode($tempUser, $code);

        return $valid;
    }

    #[Route('/disable', name: 'app_2fa_disable', methods: ['POST'])]
    public function disable(Request $request, EntityManagerInterface $entityManager): RedirectResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        /** @var User $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('disable_2fa', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_user_dashboard');
        }

        $user->setTotp_secret(null);
        $entityManager->flush();

        $authState = $this->userAuthStateService->getOrCreate($user);
        $authState->setMfaEnabled(false);
        $entityManager->flush();

        $this->addFlash('success', 'TOTP has been disabled.');

        return $this->redirectToRoute('app_user_dashboard');
    }
}