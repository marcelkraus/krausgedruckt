<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RouteSmokeTest extends WebTestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function renderingRouteProvider(): array
    {
        return [
            'adVintage' => ['/advintage'],
            'App' => ['/app'],
            'Blog' => ['/blog'],
            'Blog-Beitrag' => ['/blog/2026/jeder-block-einmal'],
            'Datenschutz' => ['/datenschutz'],
            'Häufig gestellte Fragen' => ['/haeufig-gestellte-fragen'],
            'Impressum' => ['/impressum'],
            'Kontakt' => ['/kontakt'],
            'Modell drucken' => ['/modell-drucken'],
            'robots.txt' => ['/robots.txt'],
            'sitemap.xml' => ['/sitemap.xml'],
            'Startseite' => ['/'],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function redirectingRouteProvider(): array
    {
        return [
            'Bewerten' => ['/bewerten'],
            'Referenzen' => ['/referenzen'],
            'Kontakt per E-Mail' => ['/kontakt-per-email'],
            'Kontakt per WhatsApp' => ['/kontakt-per-whats-app'],
            'Shop' => ['/shop'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('renderingRouteProvider')]
    public function testRouteRenders(string $path): void
    {
        $client = static::createClient();
        $client->request('GET', $path);

        self::assertResponseIsSuccessful();
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('redirectingRouteProvider')]
    public function testRouteRedirects(string $path): void
    {
        $client = static::createClient();
        $client->request('GET', $path);

        self::assertResponseRedirects();
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('renderingRouteProvider')]
    public function testPageHasExactlyOneMainHeading(string $path): void
    {
        if (in_array($path, ['/robots.txt', '/sitemap.xml'], true)) {
            self::markTestSkipped('Kein HTML-Dokument.');
        }

        $client = static::createClient();
        $crawler = $client->request('GET', $path);

        self::assertCount(1, $crawler->filter('h1'));
    }
}
