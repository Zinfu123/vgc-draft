<?php

namespace App\Modules\Pokepaste\Actions;

use App\Modules\Pokedex\Models\AbilityGenerationData;
use App\Modules\Pokedex\Models\PokeApiMoveCache;
use App\Modules\Pokedex\Models\PokemonGenerationData;
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
            $abilitiesRaw = [];
            if ($versionGroup !== null) {
                $gameData = PokemonGenerationData::query()
                    ->where('pokedex_id', $lp->pokedex_id)
                    ->where('version_group_id', $versionGroup->id)
                    ->first();

                if ($gameData !== null) {
                    $abilitiesRaw = AbilityGenerationData::query()
                        ->where('pokedex_id', $lp->pokedex_id)
                        ->where('version_group_id', $versionGroup->id)
                        ->orderBy('slot')
                        ->get();
                }
            }

            $abilities = [];
            if ($gameData !== null) {
                foreach ($abilitiesRaw as $row) {
                    $label = ShowdownFormatHelper::moveSlugToDisplay($row->ability_name);
                    if ($label !== '' && ! in_array($label, $abilities, true)) {
                        $abilities[] = $label;
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

        // Batch-fetch move details from the cache table and merge into each roster entry.
        if (! empty($out)) {
            $allSlugs = [];
            foreach ($out as $entry) {
                foreach ($entry['moves'] as $move) {
                    $allSlugs[] = $move['slug'];
                }
            }

            $detailsBySlug = [];
            if (! empty($allSlugs)) {
                $detailsBySlug = PokeApiMoveCache::query()
                    ->whereIn('name', array_unique($allSlugs))
                    ->get(['name', 'type_slug', 'damage_class', 'power', 'accuracy', 'short_effect_en'])
                    ->keyBy('name')
                    ->map(fn ($r) => [
                        'type_slug' => $r->type_slug,
                        'damage_class' => $r->damage_class,
                        'power' => $r->power,
                        'accuracy' => $r->accuracy,
                        'short_effect' => $r->short_effect_en,
                    ])
                    ->all();
            }

            foreach ($out as &$entry) {
                foreach ($entry['moves'] as &$move) {
                    $d = $detailsBySlug[$move['slug']] ?? [];
                    $move['type_slug'] = $d['type_slug'] ?? null;
                    $move['damage_class'] = $d['damage_class'] ?? null;
                    $move['power'] = $d['power'] ?? null;
                    $move['accuracy'] = $d['accuracy'] ?? null;
                    $move['short_effect'] = $d['short_effect'] ?? null;
                }
                unset($move);
            }
            unset($entry);
        }

        return $out;
    }
}
