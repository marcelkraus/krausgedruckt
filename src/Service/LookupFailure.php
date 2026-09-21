<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Why reading a model page did not work.
 *
 * The cases are kept apart because the page answers them differently. Two
 * of them are the visitor's to correct and need to say what is wrong with
 * the address – a platform we do not read is not the same mistake as the
 * wrong page on one we do, and telling him the first when he made the
 * second leaves him with nothing to fix. The other two are ours to
 * apologize for. None of them may stop the inquiry.
 */
enum LookupFailure
{
    case NoMetadata;
    case NotAModelPage;
    case Unreachable;
    case UnsupportedAddress;
}
