<?php

namespace App\Kernel\Support;

use App\Kernel\Contracts\ShowdownFormatter;

final class ShowdownFormatterService implements ShowdownFormatter
{
    public function moveSlugToDisplay(string $slug): string
    {
        return ShowdownFormatHelper::moveSlugToDisplay($slug);
    }

    public function moveToSlug(string $displayOrSlug): string
    {
        return ShowdownFormatHelper::moveToSlug($displayOrSlug);
    }

    public function abilityToMatchKey(string $displayOrSlug): string
    {
        return ShowdownFormatHelper::abilityToMatchKey($displayOrSlug);
    }

    public function speciesToMatchKey(string $species): string
    {
        return ShowdownFormatHelper::speciesToMatchKey($species);
    }

    public function pokemonDisplayLabel(string $raw): string
    {
        return ShowdownFormatHelper::pokemonDisplayLabel($raw);
    }
}
