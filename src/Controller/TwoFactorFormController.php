<?php

namespace App\Controller;

use App\Entity\UsersAvis\User;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TwoFactorFormController extends AbstractController
{
    #[Route('/2fa', name: '2fa_login', methods: ['GET', 'POST'])]
    public function form(Request $request, TotpAuthenticator $totpAuthenticator): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$user->isTotpAuthenticationEnabled()) {
            $request->getSession()->set('mfa_verified', true);
            return $this->redirectToRoute('app_user_dashboard');
        }

        $error = null;
        
        if ($request->isMethod('POST')) {
            $code = $request->request->get('_auth_code', '');
            $code = preg_replace('/[^0-9]/', '', $code);

            if (strlen($code) !== 6) {
                $error = 'Invalid code format';
            } elseif ($totpAuthenticator->checkCode($user, $code)) {
                $request->getSession()->set('mfa_verified', true);
                return $this->redirectToRoute('app_user_dashboard');
            } else {
                $error = 'Invalid authentication code';
            }
        }

        return $this->render('security/2fa_form.html.twig', [
            'authenticationError' => $error,
            'check_path_url' => $this->generateUrl('2fa_login'),
        ]);
    }
}
