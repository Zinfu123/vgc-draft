<?php

namespace App\Modules\League\Enums;

enum LeagueType: int
{
    //
    case ROUND_ROBIN = 1;
    case POOL = 2;
    case BRACKET = 3;


public function label(): string
{
    return match ($this) {
        self::ROUND_ROBIN => 'Round Robin',
        self::POOL => 'Pool',
        self::BRACKET => 'Bracket',
    };
}
}