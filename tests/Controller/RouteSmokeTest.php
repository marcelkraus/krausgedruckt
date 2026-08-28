<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Repository\ReferenceRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Calls every frontend route once and expects it to answer. This is the
 * cheapest insurance against a template that breaks on a page nobody clicks
 * during development – a missing variable or a renamed partial surfaces here
 * instead of in front of a visitor.
 */
final class RouteSmokeTest extends WebTestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function renderingRouteProvider(): array
    {
        return [
            'Startseite' => ['/'],
            'Referenzen' => ['/referenzen'],
            'FAQ' => ['/haeufig-gestellte-fragen'],
            'Kontakt' => ['/kontakt'],
            'Impressum' => ['/impressum'],
            'Datenschutz' => ['/datenschutz'],
            'App' => ['/app'],
            'adVintage' => ['/advintage'],
            'robots.txt' => ['/robots.txt'],
            'sitemap.xml' => ['/sitemap.xml'],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function redirectingRouteProvider(): array
    {
        return [
            'Bewerten' => ['/bewerten'],
            'Kontakt per E-Mail' => ['/kontakt-per-email'],
            'Kontakt per WhatsApp' => ['/kontakt-per-whats-app'],
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

    public function testReferenceDetailRenders(): void
    {
        $client = static::createClient();
        $references = static::getContainer()->get(ReferenceRepository::class)->findAllOrdered();

        if ($references === []) {
            self::markTestSkipped('Ohne sichtbare Referenz gibt es keine Detailseite zu prüfen.');
        }

        $reference = $references[0];
        $client->request('GET', sprintf('/referenzen/%s/%s', $reference->getYear(), $reference->getSlug()));

        self::assertResponseIsSuccessful();
    }

    /**
     * Every page carries exactly one visible top-level heading. Before the
     * redesign each of them inherited a hidden one from the navigation and
     * started at h2, which left no page with a heading of its own.
     */
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
