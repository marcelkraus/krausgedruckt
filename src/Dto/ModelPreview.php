<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * What one model page on a print platform tells us about itself.
 *
 * Built from what a platform answers about a visitor's link, so every
 * value is a stranger's string: it is escaped where it is rendered and
 * never interpolated into markup here.
 *
 * **The license is for the workshop, not for the visitor.** Where a
 * platform names it, it goes into the inquiry mail so the calculation
 * knows what it is dealing with – shown on the page it would read as a
 * promise that nobody checked.
 */
final readonly class ModelPreview
{
    public function __construct(
        public string $title,
        public string $description,
        public ?string $imageUrl,
        public string $url,
        public string $platform,
        public ?string $license = null,
    ) {
    }
}
