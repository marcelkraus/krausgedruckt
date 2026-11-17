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
                $query = 'mtm_campaign=' . ShortLinkController::PRINT_CAMPAIGNS[$prefix] . '&mtm_medium=print&mtm_kwd=' . $code;

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
            'Flyer' => ['/s/fl-2611', 'mtm_campaign=flyer&mtm_medium=print&mtm_kwd=2611'],
            'Signature-Modell' => ['/s/sm-jpjp', 'mtm_campaign=signature-model&mtm_medium=print&mtm_kwd=jurassic-print'],
            'Visitenkarte' => ['/s/bc-2611', 'mtm_campaign=business-card&mtm_medium=print&mtm_kwd=2611'],
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
            'Flyer mit unbekannter Auflage' => ['/s/fl-0000'],
            'Flyer ohne Auflage' => ['/s/fl-'],
            'Kennung mit Bindestrich' => ['/s/fl-2611-b'],
            'Modell unter dem Slug statt dem Kürzel' => ['/s/sm-dummy-13'],
            'Pfad hinter dem Slug' => ['/s/sm-d13/s/fl-2611'],
            'Präfix mit unbekanntem Modell' => ['/s/sm-gibt'],
            'unbekannt' => ['/s/gibt-es-nicht'],
            'unbekanntes Präfix' => ['/s/xx-2611'],
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

    public function testSlugsAreReachableAndUnique(): void
    {
        $slugs = array_map(static fn (array $case): string => $case[0], iterator_to_array(self::printedProvider(), false));

        foreach ($slugs as $slug) {
            self::assertMatchesRegularExpression('/^[a-z]{2}-[a-z0-9]+$/', $slug);
        }

        self::assertSame(array_values(array_unique($slugs)), $slugs);
    }
}
