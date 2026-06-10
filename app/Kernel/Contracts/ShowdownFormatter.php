<?php

namespace App\Kernel\Contracts;

interface ShowdownFormatter
{
    public function moveSlugToDisplay(string $slug): string;

    public function moveToSlug(string $displayOrSlug): string;

    public function abilityToMatchKey(string $displayOrSlug): string;

    public function speciesToMatchKey(string $species): string;

    public function pokemonDisplayLabel(string $raw): string;
}
