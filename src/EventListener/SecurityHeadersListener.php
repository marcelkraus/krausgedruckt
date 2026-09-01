<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Adds a small set of hardening headers to every response. A hosting
 * environment may override them – the server always has the last word.
 */
#[AsEventListener(event: KernelEvents::RESPONSE)]
final class SecurityHeadersListener
{
    public function __invoke(ResponseEvent $event): void
    {
        if ($event->isMainRequest() === false) {
            return;
        }

        $headers = $event->getResponse()->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('X-Frame-Options', 'DENY');

        // The one header that depends on how the request arrived. It belongs
        // to the site rather than to the machine underneath: a host that adds
        // one of its own states the same year, and a host that adds none
        // leaves the promise where it is made.
        //
        // Only over HTTPS: a browser ignores it on a plain request, and
        // sending it there states something that is not true.
        if ($event->getRequest()->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000');
        }
    }
}
