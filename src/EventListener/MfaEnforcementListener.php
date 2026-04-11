<?php

namespace App\EventListener;

use App\Entity\UsersAvis\User;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Enforces TOTP 2FA completion for authenticated users that enabled it via Scheb bundle.
 */
class MfaEnforcementListener
{
    /**
     * Routes that must remain accessible before 2FA challenge is completed.
     */
    private const MFA_ALLOWED_ROUTES = [
        '2fa_login',
        '2fa_login_check',
        'app_2fa_setup',
        'app_2fa_setup_confirm',
        'app_2fa_disable',
        'app_logout',
        'app_login',
        'app_forgot_password',
        'app_reset_password',
        'app_verify_email',
        'app_verify_email_resend',
        'app_google_oauth_connect',
        'app_google_oauth_callback',
        'app_user_profile',
        'app_user_edit',
        'app_user_edit_specific',
        'app_user_avatar',
    ];

    public function __construct(
        private Security $security,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = (string) $request->attributes->get('_route', '');

        if ($route === '' || in_array($route, self::MFA_ALLOWED_ROUTES, true)) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        // Only enforce TOTP for users who have enabled it via Scheb (totp_secret is set)
        if (!$user->isTotpAuthenticationEnabled()) {
            return;
        }

        if ((bool) $request->getSession()->get('mfa_verified', false)) {
            return;
        }

        $event->setResponse(new RedirectResponse($this->urlGenerator->generate('2fa_login')));
    }
}
