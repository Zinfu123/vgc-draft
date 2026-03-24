<?php

namespace App\Enums;

enum PokemonGame: string
{
    case ScarletViolet = 'scarlet_violet';

    public function label(): string
    {
        return match ($this) {
            self::ScarletViolet => 'Pokémon Scarlet & Violet',
        };
    }

    public function versionGroupSlug(): string
    {
        return match ($this) {
            self::ScarletViolet => 'scarlet-violet',
        };
    }

    public function generation(): int
    {
        return match ($this) {
            self::ScarletViolet => 9,
        };
    }

    /**
     * @return array<int, self>
     */
    public static function forGeneration(int $generation): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $game) => $game->generation() === $generation
        ));
    }
}
