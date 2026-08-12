<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Pins the throttle in front of the backend.
 *
 * HTTP Basic answers every wrong password with the same 401, so a status code
 * cannot tell a refused password from a refused attempt. The test asks the
 * only question that can: after the budget is spent, is the *correct*
 * password refused too?
 */
final class BackendThrottleTest extends WebTestCase
{
    private const USER_NAME = 'test-admin';
    private const PASSWORD = 'test-passwort';
    private const WRONG_PASSWORD = 'nicht-das-passwort';

    /** The limit configured for `admin_login` in rate_limiter.yaml. */
    private const LIMIT = 5;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        // The limiter keeps its state in a cache pool that outlives a single
        // run, so a second run would start with an empty budget.
        $limiter = static::getContainer()->get('limiter.admin_login');
        self::assertInstanceOf(RateLimiterFactoryInterface::class, $limiter);
        $limiter->create('127.0.0.1')->reset();
    }

    public function testTheCorrectPasswordOpensTheBackend(): void
    {
        $this->request(self::PASSWORD);

        self::assertResponseIsSuccessful();
    }

    public function testAWrongPasswordIsRefused(): void
    {
        $this->request(self::WRONG_PASSWORD);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testTheCorrectPasswordIsRefusedOnceTheBudgetIsSpent(): void
    {
        for ($versuch = 0; $versuch < self::LIMIT; $versuch++) {
            $this->request(self::WRONG_PASSWORD);
            self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        }

        $this->request(self::PASSWORD);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testASuccessfulLoginSpendsNothing(): void
    {
        for ($besuch = 0; $besuch < self::LIMIT + 3; $besuch++) {
            $this->request(self::PASSWORD);
            self::assertResponseIsSuccessful();
        }
    }

    private function request(string $password): void
    {
        $this->client->request('GET', '/admin', [], [], [
            'PHP_AUTH_USER' => self::USER_NAME,
            'PHP_AUTH_PW' => $password,
        ]);
    }
}
