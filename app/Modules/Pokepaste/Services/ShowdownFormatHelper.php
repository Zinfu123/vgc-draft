<?php

namespace App\Modules\Pokepaste\Services;

use Illuminate\Support\Str;

class ShowdownFormatHelper
{
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

        return Str::slug($s, '-');
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
