<?php

namespace App\Modules\Pokedex\Services;

use App\Kernel\Support\ShowdownFormatHelper;
use App\Modules\Pokedex\Models\AbilityGenerationData;
use App\Modules\Pokedex\Models\PokemonGenerationData;

class PokemonAbilityListResolver
{
    public function __construct(
        private PokeApiAbilityReader $abilityReader,
    ) {}

    /**
     * Allowed abilities for a species in a version group (generation table rows, then primary/secondary/hidden fallback).
     *
     * @return list<array{pokeapi_ability_id: int, ability_name: string, is_hidden: bool, slot: int}>
     */
    public function forPokemon(int $pokedexId, int $versionGroupId, ?PokemonGenerationData $gameData = null): array
    {
        $rows = AbilityGenerationData::query()
            ->where('pokedex_id', $pokedexId)
            ->where('version_group_id', $versionGroupId)
            ->orderBy('slot')
            ->get();

        if ($rows->isNotEmpty()) {
            return $rows->map(fn (AbilityGenerationData $row): array => [
                'pokeapi_ability_id' => $row->pokeapi_ability_id,
                'ability_name' => $row->ability_name,
                'is_hidden' => $row->is_hidden,
                'slot' => $row->slot,
            ])->all();
        }

        $gameData ??= PokemonGenerationData::query()
            ->where('pokedex_id', $pokedexId)
            ->where('version_group_id', $versionGroupId)
            ->first();

        if ($gameData === null) {
            return [];
        }

        return $this->fromGenerationDataAbilityIds($gameData);
    }

    /**
     * Resolve a Showdown / paste ability label to the canonical display form, or null if not allowed.
     */
    public function matchDisplayAbility(
        string $input,
        int $pokedexId,
        int $versionGroupId,
        ?PokemonGenerationData $gameData = null,
    ): ?string {
        $abilityKey = ShowdownFormatHelper::abilityToMatchKey($input);

        foreach ($this->forPokemon($pokedexId, $versionGroupId, $gameData) as $row) {
            if (ShowdownFormatHelper::abilityToMatchKey($row['ability_name']) === $abilityKey) {
                return ShowdownFormatHelper::moveSlugToDisplay($row['ability_name']);
            }
        }

        return null;
    }

    /**
     * @return list<array{pokeapi_ability_id: int, ability_name: string, is_hidden: bool, slot: int}>
     */
    private function fromGenerationDataAbilityIds(PokemonGenerationData $gameData): array
    {
        $definitions = [
            ['id' => $gameData->ability_primary_pokeapi_id, 'hidden' => false, 'slot' => 1],
            ['id' => $gameData->ability_secondary_pokeapi_id, 'hidden' => false, 'slot' => 2],
            ['id' => $gameData->ability_hidden_pokeapi_id, 'hidden' => true, 'slot' => 3],
        ];

        $out = [];

        foreach ($definitions as $definition) {
            $id = $definition['id'];
            if (! is_int($id) || $id <= 0) {
                continue;
            }

            $slug = $this->resolveAbilitySlug($id);
            if ($slug === null) {
                continue;
            }

            $out[] = [
                'pokeapi_ability_id' => $id,
                'ability_name' => $slug,
                'is_hidden' => $definition['hidden'],
                'slot' => $definition['slot'],
            ];
        }

        return $out;
    }

    private function resolveAbilitySlug(int $pokeapiAbilityId): ?string
    {
        $existing = AbilityGenerationData::query()
            ->where('pokeapi_ability_id', $pokeapiAbilityId)
            ->value('ability_name');

        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $props = $this->abilityReader->propsForInertia($pokeapiAbilityId);
        $slug = $props['name_slug'] ?? null;

        return is_string($slug) && $slug !== '' ? $slug : null;
    }
}
