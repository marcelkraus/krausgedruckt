<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Service\PlatformFetcher;
use App\Tests\Double\FixedHostResolver;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\UriSigner;

/**
 * The image relay: what it hands out, and what it refuses.
 *
 * The fetcher is replaced with one that answers from memory, so the route
 * is exercised without either platform being asked.
 */
final class ModelImageTest extends WebTestCase
{
    private const string COVER = 'https://media.printables.com/media/prints/1/cover.png';

    private const string PIXEL = "\x89PNG\r\n\x1a\n";

    public function testRelaysASignedPlatformImage(): void
    {
        $client = static::createClient();
        $this->replaceFetcher([new MockResponse(self::PIXEL, ['response_headers' => ['content-type' => 'image/png']])]);

        $client->request('GET', $this->signed($client, self::COVER));

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/png');
        self::assertSame(self::PIXEL, $client->getResponse()->getContent());
    }

    /**
     * The relay carries bytes this site did not write, which makes it the
     * one answer where sniffing would matter. The header comes from the
     * listener, and the listener is tested elsewhere – here it is checked
     * on the one path that needs it.
     */
    public function testTheRelayedImageCarriesTheSniffingGuard(): void
    {
        $client = static::createClient();
        $this->replaceFetcher([new MockResponse(self::PIXEL, ['response_headers' => ['content-type' => 'image/png']])]);

        $client->request('GET', $this->signed($client, self::COVER));

        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
    }

    /**
     * Without the signature the route would be a fetch service for the
     * platforms, usable by anyone who knows the address.
     */
    public function testRefusesAnAddressWithoutASignature(): void
    {
        $client = static::createClient();
        $this->replaceFetcher([new MockResponse(self::PIXEL)]);

        $client->request('GET', '/modell-bild?url='.urlencode(self::COVER));

        self::assertResponseStatusCodeSame(404);
    }

    public function testRefusesAnAddressSwappedUnderAValidSignature(): void
    {
        $client = static::createClient();
        $this->replaceFetcher([new MockResponse('secrets')]);

        $signed = $this->signed($client, self::COVER);
        $tampered = str_replace(urlencode(self::COVER), urlencode('https://media.printables.com/other.png'), $signed);

        $client->request('GET', $tampered);

        self::assertResponseStatusCodeSame(404);
    }

    public function testRefusesAnAddressOutsideThePlatforms(): void
    {
        $client = static::createClient();
        $this->replaceFetcher([new MockResponse('secrets')]);

        $client->request('GET', $this->signed($client, 'http://169.254.169.254/latest/meta-data/'));

        self::assertResponseStatusCodeSame(404);
    }

    public function testDoesNotAnswerAPost(): void
    {
        static::createClient()->request('POST', '/modell-bild');

        self::assertResponseStatusCodeSame(405);
    }

    private function signed(KernelBrowser $client, string $imageUrl): string
    {
        $signer = static::getContainer()->get(UriSigner::class);

        return $signer->sign(
            $client->getContainer()->get('router')->generate('app_model_image', ['url' => $imageUrl])
        );
    }

    /**
     * @param list<MockResponse> $responses
     */
    private function replaceFetcher(array $responses): void
    {
        static::getContainer()->set(PlatformFetcher::class, new PlatformFetcher(
            new MockHttpClient($responses),
            new FixedHostResolver(),
        ));
    }
}
