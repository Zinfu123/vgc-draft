<?php

namespace App\Modules\Pokedex\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Pokedex\Models\Pokedex;
use App\Modules\Pokedex\Models\PokemonGameData;
use App\Modules\Pokedex\Models\VersionGroup;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PokedexController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'type1' => ['sometimes', 'nullable', 'string', 'max:30'],
            'type2' => ['sometimes', 'nullable', 'string', 'max:30'],
            'generation' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:99'],
            'per_page' => ['sometimes', 'integer', 'min:10', 'max:100'],
        ]);

        $perPage = $validated['per_page'] ?? 36;
        $search = isset($validated['search']) ? trim((string) $validated['search']) : '';
        $type1 = isset($validated['type1']) ? trim((string) $validated['type1']) : '';
        $type2 = isset($validated['type2']) ? trim((string) $validated['type2']) : '';
        $generation = $validated['generation'] ?? null;

        $pokemon = Pokedex::query()
            ->select('pokedex.id', 'pokedex.name', 'pokedex.sprite_url', 'pokedex.type1', 'pokedex.type2', 'pokedex.nationaldex_id')
            ->when($search !== '', function ($query) use ($search) {
                $query->where('pokedex.name', 'like', '%'.$search.'%');
            })
            ->when($type1 !== '', function ($query) use ($type1) {
                $query->where(function ($q) use ($type1) {
                    $q->where('pokedex.type1', $type1)->orWhere('pokedex.type2', $type1);
                });
            })
            ->when($type2 !== '', function ($query) use ($type2) {
                $query->where(function ($q) use ($type2) {
                    $q->where('pokedex.type1', $type2)->orWhere('pokedex.type2', $type2);
                });
            })
            ->when($generation !== null, function ($query) use ($generation) {
                $query->whereHas('gameData.versionGroup', function ($q) use ($generation) {
                    $q->where('generation', $generation);
                });
            })
            ->orderBy('pokedex.name')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('pokedex/PokedexIndex', [
            'pokemon' => $pokemon,
            'filters' => [
                'search' => $search,
                'type1' => $type1,
                'type2' => $type2,
                'generation' => $generation,
                'per_page' => $perPage,
            ],
            'typeOptions' => $this->typeOptions(),
            'generationFilterOptions' => $this->generationFilterOptions(),
        ]);
    }

    public function show(Request $request, Pokedex $pokedex): Response
    {
        $versionGroups = VersionGroup::query()
            ->orderByDesc('sort_order')
            ->get(['id', 'slug', 'name', 'generation', 'sort_order']);

        $defaultSlug = $versionGroups->first()?->slug ?? (string) config('pokemon.default_version_group_slug');
        $requestedSlug = $request->query('game');
        $requestedSlug = is_string($requestedSlug) && $requestedSlug !== '' ? $requestedSlug : $defaultSlug;

        $selectedGroup = $versionGroups->firstWhere('slug', $requestedSlug)
            ?? $versionGroups->first();

        $gameData = null;
        if ($selectedGroup !== null) {
            $gameData = PokemonGameData::query()
                ->where('pokedex_id', $pokedex->id)
                ->where('version_group_id', $selectedGroup->id)
                ->first();
        }

        return Inertia::render('pokedex/PokedexShow', [
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
                'ability_primary' => $gameData->ability_primary,
                'ability_secondary' => $gameData->ability_secondary,
                'ability_hidden' => $gameData->ability_hidden,
                'learnset' => $gameData->learnset,
                'mechanics' => $gameData->mechanics,
            ] : null,
        ]);
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

    /**
     * @return list<int>
     */
    private function generationFilterOptions(): array
    {
        return VersionGroup::query()
            ->distinct()
            ->orderBy('generation')
            ->pluck('generation')
            ->all();
    }
}
