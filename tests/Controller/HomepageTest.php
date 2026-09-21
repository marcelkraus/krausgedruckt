<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Where the homepage and the contact page lead.
 *
 * The smoke test proves the pages render; it cannot see a target. The one
 * thing the connecting step did was hang existing buttons onto a new route,
 * and a wrong route name renders just as happily as the right one – so the
 * targets are pinned here rather than left to the eye.
 */
final class HomepageTest extends WebTestCase
{
    private const string ASSISTANT = '/modell-drucken';

    public function testTheHomepageLeadsToTheAssistantThreeTimesAndNowhereElse(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        $links = $crawler->filter('main a[href="'.self::ASSISTANT.'"]');

        self::assertCount(3, $links, 'the hero, the band of its own and the first step of the process');
        self::assertSame('Modell drucken', trim($links->eq(0)->text()));
        self::assertSame('Modell drucken', trim($links->eq(1)->text()));
        self::assertSame('Mit einem fertigen Modell starten', trim($links->eq(2)->text()));
    }

    /**
     * The filled button of the process was allowed to move only because the
     * visitor without a model keeps a way out beside it.
     */
    public function testTheProcessKeepsTheWayOutForAVisitorWithoutAModel(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        self::assertSame(
            'Ich habe nur eine Idee',
            trim($crawler->filter('main section.bg-neutral-950 a[href="/kontakt"]')->text())
        );
    }

    public function testTheContactPageLeadsToTheAssistant(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/kontakt');

        self::assertCount(1, $crawler->filter('main a[href="'.self::ASSISTANT.'"]'));
    }

    /**
     * Once in the desktop navigation and once in the mobile menu – both are
     * rendered on every page, and the assistant opens the bar: the homepage
     * has no entry of its own any more, it is reached through the logo.
     */
    public function testTheNavigationCarriesTheAssistant(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        self::assertCount(2, $crawler->filter('header a[href="'.self::ASSISTANT.'"]'));
        self::assertSame('Modell drucken', trim($crawler->filter('header nav a')->eq(0)->text()));
    }
}
