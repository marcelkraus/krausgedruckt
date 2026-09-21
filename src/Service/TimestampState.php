<?php

declare(strict_types=1);

namespace App\Service;

/**
 * What a submitted timestamp says about its sender.
 *
 * Kept apart because the answers differ: two of them are a machine and get
 * a silent fake success, one is a person whose form sat open and is asked
 * to send again.
 */
enum TimestampState
{
    case Expired;
    case Invalid;
    case TooFast;
    case Valid;
}
