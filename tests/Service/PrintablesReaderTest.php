<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\LookupFailedException;
use App\Service\LookupFailure;
use App\Service\PlatformFetcher;
use App\Service\PrintablesReader;
use App\Tests\Double\FixedHostResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * What Printables answers about a model, read from a recording of a real
 * answer of its interface under `tests/fixtures/platforms/`.
 */
final class PrintablesReaderTest extends TestCase
{
    private const string MODEL = 'https://www.printables.com/model/1042371-tpu-lid-instert-for-rugged-sd-card-case';

    public function testReadsTitleSummaryImageAndLicense(): void
    {
        $preview = $this->reader()->read(self::MODEL);

        self::assertSame('TPU lid instert for rugged SD card case', $preview->title);
        self::assertSame('TPU insert to instead of foam in the lid.', $preview->description);
        self::assertSame('Printables', $preview->platform);
        self::assertStringStartsWith('https://media.printables.com/media/prints/1042371/', $preview->imageUrl);
        self::assertStringContainsString('Noncommercial', (string) $preview->license);
    }

    /**
     * **The address is the platform's rendition, not the original.** A
     * cover runs into the megabytes, which the relay refuses and the card
     * shows as a broken picture; the rendition of the same file is a few
     * hundred kilobytes. The format segment follows the file's own
     * extension – it is `jpg` here and `png` on a model stored as one.
     */
    public function testAsksForTheRenditionRatherThanTheOriginalCover(): void
    {
        $preview = $this->reader()->read(self::MODEL);

        self::assertStringContainsString('/thumbs/inside/640x480/jpg/', (string) $preview->imageUrl);
        self::assertStringEndsWith('.jpg', (string) $preview->imageUrl);
    }

    /**
     * The interface wants the number, and it is the one part of the address
     * that cannot change: a model keeps its id when its title is edited and
     * the slug moves with the title.
     */
    public function testTakesTheIdFromTheAddressAndIgnoresTheSlug(): void
    {
        $seen = '';

        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$seen): MockResponse {
            $seen = (string) $options['body'];

            self::assertSame('POST', $method);
            self::assertSame('https://api.printables.com/graphql/', $url);

            return new MockResponse($this->recording(), ['response_headers' => ['content-type' => 'application/json']]);
        });

        $this->readerWith($client)->read('https://www.printables.com/model/1042371-anything-at-all');

        self::assertStringContainsString('print(id: \\"1042371\\")', $seen);
        self::assertStringContainsString('{ name summary image', $seen);
        self::assertStringContainsString('license { name }', $seen);
    }

    /**
     * The interface hands its text out encoded – the reader on the other
     * path gets it decoded from the crawler, and both must arrive in the
     * same shape.
     */
    public function testDecodesEntitiesTheInterfaceLeavesEncoded(): void
    {
        $body = '{"data":{"print":{"name":"Bolt &amp; Nut","summary":"Fits an M8&nbsp;bolt.","license":{"name":"CC &amp; friends"}}}}';
        $client = new MockHttpClient([new MockResponse($body, ['response_headers' => ['content-type' => 'application/json']])]);

        $preview = $this->readerWith($client)->read(self::MODEL);

        self::assertSame('Bolt & Nut', $preview->title);
        self::assertStringStartsWith('Fits an M8', $preview->description);
        self::assertSame('CC & friends', $preview->license);
    }

    public function testSupportsEveryPrintablesAddress(): void
    {
        $reader = $this->reader();

        self::assertTrue($reader->supports(self::MODEL));
        self::assertTrue($reader->supports('https://www.printables.com/@someone'));
        self::assertFalse($reader->supports('https://makerworld.com/en/models/1'));
    }

    /**
     * A profile or a collection is served by this reader – it is a
     * Printables address – but it is not a model, and saying so is a
     * different sentence than saying we do not read the platform.
     */
    public function testNamesAnAddressThatIsNoModelPageAsSuch(): void
    {
        try {
            $this->reader()->read('https://www.printables.com/@someone');
            self::fail('A page that is no model was accepted.');
        } catch (LookupFailedException $exception) {
            self::assertSame(LookupFailure::NotAModelPage, $exception->failure);
        }
    }

    /**
     * An answer that is not JSON must leave as a lookup failure like any
     * other: the assistant catches that one and carries on without a
     * preview, while anything else reaches the visitor as a broken page.
     */
    public function testTreatsAnAnswerThatIsNotJsonAsAFailure(): void
    {
        $client = new MockHttpClient([new MockResponse('', ['response_headers' => ['content-type' => 'application/json']])]);

        try {
            $this->readerWith($client)->read(self::MODEL);
            self::fail('An empty body was accepted.');
        } catch (LookupFailedException $exception) {
            self::assertSame(LookupFailure::NoMetadata, $exception->failure);
        }
    }

    public function testRefusesAnAnswerWithoutAModel(): void
    {
        $client = new MockHttpClient([new MockResponse('{"data":{"print":null}}', ['response_headers' => ['content-type' => 'application/json']])]);

        try {
            $this->readerWith($client)->read(self::MODEL);
            self::fail('An empty answer was accepted.');
        } catch (LookupFailedException $exception) {
            self::assertSame(LookupFailure::NoMetadata, $exception->failure);
        }
    }

    public function testRefusesAnAnswerThatIsOnlyErrors(): void
    {
        $body = '{"errors":[{"message":"Cannot query field \'name\' on type \'Print\'."}]}';
        $client = new MockHttpClient([new MockResponse($body, ['response_headers' => ['content-type' => 'application/json']])]);

        try {
            $this->readerWith($client)->read(self::MODEL);
            self::fail('An answer of only errors was accepted.');
        } catch (LookupFailedException $exception) {
            self::assertSame(LookupFailure::NoMetadata, $exception->failure);
        }
    }

    private function recording(): string
    {
        return (string) file_get_contents(__DIR__.'/../fixtures/platforms/printables-print.json');
    }

    private function reader(): PrintablesReader
    {
        return $this->readerWith(new MockHttpClient([
            new MockResponse($this->recording(), ['response_headers' => ['content-type' => 'application/json']]),
        ]));
    }

    private function readerWith(MockHttpClient $client): PrintablesReader
    {
        return new PrintablesReader(new PlatformFetcher($client, new FixedHostResolver()));
    }
}
