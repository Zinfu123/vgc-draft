<?php

namespace App\Modules\Pokedex\Enums;

enum GenerationalMechanic: int
{
    case None = 0;
    case Tera = 1;
    case Mega = 2;
    case Dynamax = 3;

    public function label(): string
    {
        return match ($this) {
            self::None => 'None',
            self::Tera => 'Tera',
            self::Mega => 'Mega Evolution',
            self::Dynamax => 'Dynamax',
        };
    }
}
