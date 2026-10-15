<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\ModelPreview;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.model_reader')]
interface ModelReader
{
    public function supports(string $url): bool;

    /**
     * @throws LookupFailedException
     */
    public function read(string $url): ModelPreview;
}
