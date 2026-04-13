<?php

namespace App\EventListener;

use Scheb\TwoFactorBundle\Security\TwoFactor\Event\TwoFactorAuthenticationEvents;
use Scheb\TwoFactorBundle\Security\TwoFactor\Event\TwoFactorAuthenticationEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class TwoFactorAuthenticationListener implements EventSubscriberInterface
{
    public function __construct(private RequestStack $requestStack)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TwoFactorAuthenticationEvents::SUCCESS => 'onAuthenticationSuccess',
            TwoFactorAuthenticationEvents::COMPLETE => 'onAuthenticationComplete',
        ];
    }

    public function onAuthenticationSuccess(TwoFactorAuthenticationEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request !== null) {
            $request->getSession()->set('mfa_verified', true);
            $request->getSession()->remove('mfa_pending');
        }
    }

    public function onAuthenticationComplete(TwoFactorAuthenticationEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request !== null) {
            $request->getSession()->set('mfa_verified', true);
            $request->getSession()->remove('mfa_pending');
        }
    }
}
