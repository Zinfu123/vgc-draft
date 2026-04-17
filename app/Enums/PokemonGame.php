<?php

namespace App\Enums;

enum PokemonGame: string
{
    case ScarletViolet = 'scarlet_violet';
    case Champions = 'champions';

    public function label(): string
    {
        return match ($this) {
            self::ScarletViolet => 'Pokémon Scarlet & Violet',
            self::Champions => 'Pokémon Champions',
        };
    }

    /**
     * Whether this game is available for league creation.
     */
    public function isAvailable(): bool
    {
        return match ($this) {
            self::ScarletViolet => true,
            self::Champions => true,
        };
    }

    public function versionGroupSlug(): string
    {
        return match ($this) {
            self::ScarletViolet => 'scarlet-violet',
            self::Champions => 'champions-reg-ma',
        };
    }

    public function generation(): int
    {
        return match ($this) {
            self::ScarletViolet => 9,
            self::Champions => 9,
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
