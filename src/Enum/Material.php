<?php

namespace App\Enum;

enum Material: string
{
    case ASA = 'ASA';
    case FLEX = 'FLEX';
    case PA12_CF = 'PA12-CF';
    case PC = 'PC';
    case PC_CF = 'PC-CF';
    case PETG = 'PETG';
    case PLA = 'PLA';

    /**
     * Every material shares this tag, so it is kept in one place instead of
     * being repeated in each branch below.
     */
    private const SHARED_HASHTAG = '#filament';

    /**
     * @return string[]
     */
    public function getHashtags(): array
    {
        $hashtag = match ($this) {
            self::ASA => '#asa',
            self::FLEX => '#flex',
            self::PA12_CF => '#pa12cf',
            self::PC => '#pc',
            self::PC_CF => '#pccf',
            self::PETG => '#petg',
            self::PLA => '#pla',
        };

        return [self::SHARED_HASHTAG, $hashtag];
    }
}
