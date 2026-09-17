<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The questions come out of one content in kongtent, split at its headings.
 * What cannot be split is dropped without a word, and the structured data
 * carries the text of every answer that stands.
 */
final class FaqTest extends WebTestCase
{
    public function testEveryHeadingWithAnAnswerIsAQuestionInItsOrder(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/haeufig-gestellte-fragen');

        self::assertResponseIsSuccessful();
        self::assertSame(['Was kostet PLA & „PETG“?', 'Welche Farben?'], $crawler->filter('dt')->each(static fn ($dt): string => $dt->text()));
        self::assertStringNotContainsString('Vor der ersten Überschrift', $crawler->filter('main')->text());
        self::assertCount(2, $crawler->filter('dd')->eq(0)->filter('p'));
        self::assertCount(2, $crawler->filter('dd')->eq(1)->filter('li'));
        self::assertSame('Häufig gestellte Fragen · krausgedruckt von Marcel Kraus', $crawler->filter('title')->text());
    }

    public function testTheStructuredDataCarriesTheTextOfEveryAnswer(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/haeufig-gestellte-fragen');

        $data = json_decode($crawler->filter('script[type="application/ld+json"]')->last()->text(), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame('FAQPage', $data['@type']);
        self::assertCount(2, $data['mainEntity']);
        self::assertSame('Was kostet PLA & „PETG“?', $data['mainEntity'][0]['name']);
        self::assertSame('Das hängt vom Modell ab. Frag uns einfach.', $data['mainEntity'][0]['acceptedAnswer']['text']);
    }

    public function testAContentNotListedIsNoPost(): void
    {
        static::createClient()->request('GET', '/blog/2026/haeufig-gestellte-fragen');

        self::assertResponseStatusCodeSame(404);
    }
}
