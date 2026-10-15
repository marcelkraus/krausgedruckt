<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class BlogTest extends WebTestCase
{
    public function testTheFeedIsValidXmlAndCarriesEveryBlock(): void
    {
        $client = static::createClient();
        $client->request('GET', '/blog/feed.xml');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/atom+xml; charset=UTF-8');

        $document = new \DOMDocument();
        self::assertTrue($document->loadXML((string) $client->getResponse()->getContent()), 'Der Feed ist kein gültiges XML.');

        $contents = $document->getElementsByTagName('content');
        self::assertSame(1, $contents->length);

        $entry = (string) $contents->item(0)?->textContent;
        self::assertSame(5, substr_count($entry, '<img'), 'Titelbild, Bild oder Galerie fehlen im Feed.');
        self::assertStringContainsString('<blockquote>', $entry);
        // Escaped twice: once for the XML, once for the HTML inside it.
        self::assertStringContainsString('<dt>Farbe &lt;b&gt;</dt>', $entry);
        self::assertStringContainsString('<a href="https://www.youtube.com/watch?v=dQw4w9WgXcQ">', $entry);
        self::assertStringContainsString('<a href="http://localhost/kontakt">', $entry);
        self::assertStringContainsString('Foto: <a href="http://localhost/fotograf">', $entry);
        self::assertStringContainsString('<a href="http://localhost/werkstatt">', (string) $document->getElementsByTagName('summary')->item(0)?->textContent);
    }

    public function testAPostAnswersOnlyUnderTheYearOfItsDate(): void
    {
        $client = static::createClient();

        $client->request('GET', '/blog/2026/jeder-block-einmal');
        self::assertResponseIsSuccessful();

        $client->request('GET', '/blog/2025/jeder-block-einmal');
        self::assertResponseStatusCodeSame(404, 'Der Beitrag antwortet unter einem fremden Jahr.');
    }

    public function testAYearOnItsOwnIsNotAPage(): void
    {
        static::createClient()->request('GET', '/blog/2026');

        self::assertResponseStatusCodeSame(404);
    }

    public function testTheOldAddressesOfTheReferencesMovePermanently(): void
    {
        $client = static::createClient();

        $client->request('GET', '/referenzen');
        self::assertResponseRedirects('/blog', 301);

        $client->request('GET', '/referenzen/2026/jeder-block-einmal');
        self::assertResponseRedirects('/blog/2026/jeder-block-einmal', 301);
    }

    public function testEveryPictureCanHoldItsPlace(): void
    {
        $client = static::createClient();
        $seen = 0;

        foreach (['/', '/blog', '/blog/2026/jeder-block-einmal'] as $path) {
            foreach ($client->request('GET', $path)->filter('img[src*="/media/"]') as $picture) {
                ++$seen;
                self::assertTrue($picture->hasAttribute('alt'));
                self::assertNotSame('', $picture->getAttribute('width'));
                self::assertNotSame('', $picture->getAttribute('height'));
            }
        }

        self::assertSame(7, $seen);
    }

    public function testTheBlocksAreThisSitesOwn(): void
    {
        $page = static::createClient()->request('GET', '/blog/2026/jeder-block-einmal');

        self::assertCount(1, $page->filter('.prose figure.not-prose > img.rounded-2xl'));
        self::assertCount(1, $page->filter('.prose figure.not-prose.border-l-2 blockquote'));
        self::assertCount(3, $page->filter('.prose ul.not-prose li figure img.aspect-square'));
        self::assertCount(2, $page->filter('.prose dl.not-prose dt'));
        self::assertStringContainsString('Farbe <b>', $page->filter('.prose dl dt')->first()->text());
        self::assertCount(1, $page->filter('.prose dl.not-prose dd[data-generated] a[href="/kontakt"]'));
        self::assertCount(1, $page->filter('.prose h2'));
        self::assertCount(1, $page->filter('.prose a[href="https://www.youtube.com/watch?v=dQw4w9WgXcQ"]'));
        self::assertCount(1, $page->filter('.prose figure > figcaption[data-generated] a[href="https://www.printables.com/model/391349"]'));
        self::assertCount(1, $page->filter('.prose li figcaption[data-generated] a[href="/fotograf"]'));
    }

    public function testTheTeaserCarriesItsLinkOnlyInThePost(): void
    {
        $client = static::createClient();

        $post = $client->request('GET', '/blog/2026/jeder-block-einmal');
        self::assertCount(1, $post->filter('p[data-generated] a[href="/werkstatt"]'));

        foreach (['/', '/blog'] as $path) {
            self::assertCount(0, $client->request('GET', $path)->filter('a[href="/werkstatt"]'), $path);
        }
    }

    public function testALoneExternalLinkCarriesTheArrow(): void
    {
        $page = static::createClient()->request('GET', '/blog/2026/jeder-block-einmal');

        $external = $page->filter('.prose p.not-prose a[href="https://www.google.com/maps"]');
        self::assertCount(1, $external);
        self::assertSame('_blank', $external->attr('target'));
        self::assertCount(1, $external->filter('svg'));

        $internal = $page->filter('.prose > p a[href="/kontakt"]');
        self::assertCount(2, $internal);
        self::assertCount(0, $internal->filter('svg'));
        self::assertCount(0, $internal->filter('[target]'));
    }

    public function testTheSitemapCarriesThePosts(): void
    {
        $client = static::createClient();
        $client->request('GET', '/sitemap.xml');

        $sitemap = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('<loc>http://localhost/blog</loc>', $sitemap);
        self::assertStringContainsString('<loc>http://localhost/blog/2026/jeder-block-einmal</loc>', $sitemap);
    }
}
