<?php

namespace App\Modules\Pokedex\Services;

use App\Kernel\Contracts\PokedexPages;
use App\Modules\Pokedex\Models\AbilityGenerationData;
use App\Modules\Pokedex\Models\PokeApiMoveCache;
use App\Modules\Pokedex\Models\Pokedex;
use App\Modules\Pokedex\Models\PokemonGenerationData;
use App\Modules\Pokedex\Models\VersionGroup;

class PokedexPagesService implements PokedexPages
{
    public function __construct(
        private PokedexFilterService $pokedexFilterService,
        private PokeApiAbilityReader $abilityReader,
        private PokeApiItemReader $itemReader,
    ) {}

    /**
     * @param  array{search?: string|null, type1?: string|null, type2?: string|null, generation?: int|null, per_page?: int}  $validated
     * @return array{pokemon: mixed, filters: array{search: string, type1: string, type2: string, generation: int|null, per_page: int}, typeOptions: list<string>, generationFilterOptions: list<int>}
     */
    public function indexProps(array $validated): array
    {
        $perPage = $validated['per_page'] ?? 36;
        $search = isset($validated['search']) ? trim((string) $validated['search']) : '';
        $type1 = isset($validated['type1']) ? trim((string) $validated['type1']) : '';
        $type2 = isset($validated['type2']) ? trim((string) $validated['type2']) : '';
        $generation = $validated['generation'] ?? null;

        $pokemon = $this->pokedexFilterService->paginate($perPage, [
            'search' => $search,
            'type1' => $type1,
            'type2' => $type2,
            'generation' => $generation,
        ]);

        return [
            'pokemon' => $pokemon,
            'filters' => [
                'search' => $search,
                'type1' => $type1,
                'type2' => $type2,
                'generation' => $generation,
                'per_page' => $perPage,
            ],
            'typeOptions' => $this->typeOptions(),
            'generationFilterOptions' => PokedexFilterService::generationFilterOptionInts(),
        ];
    }

    /**
     * @return array{pokemon: array{id: int, name: string, sprite_url: string|null, type1: string, type2: string|null, nationaldex_id: int}, versionGroups: mixed, selectedVersionSlug: string, gameData: array<string, mixed>|null}
     */
    public function showProps(int $pokedexId, ?string $requestedGameSlug): array
    {
        $pokedex = Pokedex::query()->findOrFail($pokedexId);

        $versionGroups = VersionGroup::query()
            ->orderByDesc('sort_order')
            ->get(['id', 'slug', 'name', 'generation', 'sort_order']);

        $defaultSlug = $versionGroups->first()?->slug ?? (string) config('pokemon.default_version_group_slug');
        $requestedSlug = is_string($requestedGameSlug) && $requestedGameSlug !== '' ? $requestedGameSlug : $defaultSlug;

        $selectedGroup = $versionGroups->firstWhere('slug', $requestedSlug)
            ?? $versionGroups->first();

        $gameData = null;
        $abilitiesPayload = [];
        $learnsetDisplay = [];

        if ($selectedGroup !== null) {
            $gameData = PokemonGenerationData::query()
                ->where('pokedex_id', $pokedex->id)
                ->where('version_group_id', $selectedGroup->id)
                ->first();

            if ($gameData !== null) {
                $abilitiesPayload = AbilityGenerationData::query()
                    ->where('pokedex_id', $pokedex->id)
                    ->where('version_group_id', $selectedGroup->id)
                    ->orderBy('slot')
                    ->get()
                    ->map(fn (AbilityGenerationData $a): array => [
                        'pokeapi_ability_id' => $a->pokeapi_ability_id,
                        'ability_name' => $a->ability_name,
                        'slot' => $a->slot,
                        'is_hidden' => $a->is_hidden,
                    ])
                    ->all();

                $learnset = is_array($gameData->learnset) ? $gameData->learnset : [];
                $moveIds = [];
                foreach ($learnset as $row) {
                    if (is_array($row) && isset($row['move_id']) && is_numeric($row['move_id'])) {
                        $moveIds[(int) $row['move_id']] = true;
                    }
                }
                $caches = PokeApiMoveCache::query()
                    ->whereIn('id', array_keys($moveIds))
                    ->get()
                    ->keyBy('id');

                foreach ($learnset as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $mid = isset($row['move_id']) ? (int) $row['move_id'] : 0;
                    $cache = $caches->get($mid);
                    $learnsetDisplay[] = array_merge($row, [
                        'type_slug' => $cache?->type_slug,
                        'damage_class' => $cache?->damage_class,
                        'power' => $cache?->power,
                        'accuracy' => $cache?->accuracy,
                        'ailment_name' => $cache?->ailment_name,
                    ]);
                }
            }
        }

        return [
            'pokemon' => [
                'id' => $pokedex->id,
                'name' => $pokedex->name,
                'sprite_url' => $pokedex->sprite_url,
                'type1' => $pokedex->type1,
                'type2' => $pokedex->type2,
                'nationaldex_id' => $pokedex->nationaldex_id,
            ],
            'versionGroups' => $versionGroups,
            'selectedVersionSlug' => $selectedGroup?->slug ?? $requestedSlug,
            'gameData' => $gameData ? [
                'hp' => $gameData->hp,
                'atk' => $gameData->atk,
                'def' => $gameData->def,
                'spa' => $gameData->spa,
                'spd' => $gameData->spd,
                'spe' => $gameData->spe,
                'type1' => $gameData->type1,
                'type2' => $gameData->type2,
                'ability_primary_pokeapi_id' => $gameData->ability_primary_pokeapi_id,
                'ability_secondary_pokeapi_id' => $gameData->ability_secondary_pokeapi_id,
                'ability_hidden_pokeapi_id' => $gameData->ability_hidden_pokeapi_id,
                'abilities' => $abilitiesPayload,
                'learnset' => $learnsetDisplay,
                'mechanics' => $gameData->mechanics,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function abilityProps(int $id): array
    {
        return $this->abilityReader->propsForInertia($id);
    }

    /**
     * @return array<string, mixed>
     */
    public function itemProps(int $id): array
    {
        return $this->itemReader->propsForInertia($id);
    }

    /**
     * @return list<string>
     */
    private function typeOptions(): array
    {
        return [
            'Bug',
            'Dark',
            'Dragon',
            'Electric',
            'Fairy',
            'Fighting',
            'Fire',
            'Flying',
            'Ghost',
            'Grass',
            'Ground',
            'Ice',
            'Normal',
            'Poison',
            'Psychic',
            'Rock',
            'Steel',
            'Water',
        ];
    }
}
