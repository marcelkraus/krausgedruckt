<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\LookupFailedException;
use App\Service\LookupFailure;
use App\Service\PlatformFetcher;
use App\Tests\Double\FixedHostResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Pins the guard around the fetcher: which addresses are refused, and what
 * is actually sent for one that is not.
 *
 * A plain TestCase without a kernel – nothing here needs the container, and
 * the host resolver is a stub, so no test ever asks a name server or
 * reaches the network.
 */
final class PlatformFetcherTest extends TestCase
{
    public static function refusedAddresses(): iterable
    {
        yield 'a host we do not read' => ['https://cults3d.com/en/3d-model/tool/clip'];
        yield 'a platform we do not read either' => ['https://makerworld.com/en/models/1'];
        yield 'a platform whose pages carry no model data' => ['https://www.thingiverse.com/thing:4711'];
        yield 'a host that only looks like one' => ['https://evil-printables.com/model/1'];
        yield 'a platform name in the path' => ['https://example.com/printables.com/model/1'];
        yield 'a leading dot' => ['https://.printables.com/model/1'];
        yield 'plain http' => ['http://www.printables.com/model/1'];
        yield 'a port of its own' => ['https://www.printables.com:22/model/1'];
        yield 'credentials in the authority' => ['https://user:secret@www.printables.com/model/1'];
        yield 'a file address' => ['file:///etc/passwd'];
        yield 'no scheme at all' => ['www.printables.com/model/1'];
    }

    #[DataProvider('refusedAddresses')]
    public function testRefusesAnAddressOutsideThePlatforms(string $url): void
    {
        $this->expectException(LookupFailedException::class);

        $this->fetcher([new MockResponse('should never be requested')])->fetchPage($url);
    }

    public static function reservedAddresses(): iterable
    {
        yield 'loopback' => ['127.0.0.1'];
        yield 'private class A' => ['10.0.0.1'];
        yield 'private class C' => ['192.168.1.1'];
        yield 'link local' => ['169.254.169.254'];
        yield 'IPv6 loopback' => ['::1'];
        yield 'IPv6 unique local' => ['fd00::1'];
    }

    #[DataProvider('reservedAddresses')]
    public function testRefusesAPlatformHostThatResolvesIntoAReservedRange(string $address): void
    {
        try {
            $this->fetcher([new MockResponse('should never be requested')], [$address])
                ->fetchPage('https://www.printables.com/model/1');
            self::fail('A reserved address was fetched.');
        } catch (LookupFailedException $exception) {
            self::assertSame(LookupFailure::UnsupportedAddress, $exception->failure);
        }
    }

    public function testRefusesWhenOneOfSeveralAddressesIsReserved(): void
    {
        $this->expectException(LookupFailedException::class);

        $this->fetcher([new MockResponse('nope')], [FixedHostResolver::PUBLIC_ADDRESS, '127.0.0.1'])
            ->fetchPage('https://www.printables.com/model/1');
    }

