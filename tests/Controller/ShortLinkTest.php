<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\SignatureModel;
use App\Service\ShortLinkResolver;
use App\Service\SignatureModelCatalog;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The printed short links: where they lead, what they are counted as, and
 * that nothing printed can answer under two meanings or not at all. The
 * models change with every event, so the tests take theirs from the catalog.
 */
final class ShortLinkTest extends WebTestCase
{
    public function testSignatureModelLeadsHomeUnderTheEventCampaign(): void
    {
        $client = static::createClient();
        $catalog = static::getContainer()->get(SignatureModelCatalog::class);
        $model = $catalog->all()[0];

        $client->request('GET', '/s/' . ShortLinkResolver::SIGNATURE_PREFIX . $model->slug);

        self::assertResponseRedirects('/?mtm_campaign=' . $catalog->campaign()['slug'] . '&mtm_kwd=' . $model->slug, 302);
    }

    /**
     * The codes printed for the Gangelt Games Festival 2026 chain the
     * addresses of the cards before them; the last one counts.
     */
    public function testChainedAddressLeadsToTheLastModel(): void
    {
        $client = static::createClient();
        $catalog = static::getContainer()->get(SignatureModelCatalog::class);
        [$first, $last] = [$catalog->all()[0], $catalog->all()[\count($catalog->all()) - 1]];

        $client->request('GET', '/s/signature-' . $first->slug . 'https:/krausgedruckt.de/s/signature-' . $last->slug);

        self::assertResponseRedirects('/?mtm_campaign=' . $catalog->campaign()['slug'] . '&mtm_kwd=' . $last->slug, 302);
    }

    public function testSignatureModelWithoutPrefixIsNotFound(): void
    {
        $client = static::createClient();
        $model = static::getContainer()->get(SignatureModelCatalog::class)->all()[0];

        $client->request('GET', '/s/' . $model->slug);

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function unknownSlugProvider(): array
    {
        return [
            'Präfix ohne Modell' => ['/s/signature-gibt-es-nicht'],
            'unbekannt' => ['/s/gibt-es-nicht'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unknownSlugProvider')]
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

    /**
     * Two models under one slug would share one count, and a slug the route
     * refuses would put a dead code on paper.
     */
    public function testSlugsAreReachableAndUnique(): void
    {
        static::bootKernel();

        $modelSlugs = array_map(static fn (SignatureModel $model): string => $model->slug, static::getContainer()->get(SignatureModelCatalog::class)->all());

        foreach ($modelSlugs as $slug) {
            self::assertMatchesRegularExpression('/^' . ShortLinkResolver::SLUG_PATTERN . '$/', ShortLinkResolver::SIGNATURE_PREFIX . $slug);
        }

        self::assertSame(array_values(array_unique($modelSlugs)), $modelSlugs);
    }
}
