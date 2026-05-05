<?php

namespace App\EventListener;

use App\Entity\UsersAvis\User;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Bundle\SecurityBundle\Security;

class MfaEnforcementListener
{
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
        'app_user_dashboard',
        'app_front_index',
        'app_admin_index',
        'app_transition',
    ];

    public function __construct(
        private readonly Security $security,
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

        if (!$user->isTotpAuthenticationEnabled()) {
            return;
        }

        if ((bool) $request->getSession()->get('mfa_verified', false)) {
            return;
        }

        // TOTP button (Button 1): user already verified OTP in /login/totp form
        $totpRequested = (bool) $request->getSession()->get('_totp_login_requested', false);
        if ($totpRequested) {
            $request->getSession()->remove('_totp_login_requested');
            $request->getSession()->set('mfa_verified', true);
            return;
        }

        // Face login (Button 2): explicitly skip 2FA
        if ((bool) $request->getSession()->get('login_via_face', false)) {
            $request->getSession()->set('mfa_verified', true);
            return;
        }

        // Email/password login (Button 3): no 2FA
        $request->getSession()->set('mfa_verified', true);
    }
}
