<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\ModelPreview;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Only the page is ever fetched, never the model file—that keeps a
 * non-commercial license intact.
 */
final class ModelLookup
{
    private const int MAX_DESCRIPTION_LENGTH = 600;

    private const int MAX_TITLE_LENGTH = 200;

    /**
     * @param iterable<ModelReader> $readers
     */
    public function __construct(
        #[AutowireIterator('app.model_reader')]
        private readonly iterable $readers,
    ) {
    }

    /**
     * @throws LookupFailedException
     */
    public function lookup(string $url): ModelPreview
    {
        foreach ($this->readers as $reader) {
            if ($reader->supports($url)) {
                return $this->clamped($reader->read($url));
            }
        }

        throw new LookupFailedException(LookupFailure::UnsupportedAddress);
    }

    // A description's line breaks would grow the card past the screen.
    private function clamped(ModelPreview $preview): ModelPreview
    {
        return new ModelPreview(
            title: $this->shorten($preview->title, self::MAX_TITLE_LENGTH),
            description: $this->shorten($preview->description, self::MAX_DESCRIPTION_LENGTH),
            imageUrl: $preview->imageUrl,
            url: $preview->url,
            platform: $preview->platform,
            license: $preview->license !== null ? $this->shorten($preview->license, self::MAX_TITLE_LENGTH) : null,
        );
    }

    private function shorten(string $value, int $length): string
    {
        $collapsed = trim((string) preg_replace('/\s+/u', ' ', $value));

        return mb_strlen($collapsed) > $length ? mb_substr($collapsed, 0, $length - 1).'…' : $collapsed;
    }
}
