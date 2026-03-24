<?php

namespace App\Enums;

/**
 * Integer values follow the canonical in-game order (Gen III onward).
 */
enum PokemonNature: int
{
    case Hardy = 0;
    case Lonely = 1;
    case Brave = 2;
    case Adamant = 3;
    case Naughty = 4;
    case Bold = 5;
    case Docile = 6;
    case Relaxed = 7;
    case Impish = 8;
    case Lax = 9;
    case Timid = 10;
    case Hasty = 11;
    case Serious = 12;
    case Jolly = 13;
    case Naive = 14;
    case Modest = 15;
    case Mild = 16;
    case Quiet = 17;
    case Bashful = 18;
    case Rash = 19;
    case Calm = 20;
    case Gentle = 21;
    case Sassy = 22;
    case Careful = 23;
    case Quirky = 24;

    public function label(): string
    {
        return match ($this) {
            self::Hardy => 'Hardy',
            self::Lonely => 'Lonely',
            self::Brave => 'Brave',
            self::Adamant => 'Adamant',
            self::Naughty => 'Naughty',
            self::Bold => 'Bold',
            self::Docile => 'Docile',
            self::Relaxed => 'Relaxed',
            self::Impish => 'Impish',
            self::Lax => 'Lax',
            self::Timid => 'Timid',
            self::Hasty => 'Hasty',
            self::Serious => 'Serious',
            self::Jolly => 'Jolly',
            self::Naive => 'Naive',
            self::Modest => 'Modest',
            self::Mild => 'Mild',
            self::Quiet => 'Quiet',
            self::Bashful => 'Bashful',
            self::Rash => 'Rash',
            self::Calm => 'Calm',
            self::Gentle => 'Gentle',
            self::Sassy => 'Sassy',
            self::Careful => 'Careful',
            self::Quirky => 'Quirky',
        };
    }

    /**
     * Label for dropdowns, e.g. "Jolly (+Spe, -SpA)". Neutral natures omit modifiers.
     */
    public function labelWithStatModifiers(): string
    {
        $name = $this->label();

        return match ($this) {
            self::Hardy, self::Docile, self::Serious, self::Bashful, self::Quirky => $name,
            self::Lonely => "{$name} (+Atk, -Def)",
            self::Brave => "{$name} (+Atk, -Spe)",
            self::Adamant => "{$name} (+Atk, -SpA)",
            self::Naughty => "{$name} (+Atk, -SpD)",
            self::Bold => "{$name} (+Def, -Atk)",
            self::Relaxed => "{$name} (+Def, -Spe)",
            self::Impish => "{$name} (+Def, -SpA)",
            self::Lax => "{$name} (+Def, -SpD)",
            self::Timid => "{$name} (+Spe, -Atk)",
            self::Hasty => "{$name} (+Spe, -Def)",
            self::Jolly => "{$name} (+Spe, -SpA)",
            self::Naive => "{$name} (+Spe, -SpD)",
            self::Modest => "{$name} (+SpA, -Atk)",
            self::Mild => "{$name} (+SpA, -Def)",
            self::Quiet => "{$name} (+SpA, -Spe)",
            self::Rash => "{$name} (+SpA, -SpD)",
            self::Calm => "{$name} (+SpD, -Atk)",
            self::Gentle => "{$name} (+SpD, -Def)",
            self::Sassy => "{$name} (+SpD, -Spe)",
            self::Careful => "{$name} (+SpD, -SpA)",
        };
    }

    /**
     * @return list<array{value: int, label: string, export_label: string}>
     */
    public static function optionsForFrontend(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            $out[] = [
                'value' => $case->value,
                'label' => $case->labelWithStatModifiers(),
                'export_label' => $case->label(),
            ];
        }

        usort($out, fn (array $a, array $b) => strcmp($a['label'], $b['label']));

        return $out;
    }

    public static function tryFromShowdownName(string $raw): ?self
    {
        $normalized = mb_strtolower(trim(preg_replace('/\s+Nature$/i', '', $raw) ?? $raw));

        foreach (self::cases() as $case) {
            if (mb_strtolower($case->label()) === $normalized) {
                return $case;
            }
        }

        return null;
    }
}
