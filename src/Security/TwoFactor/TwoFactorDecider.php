<?php

namespace App\Security\TwoFactor;

use App\Entity\UsersAvis\User;
use Scheb\TwoFactorBundle\Security\TwoFactor\AuthenticationContextInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Condition\TwoFactorConditionInterface;

class TwoFactorDecider implements TwoFactorConditionInterface
{
    public function shouldPerformTwoFactorAuthentication(AuthenticationContextInterface $context): bool
    {
        $user = $context->getUser();
        if (!$user instanceof User) {
            return false;
        }

        if (!$user->isTotpAuthenticationEnabled()) {
            return false;
        }

        $session = $context->getSession();
        $requested = (bool) $session->get('_totp_login_requested', false);

        if ($requested) {
            $session->remove('_totp_login_requested');
        }

        return $requested;
    }
}
