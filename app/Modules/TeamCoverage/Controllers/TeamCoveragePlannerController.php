<?php

namespace App\Modules\TeamCoverage\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\TeamCoverage\TeamCoverageLearnsetRequest;
use App\Http\Requests\TeamCoverage\TeamCoveragePokedexSearchRequest;
use App\Http\Requests\TeamCoverage\TeamCoverageTeamRosterRequest;
use App\Modules\League\Models\LeaguePokemon;
use App\Modules\Pokedex\Models\AbilityGenerationData;
use App\Modules\Pokedex\Models\PokeApiMoveCache;
use App\Modules\Pokedex\Models\Pokedex;
use App\Modules\Pokedex\Models\PokemonGenerationData;
use App\Modules\Pokedex\Models\VersionGroup;
use App\Modules\Pokedex\Services\PokedexFilterService;
use App\Modules\Pokedex\Services\TypeEffectivenessTable;
use App\Modules\Teams\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class TeamCoveragePlannerController extends Controller
{
    public function show(): Response
    {
        $user = Auth::user();
        abort_if($user === null, 403);

        $versionGroups = VersionGroup::query()
            ->orderByDesc('sort_order')
            ->get(['id', 'slug', 'name', 'generation', 'sort_order']);

        $defaultSlug = (string) config('pokemon.default_version_group_slug');
        $selectedGroup = $versionGroups->firstWhere('slug', $defaultSlug)
            ?? $versionGroups->first();

        $myTeams = Team::query()
            ->where('user_id', $user->id)
            ->notDropped()
            ->whereHas('league')
            ->with('league')
            ->orderByDesc('league_id')
            ->orderBy('name')
            ->get()
            ->map(fn (Team $team): array => [
                'id' => $team->id,
                'name' => (string) $team->name,
                'league_id' => (int) $team->league_id,
                'league_name' => (string) ($team->league?->name ?? ''),
            ])
            ->values()
            ->all();

        return Inertia::render('tools/TeamCoveragePlanner', [
            'versionGroups' => $versionGroups,
            'defaultVersionSlug' => $selectedGroup?->slug ?? $defaultSlug,
            'typeOrder' => TypeEffectivenessTable::TYPE_ORDER,
            'myTeams' => $myTeams,
        ]);
    }

    public function search(
        TeamCoveragePokedexSearchRequest $request,
        PokedexFilterService $pokedexFilterService,
    ): JsonResponse {
        $validated = $request->validated();
        $perPage = isset($validated['per_page']) ? (int) $validated['per_page'] : 36;

        $paginator = $pokedexFilterService->paginate($perPage, [
            'search' => isset($validated['search']) ? trim((string) $validated['search']) : '',
            'type1' => isset($validated['type1']) ? trim((string) $validated['type1']) : '',
            'type2' => isset($validated['type2']) ? trim((string) $validated['type2']) : '',
            'generation' => $validated['generation'] ?? null,
        ], null);

        return response()->json($paginator);
    }

    public function learnset(
        TeamCoverageLearnsetRequest $request,
        Pokedex $pokedex,
    ): JsonResponse {
        $validated = $request->validated();
        $requestedSlug = isset($validated['game']) && is_string($validated['game']) && $validated['game'] !== ''
            ? $validated['game']
            : (string) config('pokemon.default_version_group_slug');

        $selectedGroup = VersionGroup::query()->where('slug', $requestedSlug)->first();
        if ($selectedGroup === null) {
            return response()->json([
                'game' => null,
                'learnset' => [],
            ]);
        }

        $gameData = PokemonGenerationData::query()
            ->where('pokedex_id', $pokedex->id)
            ->where('version_group_id', $selectedGroup->id)
            ->first();

        $abilitiesPayload = [];
        $learnsetDisplay = [];

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

        return response()->json([
            'pokemon' => [
                'id' => $pokedex->id,
                'name' => $pokedex->name,
                'sprite_url' => $pokedex->sprite_url,
                'type1' => $pokedex->type1,
                'type2' => $pokedex->type2,
            ],
            'game' => [
                'slug' => $selectedGroup->slug,
                'name' => $selectedGroup->name,
                'type1' => $gameData?->type1,
                'type2' => $gameData?->type2,
            ],
            'abilities' => $abilitiesPayload,
            'learnset' => $learnsetDisplay,
        ]);
    }

    public function roster(TeamCoverageTeamRosterRequest $request, Team $team): JsonResponse
    {
        $league = $team->league;
        $versionGroup = $league?->versionGroup();

        if ($versionGroup === null) {
            $versionGroup = VersionGroup::query()
                ->where('slug', (string) config('pokemon.default_version_group_slug'))
                ->first();
        }

        $slug = $versionGroup?->slug ?? (string) config('pokemon.default_version_group_slug');

        /** @var \Illuminate\Database\Eloquent\Collection<int, LeaguePokemon> $roster */
        $roster = LeaguePokemon::query()
            ->where('drafted_by', $team->id)
            ->where('league_id', $team->league_id)
            ->with('pokemon')
            ->orderByDesc('cost')
            ->orderBy('name')
            ->get();

        // Batch-load all game data for the roster in one query
        $dexIds = $roster->pluck('pokedex_id')->filter()->unique()->values()->all();
        $gameDataByDexId = ($versionGroup !== null && $dexIds !== [])
            ? PokemonGenerationData::query()
                ->whereIn('pokedex_id', $dexIds)
                ->where('version_group_id', $versionGroup->id)
                ->get()
                ->keyBy('pokedex_id')
            : collect();

        $slots = [];
        foreach ($roster as $lp) {
            $dex = $lp->pokemon;
            $gameData = $dex !== null ? $gameDataByDexId->get($dex->id) : null;

            $type1 = $gameData?->type1 ?? $dex?->type1;
            $type2 = $gameData?->type2 ?? $dex?->type2;

            $slots[] = [
                'league_pokemon_id' => $lp->id,
                'pokedex_id' => $dex?->id,
                'name' => (string) ($dex?->name ?? $lp->name),
                'sprite_url' => $dex?->sprite_url,
                'type1' => $type1,
                'type2' => $type2,
            ];
        }

        return response()->json([
            'team_id' => $team->id,
            'league_id' => $team->league_id,
            'version_group_slug' => $slug,
            'slots' => $slots,
        ]);
    }
}
