<?php

namespace App\Modules\League\Enums;

enum LeagueType: int
{
    //
    case SINGLEDAY = 1;
    case DAILY = 2;
    case WEEKLY = 3;
    case CUSTOM = 4;

    public function label(): string
    {
        return match ($this) {
            self::SINGLEDAY => 'Single Day',
            self::DAILY => 'Daily',
            self::WEEKLY => 'Weekly',
            self::CUSTOM => 'Custom',
        };
    }
}
