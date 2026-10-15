<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Carries the case, not a sentence: the wording belongs to the page.
 */
final class LookupFailedException extends \RuntimeException
{
    public function __construct(public readonly LookupFailure $failure, string $message = '', ?\Throwable $previous = null)
    {
        parent::__construct($message !== '' ? $message : $failure->name, 0, $previous);
    }
}
