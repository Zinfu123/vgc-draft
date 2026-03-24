<?php

namespace App\Modules\MatchPrep\Actions;

use App\Modules\League\Models\LeaguePokemon;
use App\Modules\Pokepaste\Services\ShowdownFormatHelper;
use Illuminate\Support\Collection;

class BuildLeaguePokemonDraftPreviewAction
{
    /**
     * @param  Collection<int, LeaguePokemon>  $leaguePokemon
     * @return list<array<string, mixed>>
     */
    public function __invoke(Collection $leaguePokemon): array
    {
        $out = [];

        foreach ($leaguePokemon as $lp) {
            $dex = $lp->pokemon;
            $species = $dex !== null
                ? ShowdownFormatHelper::pokemonDisplayLabel((string) $dex->name)
                : ShowdownFormatHelper::pokemonDisplayLabel((string) $lp->name);
            $nickname = trim((string) $lp->name);
            $showNickname = $nickname !== '' && strcasecmp($nickname, $species) !== 0;

            $spriteUrl = $dex?->sprite_url;
            if (is_string($spriteUrl) && trim($spriteUrl) === '') {
                $spriteUrl = null;
            }

            $out[] = [
                'league_pokemon_id' => $lp->id,
                'species_label' => $species,
                'nickname_label' => $showNickname ? $nickname : null,
                'sprite_url' => $spriteUrl,
                'type1' => $dex?->type1,
                'type2' => $dex?->type2,
                'cost' => $lp->cost,
            ];
        }

        return $out;
    }
}
