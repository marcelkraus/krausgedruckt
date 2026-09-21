<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Raised when a model address cannot be read.
 *
 * Carries the case rather than a sentence: the wording belongs to the page,
 * which speaks to the visitor, not to the service that fetches.
 */
final class LookupFailedException extends \RuntimeException
{
    public function __construct(public readonly LookupFailure $failure, string $message = '', ?\Throwable $previous = null)
    {
        parent::__construct($message !== '' ? $message : $failure->name, 0, $previous);
    }
}
