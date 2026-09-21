<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\ModelPreview;

/**
 * Reads a Printables model through the interface the platform's own site
 * runs on.
 *
 * **The pages themselves answer a server with a bot check**, measured from
 * the deploy host, so the Open Graph tags are out of reach there. The
 * interface answers without a key and is not behind that check. It is
 * undocumented, though: the field names were found by asking, since
 * introspection is switched off, and they can change without notice. When
 * they do, the failure is caught like any other and the assistant carries
 * on without a preview.
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

    /**
     * The address of a rendition rather than of the original.
     *
     * **A cover on Printables runs into the megabytes** – seven of them on
     * a measured model, which the relay refuses and the card shows as a
     * broken picture. The platform keeps renditions beside the original,
     * addressed by inserting `thumbs/inside/<size>/<format>/` before the
     * file name, and only a fixed set of sizes exists: an invented one is
     * answered with 400. Measured at 640 by 480 in png and jpeg: real
     * images of a few hundred kilobytes, which is what a card 176 pixels
     * wide needs even on a phone with three times the pixels.
     *
     * Like the interface itself this is undocumented and may change. If it
     * does, the relay refuses an answer that is not an image and the card
     * falls back to its own empty state.
     */
    private function thumbnail(string $path): string
    {
        $cut = strrpos($path, '/');
        $format = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($cut === false || $format === '') {
            return $path;
        }

        return substr($path, 0, $cut).'/thumbs/inside/'.self::THUMBNAIL_SIZE.'/'.$format.'/'.substr($path, $cut + 1);
    }

    /**
     * The interface hands its text out encoded. Decoding belongs to the
     * reader rather than to a shared step above it: another platform may
     * well hand its text over decoded already, and doing it twice turns an
     * ampersand a designer typed into the character behind it.
     */
    private function decoded(string $value): string
    {
        return html_entity_decode($value, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
    }

    /**
     * The numeric part of `/model/1042371-parametric-cable-clip`, which is
     * what the interface asks for – the slug behind it is decoration and
     * may be missing.
     */
    private function idOf(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (is_string($path) === false) {
            return null;
        }

        return preg_match('#/model/(\d{1,12})(?!\d)#', $path, $found) === 1 ? $found[1] : null;
    }
}
