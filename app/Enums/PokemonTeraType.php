<?php

namespace App\Enums;

/**
 * Canonical type names as used in roster / game data (Title Case).
 */
enum PokemonTeraType: string
{
    case Normal = 'Normal';
    case Fire = 'Fire';
    case Water = 'Water';
    case Electric = 'Electric';
    case Grass = 'Grass';
    case Ice = 'Ice';
    case Fighting = 'Fighting';
    case Poison = 'Poison';
    case Ground = 'Ground';
    case Flying = 'Flying';
    case Psychic = 'Psychic';
    case Bug = 'Bug';
    case Rock = 'Rock';
    case Ghost = 'Ghost';
    case Dragon = 'Dragon';
    case Dark = 'Dark';
    case Steel = 'Steel';
    case Fairy = 'Fairy';
    case Stellar = 'Stellar';

    /**
     * @return list<string>
     */
    public static function allValuesForGeneration(int $generation): array
    {
        $base = [
            self::Normal,
            self::Fire,
            self::Water,
            self::Electric,
            self::Grass,
            self::Ice,
            self::Fighting,
            self::Poison,
            self::Ground,
            self::Flying,
            self::Psychic,
            self::Bug,
            self::Rock,
            self::Ghost,
            self::Dragon,
            self::Dark,
            self::Steel,
            self::Fairy,
        ];

        if ($generation >= 9) {
            $base[] = self::Stellar;
        }

        $values = array_map(fn (self $t) => $t->value, $base);
        sort($values, SORT_STRING);

        return array_values($values);
    }

    public static function isAllowedValue(string $value, int $generation): bool
    {
        return in_array($value, self::allValuesForGeneration($generation), true);
    }
}
