<?php

namespace App\Security;

use App\Entity\UsersAvis\User;
use App\Service\Auth\UserAuthStateService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

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
        $request->getSession()->remove('_totp_login_requested');

        return new RedirectResponse($this->urlGenerator->generate('app_user_dashboard'));
    }
}
