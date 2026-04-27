<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 10)]
class TrailingSlashListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        if (!$event->getThrowable() instanceof NotFoundHttpException) {
            return;
        }

        $request  = $event->getRequest();
        $pathInfo = $request->getPathInfo();

        if (!str_ends_with($pathInfo, '/') || $pathInfo === '/') {
            return;
        }

        $url = rtrim($pathInfo, '/');

        $qs = $request->getQueryString();
        if ($qs) {
            $url .= '?' . $qs;
        }

        $event->setResponse(new RedirectResponse($url, 301));
        $event->stopPropagation();
    }
}
