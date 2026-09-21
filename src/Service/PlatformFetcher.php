<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Fetches a document from one of the model platforms – and nothing else.
 *
 * A visitor types the address and this side requests it, which is a
 * server-side request forgery unless something stands against it. What
 * stands against it is here and nowhere else: the host list, the address
 * check behind it, a redirect that is checked again at every hop, and hard
 * limits on time, size and media type.
 *
 * **The resolved address is pinned into the request.** Checking a name and
 * then letting the client resolve it a second time leaves a window in which
 * the answer can change – the check would pass on a public address and the
 * request would go to a private one.
 *
 * **What is requested is rebuilt from the checked parts**, never the string
 * the visitor typed: the guard and the client would otherwise have to agree
 * on how to read an address, and every disagreement between two parsers is
 * a way around the guard.
 */
final class PlatformFetcher
{
    /**
     * The platforms this site reads, and the name each one is shown under.
     * Subdomains are included, anything else is not: a host must equal one
     * of these or end in a dot plus one of them, so `evil-printables.com`
     * does not pass as `printables.com`.
     */
    public const array PLATFORMS = [
        'printables.com' => 'Printables',
    ];

    public const int MAX_IMAGE_BYTES = 5_242_880;

    public const int MAX_PAGE_BYTES = 2_097_152;

    /**
     * The budget for the whole lookup, redirects included. Per request it
     * would be this much times four, and a visitor waiting on a page that
     * fetches has no way to tell a slow answer from a hung one.
     */
    private const float BUDGET = 5.0;

    private const float MIN_SLICE = 0.5;

    private const int MAX_REDIRECTS = 3;

