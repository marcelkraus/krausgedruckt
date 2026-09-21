<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\ModelPreview;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Reads what one platform says about a model.
 *
 * One implementation per platform, because no two of them answer the same
 * way: Printables serves its own interface while its pages sit behind a bot
 * check. Whoever asks sees none of that – the assistant, the mail and the
 * image relay know only this interface and the preview it returns.
 */
#[AutoconfigureTag('app.model_reader')]
interface ModelReader
{
    public function supports(string $url): bool;

    /**
     * @throws LookupFailedException
     */
    public function read(string $url): ModelPreview;
}
