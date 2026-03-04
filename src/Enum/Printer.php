<?php

namespace App\Enum;

enum Printer: string
{
    case CORE_ONE = 'Prusa CORE One+';
    case MINI = 'Prusa MINI+';
    case MK4S = 'Prusa MK4S';
    case MK4S_MMU = 'Prusa MK4S+MMU';

    public function isMultiColor(): bool
    {
        return $this === self::MK4S_MMU;
    }
}
