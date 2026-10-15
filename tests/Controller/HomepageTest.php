<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

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
        self::assertSame('Ich habe ein fertiges Modell gefunden', trim($links->eq(2)->text()));
    }

    public function testTheProcessKeepsTheWayOutForAVisitorWithoutAModel(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        self::assertSame(
            'Ich habe eine eigene Idee',
            trim($crawler->filter('main section.bg-neutral-950 a[href="/kontakt"]')->text())
        );
    }

    public function testTheNavigationCarriesTheAssistant(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        self::assertCount(2, $crawler->filter('header a[href="'.self::ASSISTANT.'"]'));
        self::assertSame('Modell drucken', trim($crawler->filter('header nav a')->eq(0)->text()));
    }
}
