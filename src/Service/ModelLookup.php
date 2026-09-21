<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\ModelPreview;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Hands a model address to the reader that serves its platform.
 *
 * The one place that knows there is more than one way in. Everything above
 * asks here and gets a preview or a failure, whichever platform it was.
 *
 * **Only the page is ever fetched, never the model file** – that one is
 * downloaded by hand after an order, on the customer's instruction, which
 * is what keeps a non-commercial license intact.
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

    /**
     * A reader hands over a stranger's text, decoded, and a platform allows
     * more of it than a card can hold – the line breaks of a description
     * would grow the card until the fields below it leave the screen.
     */
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
