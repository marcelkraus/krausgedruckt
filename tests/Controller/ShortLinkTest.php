<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\ShortLinkController;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ShortLinkTest extends WebTestCase
{
    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function printedProvider(): iterable
    {
        foreach (ShortLinkController::PRINT_EDITIONS as $prefix => $codes) {
            foreach ($codes as $code) {
                $query = 'mtm_campaign=' . ShortLinkController::PRINT_CAMPAIGNS[$prefix] . '&mtm_medium=print';
                $query .= $prefix === 'ad'
                    ? '&mtm_source=' . ShortLinkController::PRINT_SOURCES[substr($code, 0, 2)] . '&mtm_kwd=' . substr($code, 2)
                    : '&mtm_kwd=' . $code;

                yield $prefix . '-' . $code => [$prefix . '-' . $code, $query];
            }
        }

        foreach (ShortLinkController::SIGNATURE_MODELS as $code => $slug) {
            yield 'sm-' . $code => ['sm-' . $code, 'mtm_campaign=signature-model&mtm_medium=print&mtm_kwd=' . $slug];
        }
    }

    #[DataProvider('printedProvider')]
    public function testPrintedLeadsHomeCountedAsPrint(string $slug, string $query): void
    {
        $client = static::createClient();

        $client->request('GET', '/s/' . $slug);

        self::assertResponseRedirects('/?' . $query, 302);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function countedAsProvider(): array
    {
        return [
            'Anzeige' => ['/s/ad-ea2610', 'mtm_campaign=advertisement&mtm_medium=print&mtm_source=erftstadt-aktuell&mtm_kwd=2610'],
            'Flyer' => ['/s/fl-2610', 'mtm_campaign=flyer&mtm_medium=print&mtm_kwd=2610'],
            'Signature-Modell' => ['/s/sm-jpjp', 'mtm_campaign=signature-model&mtm_medium=print&mtm_kwd=jurassic-print'],
            'Visitenkarte' => ['/s/bc-2610', 'mtm_campaign=business-card&mtm_medium=print&mtm_kwd=2610'],
        ];
    }

    #[DataProvider('countedAsProvider')]
    public function testPrintedIsCountedUnderItsReadableCampaign(string $path, string $query): void
    {
        $client = static::createClient();

        $client->request('GET', $path);

        self::assertResponseRedirects('/?' . $query, 302);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function unknownSlugProvider(): array
    {
        return [
            'Anzeige in unbekanntem Medium' => ['/s/ad-xx2610'],
            'Anzeige mit unbekannter Auflage' => ['/s/ad-ea0000'],
            'Anzeige ohne Auflage' => ['/s/ad-ea'],
            'Flyer mit unbekannter Auflage' => ['/s/fl-0000'],
            'Flyer ohne Auflage' => ['/s/fl-'],
            'Kennung mit Bindestrich' => ['/s/fl-2610-b'],
            'Modell unter dem Slug statt dem Kürzel' => ['/s/sm-dummy-13'],
            'Pfad hinter dem Slug' => ['/s/sm-d13/s/fl-2610'],
            'Präfix mit unbekanntem Modell' => ['/s/sm-gibt'],
            'unbekannt' => ['/s/gibt-es-nicht'],
            'unbekanntes Präfix' => ['/s/xx-2610'],
            'Visitenkarte mit unbekannter Auflage' => ['/s/bc-0000'],
            'Visitenkarte ohne Auflage' => ['/s/bc-'],
        ];
    }

    #[DataProvider('unknownSlugProvider')]
    public function testUnknownSlugIsNotFound(string $path): void
    {
        $client = static::createClient();
        $client->request('GET', $path);

        self::assertResponseStatusCodeSame(404);
    }

    public function testShopLeadsToEtsy(): void
    {
        $client = static::createClient();
        $client->request('GET', '/shop');

        self::assertResponseRedirects(static::getContainer()->getParameter('app.etsy_url'), 302);
    }

    public function testSlugsAreReachableAndUnique(): void
    {
        $slugs = array_map(static fn (array $case): string => $case[0], iterator_to_array(self::printedProvider(), false));

        foreach ($slugs as $slug) {
            self::assertMatchesRegularExpression('/^[a-z]{2}-[a-z0-9]+$/', $slug);
        }

        foreach (ShortLinkController::PRINT_EDITIONS['ad'] as $code) {
            self::assertArrayHasKey(substr($code, 0, 2), ShortLinkController::PRINT_SOURCES);
        }

        self::assertSame(array_values(array_unique($slugs)), $slugs);
    }
}
