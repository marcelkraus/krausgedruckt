<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

/**
 * Counts failed attempts against the backend and refuses further ones.
 *
 * Symfony ships `login_throttling`, and it does not help here: it only covers
 * authenticators it considers interactive, and HTTP Basic is not one. So the
 * admin path accepted passwords at whatever rate the network allowed, on a
 * repository that names both the path and — until this change — the user.
 *
 * Five failures per address per fifteen minutes. The check runs before the
 * password is verified, so an exhausted budget refuses the correct password
 * too; that is the point, and it is why the counter is only spent on a
 * failure. A successful login costs nothing.
 */
final class AdminLoginThrottleListener implements EventSubscriberInterface
{
    private const ADMIN_PATH_PREFIX = '/admin';

    public function __construct(
        #[Autowire(service: 'limiter.admin_login')] private readonly RateLimiterFactoryInterface $adminLoginLimiter,
        private readonly RequestStack $requestStack,
    ) {
        // Intentionally left blank.
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Ahead of the password check, so an exhausted budget stops the
            // attempt rather than merely recording it.
            CheckPassportEvent::class => ['onCheckPassport', 2048],
            LoginFailureEvent::class => 'onLoginFailure',
        ];
    }

    public function onCheckPassport(CheckPassportEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request === null || $this->isAdminRequest($request) === false) {
            return;
        }

        // Consuming zero tokens asks the question without spending anything.
        // The answer has to be read from the remaining count rather than from
        // `isAccepted()`: a request for zero tokens is always granted, so the
        // accepted flag stays true even after the budget is gone.
        if ($this->limiterFor($request)->consume(0)->getRemainingTokens() < 1) {
            throw new TooManyLoginAttemptsAuthenticationException();
        }
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $request = $event->getRequest();

        if ($this->isAdminRequest($request) === false) {
            return;
        }

        $this->limiterFor($request)->consume(1);
    }

    private function isAdminRequest(Request $request): bool
    {
        return str_starts_with($request->getPathInfo(), self::ADMIN_PATH_PREFIX);
    }

    private function limiterFor(Request $request): \Symfony\Component\RateLimiter\LimiterInterface
    {
        return $this->adminLoginLimiter->create($request->getClientIp() ?? 'unknown');
    }
}
