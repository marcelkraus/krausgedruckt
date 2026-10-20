<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PhoneNumberTest extends WebTestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function pagesWithTheNumber(): iterable
    {
        yield 'imprint' => ['/impressum'];
        yield 'contact' => ['/kontakt'];
    }

    // The dialable number is derived from the displayed one; a space left in it breaks the call.
    #[DataProvider('pagesWithTheNumber')]
    public function testThePhoneNumberIsDialable(string $path): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', $path);

        self::assertMatchesRegularExpression('/^tel:\+\d+$/', (string) $crawler->filter('a[href^="tel:"]')->attr('href'));
    }

    public function testTheStructuredDataCarriesTheDialableNumber(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        $data = json_decode($crawler->filter('script[type="application/ld+json"]')->first()->text(), true, flags: JSON_THROW_ON_ERROR);

        self::assertMatchesRegularExpression('/^\+\d+$/', $data['telephone']);
    }
}
