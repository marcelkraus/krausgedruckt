<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SecurityHeadersListenerTest extends WebTestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function publicPaths(): iterable
    {
        yield 'homepage' => ['/'];
        yield 'contact' => ['/kontakt'];
        yield 'questions' => ['/haeufig-gestellte-fragen'];
        yield 'imprint' => ['/impressum'];
        yield 'privacy' => ['/datenschutz'];
        yield 'robots' => ['/robots.txt'];
        yield 'sitemap' => ['/sitemap.xml'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('publicPaths')]
    public function testEveryPublicResponseCarriesTheHardeningHeaders(string $path): void
    {
        $client = static::createClient();
        $client->request('GET', $path);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        self::assertResponseHeaderSame('Referrer-Policy', 'strict-origin-when-cross-origin');
        self::assertResponseHeaderSame('X-Frame-Options', 'DENY');
    }

    public function testTheTransportHeaderFollowsTheScheme(): void
    {
        $client = static::createClient();

        $client->request('GET', 'https://localhost/');
        self::assertResponseHeaderSame('Strict-Transport-Security', 'max-age=31536000');

        $client->request('GET', 'http://localhost/');
        self::assertResponseNotHasHeader('Strict-Transport-Security');
    }
}
