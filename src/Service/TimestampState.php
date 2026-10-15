<?php

declare(strict_types=1);

namespace App\Service;

enum TimestampState
{
    case Expired;
    case Invalid;
    case TooFast;
    case Valid;
}
