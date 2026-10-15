<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\ModelPreview;

/**
 * The pages answer a server with a bot check, so the platform's own, undocumented
 * interface is read; a changed field ends in a lookup without preview.
 */
final class PrintablesReader implements ModelReader
{
    private const string ENDPOINT = 'https://api.printables.com/graphql/';

    private const string MEDIA_BASE = 'https://media.printables.com/';

    private const string THUMBNAIL_SIZE = '640x480';

    private const string QUERY = '{ print(id: "%s") { name summary image { filePath } license { name } } }';

    public function __construct(private readonly PlatformFetcher $fetcher)
    {
    }

    public function supports(string $url): bool
    {
        return $this->fetcher->platformOf($url) === 'Printables';
    }

    public function read(string $url): ModelPreview
    {
        $id = $this->idOf($url);

        if ($id === null) {
            throw new LookupFailedException(LookupFailure::NotAModelPage);
        }

        $payload = json_encode(['query' => sprintf(self::QUERY, $id)], JSON_THROW_ON_ERROR);

        try {
            $answer = json_decode($this->fetcher->postJson(self::ENDPOINT, $payload), true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new LookupFailedException(LookupFailure::NoMetadata, 'The interface did not answer with JSON.', $exception);
        }

        $print = $answer['data']['print'] ?? null;

        if (is_array($print) === false || is_string($print['name'] ?? null) === false) {
            throw new LookupFailedException(LookupFailure::NoMetadata);
        }

        $path = $print['image']['filePath'] ?? null;

        return new ModelPreview(
            title: $this->decoded($print['name']),
            description: is_string($print['summary'] ?? null) ? $this->decoded(strip_tags($print['summary'])) : '',
            imageUrl: is_string($path) && $path !== '' ? self::MEDIA_BASE.$this->thumbnail(ltrim($path, '/')) : null,
            url: $url,
            platform: 'Printables',
            license: is_string($print['license']['name'] ?? null) ? $this->decoded($print['license']['name']) : null,
        );
    }

    // The original runs into megabytes; only fixed rendition sizes exist.
    private function thumbnail(string $path): string
    {
        $cut = strrpos($path, '/');
        $format = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($cut === false || $format === '') {
            return $path;
        }

        return substr($path, 0, $cut).'/thumbs/inside/'.self::THUMBNAIL_SIZE.'/'.$format.'/'.substr($path, $cut + 1);
    }

    // Decoded here, not centrally: decoding twice would mangle a typed ampersand.
    private function decoded(string $value): string
    {
        return html_entity_decode($value, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
    }

    private function idOf(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (is_string($path) === false) {
            return null;
        }

        return preg_match('#/model/(\d{1,12})(?!\d)#', $path, $found) === 1 ? $found[1] : null;
    }
}
