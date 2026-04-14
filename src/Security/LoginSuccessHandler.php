<?php

namespace App\Security;

use App\Entity\UsersAvis\User;
use App\Service\Auth\UserAuthStateService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

/**
 * Controls post-login routing and initializes MFA session state.
 */
class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private UserAuthStateService $userAuthStateService,
    )
    {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?RedirectResponse
    {
        $user = $token->getUser();

        if ((bool) $request->getSession()->get('login_via_totp', false)) {
            $request->getSession()->remove('login_via_totp');
            $request->getSession()->set('mfa_verified', true);

            return new RedirectResponse($this->urlGenerator->generate('app_user_dashboard'));
        }

        if ((bool) $request->getSession()->get('login_via_face', false)) {
            $request->getSession()->remove('login_via_face');
            $request->getSession()->set('mfa_verified', true);

            return new RedirectResponse($this->urlGenerator->generate('app_user_dashboard'));
        }

        // Default: go to dashboard - MFA and face are optional via profile
        return new RedirectResponse($this->urlGenerator->generate('app_user_dashboard'));
    }
}
