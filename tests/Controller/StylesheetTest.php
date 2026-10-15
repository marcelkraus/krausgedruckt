<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class StylesheetTest extends WebTestCase
{
    public function testTheStylesheetUrlCarriesTheVersionOfTheFile(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        $mtime = filemtime(\dirname(__DIR__, 2).'/public/css/output.css');

        self::assertSame('/css/output.css?v='.$mtime, $crawler->filter('link[rel="stylesheet"]')->attr('href'));
    }
}
