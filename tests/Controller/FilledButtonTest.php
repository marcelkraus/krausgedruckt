<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Service\PlatformFetcher;
use App\Tests\Double\FixedHostResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Every filled button below the header ends in an icon. `_button` sets it
 * itself, a `<button>` pulling `_button_class` writes it out by hand – and
 * that is where a new one would go without.
 */
final class FilledButtonTest extends WebTestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function pages(): iterable
    {
        yield 'homepage' => ['/'];
        yield 'contact' => ['/kontakt'];
        yield 'faq' => ['/haeufig-gestellte-fragen'];
        yield 'advintage' => ['/advintage'];
        yield 'blog' => ['/blog'];
    }

    #[DataProvider('pages')]
    public function testEveryFilledButtonCarriesAnIcon(string $path): void
    {
        $crawler = static::createClient()->request('GET', $path);

        self::assertResponseIsSuccessful();
        $this->assertEveryButtonCarriesAnIcon($crawler);
    }

    public function testEveryFilledButtonOfTheOpenAssistantCarriesAnIcon(): void
    {
        $client = static::createClient();
        static::getContainer()->set(PlatformFetcher::class, new PlatformFetcher(
            new MockHttpClient([new MockResponse('', ['http_code' => 503])]),
            new FixedHostResolver(),
        ));

        $crawler = $client->request('GET', '/modell-drucken?url='.urlencode('https://www.printables.com/model/1042371-tpu-lid-instert'));

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('#schritt-3 input[name="email"]'));
        $this->assertEveryButtonCarriesAnIcon($crawler);
    }

    private function assertEveryButtonCarriesAnIcon(Crawler $crawler): void
    {
        $buttons = $crawler->filter('main a.bg-accent, main button.bg-accent');

        self::assertGreaterThan(0, $buttons->count());
        $buttons->each(static function (Crawler $button): void {
            self::assertCount(1, $button->filter('svg'), trim($button->text()));
        });
    }
}
