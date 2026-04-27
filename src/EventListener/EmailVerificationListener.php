<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\UsersAvis\User;
use App\Service\Auth\UserAuthStateService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class EmailVerificationListener implements EventSubscriberInterface
{
    private const EXCLUDED_ROUTES = [
        'app_verify_email',
        'app_verify_email_resend',
        'app_logout',
        '_profiler',
        '_wdt',
    ];

    public function __construct(
        private readonly UserAuthStateService $userAuthStateService,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', -10],
        ];
    }

    public function onKernelRequest(\Symfony\Component\HttpKernel\Event\RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = $request->attributes->get('_route');

        if ($route === null) {
            return;
        }

        if (in_array($route, self::EXCLUDED_ROUTES, true)) {
            return;
        }

        $token = $request->getSession()?->get('_security_main');
        if ($token === null) {
            return;
        }

        $user = $this->getUserFromToken($token);
        if (!$user instanceof User) {
            return;
        }

        if ($this->userAuthStateService->isVerified($user)) {
            return;
        }

        if ($route === 'app_verify_pending') {
            return;
        }

        $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_verify_pending')));
    }

    private function getUserFromToken(string $token): ?User
    {
        if (!str_starts_with($token, 'a:')) {
            return null;
        }

        try {
            $data = unserialize($token, ['allowed_classes' => [User::class]] ?? User::class);
            if ($data instanceof User) {
                return $data;
            }
        } catch (\Exception) {
        }

        return null;
    }
}