    private const string USER_AGENT = 'krausgedruckt/1.0 (+https://www.krausgedruckt.de)';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly HostResolver $hostResolver,
    ) {
    }

    /**
     * The platform's display name, or null for an address we do not serve.
     */
    public function platformOf(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (is_string($host) === false || $host === '' || str_starts_with($host, '.')) {
            return null;
        }

        $host = strtolower($host);

        foreach (self::PLATFORMS as $domain => $name) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return $name;
            }
        }

        return null;
    }

    /**
     * @throws LookupFailedException
     */
    public function fetchPage(string $url): string
    {
        return $this->fetch($url, ['text/html'], self::MAX_PAGE_BYTES)['body'];
    }

    /**
     * Posts a JSON document and expects one back – the shape a GraphQL
     * endpoint speaks. A redirect is refused rather than followed: the
     * other side may not repeat the body, and a silently emptied request
     * would look like an answer with no data.
     *
     * Unlike its neighbors the address here is a caller's constant, never
     * something a visitor typed – the guard runs all the same.
     *
     * @throws LookupFailedException
     */
    public function postJson(string $url, string $payload): string
    {
        return $this->fetch($url, ['application/json'], self::MAX_PAGE_BYTES, $payload)['body'];
    }

    /**
     * @return array{type: string, body: string}
     *
     * @throws LookupFailedException
     */
    public function fetchImage(string $url): array
    {
        return $this->fetch($url, ['image/avif', 'image/gif', 'image/jpeg', 'image/png', 'image/webp'], self::MAX_IMAGE_BYTES);
    }

    /**
     * @param list<string> $allowedTypes
     *
     * @return array{type: string, body: string}
     *
     * @throws LookupFailedException
     */
    private function fetch(string $url, array $allowedTypes, int $maxBytes, ?string $payload = null): array
    {
        $deadline = microtime(true) + self::BUDGET;
        $target = $url;

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; ++$hop) {
            $checked = $this->guard($target);

            try {
                $options = [
                    'max_redirects' => 0,
                    'timeout' => $this->remaining($deadline),
                    'max_duration' => $this->remaining($deadline),
                    'headers' => ['User-Agent' => self::USER_AGENT],
                    'resolve' => [$checked['host'] => $checked['ip']],
                ];

                if ($payload !== null) {
                    $options['headers']['Content-Type'] = 'application/json';
                    $options['body'] = $payload;
                }

                $response = $this->httpClient->request($payload === null ? 'GET' : 'POST', $checked['url'], $options);

                $status = $response->getStatusCode();

                if ($status >= 300 && $status < 400) {
                    if ($payload !== null) {
                        throw new LookupFailedException(LookupFailure::Unreachable, 'A posted request was redirected.');
                    }

                    $location = $response->getHeaders(false)['location'][0] ?? null;

                    if (is_string($location) === false || $location === '') {
                        throw new LookupFailedException(LookupFailure::Unreachable, 'Redirect without a target.');
                    }

                    $target = $this->absolutize($location, $checked['url']);

                    continue;
                }

                if ($status !== 200) {
                    throw new LookupFailedException(LookupFailure::Unreachable, sprintf('Answered %d.', $status));
                }

                return [
                    'type' => $this->checkedType($response->getHeaders(false), $allowedTypes),
                    'body' => $this->readAtMost($response, $maxBytes),
                ];
            } catch (HttpExceptionInterface $exception) {
                throw new LookupFailedException(LookupFailure::Unreachable, $exception->getMessage(), $exception);
            }
        }

        throw new LookupFailedException(LookupFailure::Unreachable, 'Too many redirects.');
    }

    /**
     * @return array{url: string, host: string, ip: string}
     *
     * @throws LookupFailedException
     */
    private function guard(string $url): array
    {
        $parts = parse_url($url);

        if ($parts === false || ($parts['scheme'] ?? null) !== 'https') {
            throw new LookupFailedException(LookupFailure::UnsupportedAddress, 'Only https is fetched.');
        }

        if (isset($parts['port']) && $parts['port'] !== 443) {
            throw new LookupFailedException(LookupFailure::UnsupportedAddress, 'Only the default port is fetched.');
        }

        // Credentials in the authority would be handed to the platform, and
        // nothing on a public model page asks for them.
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new LookupFailedException(LookupFailure::UnsupportedAddress, 'No credentials are sent.');
        }

        if ($this->platformOf($url) === null) {
            throw new LookupFailedException(LookupFailure::UnsupportedAddress, 'Host is not a platform we read.');
        }

        $host = strtolower($parts['host']);
        $addresses = $this->hostResolver->resolve($host);

        if ($addresses === []) {
            throw new LookupFailedException(LookupFailure::Unreachable, 'Host does not resolve.');
        }

        // Every answer is checked, not just the one that would be used: a
        // name that points at a public and a private address must not pass.
        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new LookupFailedException(LookupFailure::UnsupportedAddress, 'Host points into a reserved range.');
            }
        }

        return [
            'url' => 'https://'.$host.($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : ''),
            'host' => $host,
            'ip' => $addresses[0],
        ];
    }

    /**
     * @throws LookupFailedException
     */
    private function remaining(float $deadline): float
    {
        $left = $deadline - microtime(true);

        if ($left <= 0) {
            throw new LookupFailedException(LookupFailure::Unreachable, 'Ran out of time.');
        }

        return max(self::MIN_SLICE, $left);
    }

    /**
     * @param array<string, list<string>> $headers
     * @param list<string>                $allowedTypes
     *
     * @throws LookupFailedException
     */
    private function checkedType(array $headers, array $allowedTypes): string
    {
        $header = $headers['content-type'][0] ?? '';
        $type = strtolower(trim(explode(';', $header)[0]));

        if (in_array($type, $allowedTypes, true) === false) {
            throw new LookupFailedException(LookupFailure::Unreachable, sprintf('Answered with %s.', $type !== '' ? $type : 'no media type'));
        }

        return $type;
    }

    /**
     * Reads in chunks and stops at the limit rather than trusting
     * `Content-Length`, which the other side writes and may understate.
     *
     * @throws LookupFailedException
     */
    private function readAtMost(ResponseInterface $response, int $maxBytes): string
    {
        $body = '';

        foreach ($this->httpClient->stream($response) as $chunk) {
            $body .= $chunk->getContent();

            if (strlen($body) > $maxBytes) {
                throw new LookupFailedException(LookupFailure::Unreachable, sprintf('Answer exceeds %d bytes.', $maxBytes));
            }
        }

        return $body;
    }

    private function absolutize(string $location, string $base): string
    {
        if (str_starts_with($location, 'https://') || str_starts_with($location, 'http://')) {
            return $location;
        }

        $parts = parse_url($base);
        $origin = 'https://'.($parts['host'] ?? '');

        if (str_starts_with($location, '/')) {
            return $origin.$location;
        }

        $path = $parts['path'] ?? '/';

        return $origin.substr($path, 0, (int) strrpos($path, '/') + 1).$location;
    }
}
