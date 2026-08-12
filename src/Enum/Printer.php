<?php

declare(strict_types=1);

namespace App\Enum;

enum Printer: string
{
    case CORE_ONE_INDX = 'Prusa CORE One INDX';
    case CORE_ONE_L = 'Prusa CORE One L';
    case CORE_ONE_PLUS = 'Prusa CORE One+';
    case MINI_PLUS = 'Prusa MINI+';
    case MK4S = 'Prusa MK4S';
    case MK4S_MMU3 = 'Prusa MK4S + MMU3';

    public function isMultiColor(): bool
    {
        return match ($this) {
            self::CORE_ONE_INDX, self::MK4S_MMU3 => true,
            default => false,
        };
    }

    /**
     * @return string[]
     */
    public function getHashtags(): array
    {
        return match ($this) {
            self::CORE_ONE_INDX => ['#bondtechindx', '#indx', '#prusacoreoneindx'],
            self::CORE_ONE_L => ['#prusacoreonel'],
            self::CORE_ONE_PLUS => ['#prusacoreone', '#prusacoreoneplus'],
            self::MINI_PLUS => ['#prusamini', '#prusaminiplus'],
            self::MK4S => ['#prusamk4', '#prusamk4s'],
            self::MK4S_MMU3 => ['#prusamk4', '#prusamk4s', '#prusammu3'],
        };
    }
}
