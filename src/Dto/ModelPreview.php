<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Every value is a stranger's string. The license goes into the inquiry mail,
 * never onto the page.
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
