<?php

namespace App\Modules\Pokepaste\Services;

use Illuminate\Support\Str;

class ShowdownFormatHelper
{
    /**
     * Showdown uses separate species strings per form; our dex stores one row (e.g. `tatsugiri`).
     *
     * @var array<string, string>
     */
    private const SPECIES_MATCH_KEY_ALIASES = [
        'greninja-ash' => 'greninja',
        'tatsugiri-curly' => 'tatsugiri',
        'tatsugiri-droopy' => 'tatsugiri',
        'tatsugiri-stretchy' => 'tatsugiri',
        'aegislash-blade' => 'aegislash',
        'aegislash-shield' => 'aegislash',
        'rotom-wash' => 'rotom',
        'rotom-heat' => 'rotom',
        'rotom-frost' => 'rotom',
        'rotom-mow' => 'rotom',
        'rotom-fan' => 'rotom',
        'meowstic-f' => 'meowstic',
        'tauros-paldea-aqua' => 'tauros-paldea-water',
        'tauros-paldea-blaze' => 'tauros-paldea-fire',
        'tauros-paldea-combat' => 'tauros-paldea',
    ];

    public static function moveSlugToDisplay(string $slug): string
    {
        return Str::title(str_replace('-', ' ', strtolower(trim($slug))));
    }

    public static function moveToSlug(string $displayOrSlug): string
    {
        return strtolower(str_replace([' ', '_'], '-', trim($displayOrSlug)));
    }

    /**
     * Showdown ability labels and PokeAPI-derived titles can differ in minor words (e.g. "of" vs "Of").
     * Compare abilities using the same slug rules as moves.
     */
    public static function abilityToMatchKey(string $displayOrSlug): string
    {
        return self::moveToSlug($displayOrSlug);
    }

    public static function speciesToMatchKey(string $species): string
    {
        $s = str_replace(['♀', '♂', '.'], ['', '', ''], $species);
        $key = Str::slug($s, '-');

        return self::SPECIES_MATCH_KEY_ALIASES[$key] ?? $key;
    }

    /**
     * Title-case label for roster / UI (Unicode-aware).
     */
    public static function pokemonDisplayLabel(string $raw): string
    {
        $t = trim($raw);

        if ($t === '') {
            return $t;
        }

        return Str::title(Str::lower($t));
    }
}
