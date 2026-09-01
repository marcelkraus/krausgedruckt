<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The listener is the only thing in the source that hardens a response, and it
 * fails silently: a renamed header or a lost autoconfiguration takes the
 * headers off without breaking a page. So the headers are asserted on every
 * public path rather than on the homepage alone.
 */
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

    /**
     * The one header that depends on how the request arrived, and the one the
     * listener may not set unconditionally. Over HTTPS it has to be there –
     * the backend is reached with a password, and it goes over the first
     * request there is. Over plain HTTP it must not be: a browser ignores it,
     * and sending it states something that is not true.
     */
    public function testTheTransportHeaderFollowsTheScheme(): void
    {
        $client = static::createClient();

        $client->request('GET', 'https://localhost/');
        self::assertResponseHeaderSame('Strict-Transport-Security', 'max-age=31536000');

        $client->request('GET', 'http://localhost/');
        self::assertResponseNotHasHeader('Strict-Transport-Security');
    }
}