    /**
     * The head of the fetcher claims the resolved address is pinned into the
     * request. Without this test the claim holds only until someone deletes
     * the option: every other test passes with the client resolving a second
     * time, which is the window the whole guard exists to close.
     */
    public function testSendsTheCheckedAddressWithThePinAndTheUserAgent(): void
    {
        $seen = [];

        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$seen): MockResponse {
            $seen = ['method' => $method, 'url' => $url, 'options' => $options];

            return new MockResponse('<html></html>', ['response_headers' => ['content-type' => 'text/html']]);
        });

        $this->fetcherWith($client)->fetchPage('https://WWW.Printables.com/model/1?ref=x');

        self::assertSame('GET', $seen['method']);
        self::assertSame('https://www.printables.com/model/1?ref=x', $seen['url']);
        self::assertSame(['www.printables.com' => FixedHostResolver::PUBLIC_ADDRESS], $seen['options']['resolve']);
        self::assertSame(0, $seen['options']['max_redirects']);
        self::assertContains('User-Agent: krausgedruckt/1.0 (+https://www.krausgedruckt.de)', $seen['options']['headers']);
        self::assertLessThanOrEqual(5.0, $seen['options']['max_duration']);
    }

    public function testFollowsARedirectThatStaysOnAPlatform(): void
    {
        $fetcher = $this->fetcher([
            new MockResponse('', ['http_code' => 301, 'response_headers' => ['location' => 'https://www.printables.com/model/final']]),
            new MockResponse('<html><body>arrived</body></html>', ['response_headers' => ['content-type' => 'text/html; charset=utf-8']]),
        ]);

        self::assertStringContainsString('arrived', $fetcher->fetchPage('https://www.printables.com/model/1'));
    }

    public function testRefusesARedirectThatLeavesThePlatforms(): void
    {
        $fetcher = $this->fetcher([
            new MockResponse('', ['http_code' => 302, 'response_headers' => ['location' => 'http://169.254.169.254/latest/meta-data/']]),
            new MockResponse('secrets'),
        ]);

        try {
            $fetcher->fetchPage('https://www.printables.com/model/1');
            self::fail('A redirect off the platforms was followed.');
        } catch (LookupFailedException $exception) {
            self::assertSame(LookupFailure::UnsupportedAddress, $exception->failure);
        }
    }

    public function testRefusesARedirectOffThePlatformsOnTheImagePath(): void
    {
        $fetcher = $this->fetcher([
            new MockResponse('', ['http_code' => 302, 'response_headers' => ['location' => 'https://cdn.example.com/cover.png']]),
            new MockResponse('not ours'),
        ]);

        $this->expectException(LookupFailedException::class);

        $fetcher->fetchImage('https://media.printables.com/cover.png');
    }

    public function testGivesUpAfterThreeRedirects(): void
    {
        $hop = static fn (int $n) => new MockResponse('', [
            'http_code' => 302,
            'response_headers' => ['location' => 'https://www.printables.com/model/'.$n],
        ]);

        $this->expectException(LookupFailedException::class);

        $this->fetcher([$hop(1), $hop(2), $hop(3), $hop(4), $hop(5)])->fetchPage('https://www.printables.com/model/0');
    }

    public function testRefusesAnAnswerThatIsNotHtml(): void
    {
        $fetcher = $this->fetcher([
            new MockResponse('{"model":1}', ['response_headers' => ['content-type' => 'application/json']]),
        ]);

        try {
            $fetcher->fetchPage('https://www.printables.com/model/1');
            self::fail('A non-HTML answer was accepted.');
        } catch (LookupFailedException $exception) {
            self::assertSame(LookupFailure::Unreachable, $exception->failure);
        }
    }

    public function testStopsAtTheSizeLimitEvenWhenContentLengthLies(): void
    {
        $fetcher = $this->fetcher([
            new MockResponse(str_repeat('a', PlatformFetcher::MAX_PAGE_BYTES + 1), [
                'response_headers' => ['content-type' => 'text/html', 'content-length' => '10'],
            ]),
        ]);

        $this->expectException(LookupFailedException::class);

        $fetcher->fetchPage('https://www.printables.com/model/1');
    }

    public function testStopsAtTheSizeLimitOnTheImagePath(): void
    {
        $fetcher = $this->fetcher([
            new MockResponse(str_repeat('a', PlatformFetcher::MAX_IMAGE_BYTES + 1), [
                'response_headers' => ['content-type' => 'image/png'],
            ]),
        ]);

        $this->expectException(LookupFailedException::class);

        $fetcher->fetchImage('https://media.printables.com/cover.png');
    }

    public function testRefusesAnErrorAnswer(): void
    {
        try {
            $this->fetcher([new MockResponse('gone', ['http_code' => 404])])->fetchPage('https://www.printables.com/model/1');
            self::fail('A 404 was treated as a page.');
        } catch (LookupFailedException $exception) {
            self::assertSame(LookupFailure::Unreachable, $exception->failure);
        }
    }

    /**
     * The posted path is the one the interface of Printables runs on, and
     * it carries the same guard as the rest – these three hold it, because
     * nothing else in the suite touches the branch.
     */
    public function testSendsThePostedBodyWithTheJsonTypeAndThePin(): void
    {
        $seen = [];

        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$seen): MockResponse {
            $seen = ['method' => $method, 'url' => $url, 'options' => $options];

            return new MockResponse('{"data":{}}', ['response_headers' => ['content-type' => 'application/json']]);
        });

        $this->fetcherWith($client)->postJson('https://api.printables.com/graphql/', '{"query":"{ x }"}');

        self::assertSame('POST', $seen['method']);
        self::assertSame('https://api.printables.com/graphql/', $seen['url']);
        self::assertSame('{"query":"{ x }"}', $seen['options']['body']);
        self::assertSame(['api.printables.com' => FixedHostResolver::PUBLIC_ADDRESS], $seen['options']['resolve']);
        self::assertContains('Content-Type: application/json', $seen['options']['headers']);
    }

    /**
     * A redirect is refused rather than followed: the other side may not
     * repeat the body, and a silently emptied request would come back
     * looking like an answer with no data.
     */
    public function testRefusesARedirectOnThePostedPath(): void
    {
        $fetcher = $this->fetcher([
            new MockResponse('', ['http_code' => 307, 'response_headers' => ['location' => 'https://api.printables.com/elsewhere/']]),
            new MockResponse('{"data":{}}', ['response_headers' => ['content-type' => 'application/json']]),
        ]);

        try {
            $fetcher->postJson('https://api.printables.com/graphql/', '{}');
            self::fail('A posted request was redirected.');
        } catch (LookupFailedException $exception) {
            self::assertSame(LookupFailure::Unreachable, $exception->failure);
        }
    }

    public function testRefusesAnAnswerThatIsNotJsonOnThePostedPath(): void
    {
        $fetcher = $this->fetcher([
            new MockResponse('<html>just a moment</html>', ['response_headers' => ['content-type' => 'text/html']]),
        ]);

        try {
            $fetcher->postJson('https://api.printables.com/graphql/', '{}');
            self::fail('An HTML answer was accepted on the posted path.');
        } catch (LookupFailedException $exception) {
            self::assertSame(LookupFailure::Unreachable, $exception->failure);
        }
    }

    public function testRefusesAPostedRequestToAHostOutsideThePlatforms(): void
    {
        $this->expectException(LookupFailedException::class);

        $this->fetcher([new MockResponse('secrets')])->postJson('https://api.example.com/graphql/', '{}');
    }

    public function testRefusesAnImageThatIsNotAnImage(): void
    {
        $fetcher = $this->fetcher([
            new MockResponse('<svg onload="alert(1)">', ['response_headers' => ['content-type' => 'image/svg+xml']]),
        ]);

        $this->expectException(LookupFailedException::class);

        $fetcher->fetchImage('https://media.printables.com/cover.svg');
    }

    public function testNamesThePlatformOfAnAddress(): void
    {
        $fetcher = $this->fetcher([]);

        self::assertSame('Printables', $fetcher->platformOf('https://www.printables.com/model/1'));
        self::assertSame('Printables', $fetcher->platformOf('https://media.printables.com/cover.jpg'));
        self::assertNull($fetcher->platformOf('https://www.thingiverse.com/thing:1'));
        self::assertNull($fetcher->platformOf('https://makerworld.com/en/models/1'));
        self::assertNull($fetcher->platformOf('https://cults3d.com/en/3d-model/tool/clip'));
    }

    /**
     * @param list<MockResponse> $responses
     * @param list<string>       $addresses
     */
    private function fetcher(array $responses, array $addresses = [FixedHostResolver::PUBLIC_ADDRESS]): PlatformFetcher
    {
        return $this->fetcherWith(new MockHttpClient($responses), $addresses);
    }

    /**
     * @param list<string> $addresses
     */
    private function fetcherWith(MockHttpClient $client, array $addresses = [FixedHostResolver::PUBLIC_ADDRESS]): PlatformFetcher
    {
        return new PlatformFetcher($client, new FixedHostResolver($addresses));
    }
}
