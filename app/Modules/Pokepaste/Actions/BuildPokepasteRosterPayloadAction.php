<?php

namespace App\Modules\Pokepaste\Actions;

use App\Modules\Pokedex\Models\PokemonGameData;
use App\Modules\Pokedex\Models\VersionGroup;
use App\Modules\Pokepaste\Services\ShowdownFormatHelper;
use Illuminate\Support\Collection;

class BuildPokepasteRosterPayloadAction
{
    /**
     * @param  Collection<int, \App\Modules\League\Models\LeaguePokemon>  $leaguePokemon
     * @return list<array<string, mixed>>
     */
    public function __invoke(Collection $leaguePokemon, ?VersionGroup $versionGroup): array
    {
        $out = [];

        foreach ($leaguePokemon as $lp) {
            $gameData = null;
            if ($versionGroup !== null) {
                $gameData = PokemonGameData::query()
                    ->where('pokedex_id', $lp->pokedex_id)
                    ->where('version_group_id', $versionGroup->id)
                    ->first();
            }

            $abilities = [];
            if ($gameData !== null) {
                foreach ([$gameData->ability_primary, $gameData->ability_secondary, $gameData->ability_hidden] as $a) {
                    if ($a !== null && $a !== '' && ! in_array($a, $abilities, true)) {
                        $abilities[] = $a;
                    }
                }
            }

            $moves = [];
            if ($gameData !== null && is_array($gameData->learnset)) {
                $seen = [];
                foreach ($gameData->learnset as $row) {
                    if (! is_array($row) || empty($row['move_name']) || ! is_string($row['move_name'])) {
                        continue;
                    }
                    $slug = ShowdownFormatHelper::moveToSlug($row['move_name']);
                    if (isset($seen[$slug])) {
                        continue;
                    }
                    $seen[$slug] = true;
                    $moves[] = [
                        'slug' => $slug,
                        'label' => ShowdownFormatHelper::moveSlugToDisplay($row['move_name']),
                    ];
                }
            }

            usort($moves, fn (array $a, array $b) => strcmp($a['label'], $b['label']));

            $teraCapable = false;
            if ($gameData !== null) {
                $mechanics = $gameData->mechanics ?? [];
                $teraCapable = ! empty($mechanics['tera_capable']);
            }

            $dex = $lp->pokemon;

            $out[] = [
                'league_pokemon_id' => $lp->id,
                'name' => ShowdownFormatHelper::pokemonDisplayLabel((string) $lp->name),
                'pokedex_name' => ShowdownFormatHelper::pokemonDisplayLabel($dex !== null ? (string) $dex->name : (string) $lp->name),
                'sprite_url' => $dex?->sprite_url,
                'abilities' => $abilities,
                'moves' => $moves,
                'tera_capable' => $teraCapable,
                'game_data_missing' => $gameData === null,
            ];
        }

        return $out;
    }
}
