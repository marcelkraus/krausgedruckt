<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Two cases are the visitor's to correct, two are ours; none stops the inquiry.
 */
enum LookupFailure
{
    case NoMetadata;
    case NotAModelPage;
    case Unreachable;
    case UnsupportedAddress;
}
