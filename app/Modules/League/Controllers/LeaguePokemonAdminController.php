<?php

namespace App\Modules\League\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\League\ApplyLeaguePokemonTemplateRequest;
use App\Http\Requests\League\ImportLeaguePokemonCsvRequest;
use App\Http\Requests\League\StoreLeaguePokemonRequest;
use App\Http\Requests\League\UpdateLeaguePokemonCostRequest;
use App\Modules\League\Actions\ReadLeaguePokemonAction;
use App\Modules\League\Models\League;
use App\Modules\League\Models\LeaguePokemon;
use App\Modules\League\Models\LeaguePokemonTemplate;
use App\Modules\League\Models\LeaguePokemonTemplateRow;
use App\Modules\League\Services\ApplyLeaguePokemonTemplateService;
use App\Modules\League\Services\ImportLeaguePokemonToLeagueFromCsvService;
use App\Modules\League\Services\LeaguePokemonDeletionEvaluator;
use App\Modules\League\Services\LeaguePokemonPoolReplaceEvaluator;
use App\Modules\Pokedex\Models\Pokedex;
use App\Modules\Pokedex\Services\PokedexFilterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class LeaguePokemonAdminController extends Controller
{
    public function show(
        League $league,
        ReadLeaguePokemonAction $readLeaguePokemonAction,
        LeaguePokemonPoolReplaceEvaluator $replaceEvaluator,
    ): Response {
        $this->authorize('admin', $league);

        $verdict = $replaceEvaluator->evaluate($league);
        $hasPool = LeaguePokemon::query()->where('league_id', $league->id)->exists();

        $templates = LeaguePokemonTemplate::query()
            ->with(['versionGroup:id,slug,name,generation'])
            ->withCount('rows')
            ->orderBy('name')
            ->get()
            ->map(fn (LeaguePokemonTemplate $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
                'description' => $t->description,
                'rows_count' => (int) $t->rows_count,
                'version_group' => $t->versionGroup !== null ? [
                    'id' => $t->versionGroup->id,
                    'slug' => $t->versionGroup->slug,
                    'name' => $t->versionGroup->name,
                    'generation' => (int) $t->versionGroup->generation,
                ] : null,
            ]);

        return Inertia::render('league/admin/LeaguePokemonPool', [
            'league' => ['id' => $league->id, 'name' => $league->name],
            'templates' => $templates,
            'pool' => $readLeaguePokemonAction(['league_id' => $league->id, 'command' => 'all_with_status']),
            'poolReplace' => [
                'has_pool' => $hasPool,
                'allowed' => $verdict['allowed'],
                'blocked_reason' => $verdict['reason'],
            ],
            'pokedexTypeOptions' => $this->typeOptions(),
            'pokedexGenerationOptions' => PokedexFilterService::generationFilterOptionInts(),
        ]);
    }

    public function templatesIndex(League $league): JsonResponse
    {
        $this->authorize('admin', $league);

        $templates = LeaguePokemonTemplate::query()
            ->with(['versionGroup:id,slug,name,generation'])
            ->withCount('rows')
            ->orderBy('name')
            ->get()
            ->map(fn (LeaguePokemonTemplate $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
                'description' => $t->description,
                'rows_count' => (int) $t->rows_count,
                'version_group' => $t->versionGroup !== null ? [
                    'id' => $t->versionGroup->id,
                    'slug' => $t->versionGroup->slug,
                    'name' => $t->versionGroup->name,
                    'generation' => (int) $t->versionGroup->generation,
                ] : null,
            ]);

        return response()->json(['data' => $templates]);
    }

    public function templatePreview(League $league, LeaguePokemonTemplate $template, Request $request): JsonResponse
    {
        $this->authorize('admin', $league);

        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:10', 'max:100'],
        ]);
        $perPage = $validated['per_page'] ?? 36;

        $rows = LeaguePokemonTemplateRow::query()
            ->where('league_pokemon_template_id', $template->id)
            ->join('pokedex', 'league_pokemon_template_rows.pokedex_id', '=', 'pokedex.id')
            ->select(
                'league_pokemon_template_rows.id',
                'league_pokemon_template_rows.cost',
                'pokedex.name',
                'pokedex.sprite_url',
                'pokedex.type1',
                'pokedex.type2',
                'pokedex.nationaldex_id',
            )
            ->orderByDesc('league_pokemon_template_rows.cost')
            ->orderBy('pokedex.name')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json($rows);
    }

    public function applyTemplate(
        ApplyLeaguePokemonTemplateRequest $request,
        League $league,
        LeaguePokemonTemplate $template,
        ApplyLeaguePokemonTemplateService $applyService,
    ): RedirectResponse {
        try {
            $applyService->apply($league, $template, $request->boolean('confirm_replace'));
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'template' => $e->getMessage(),
            ]);
        }

        return redirect()
            ->route('leagues.admin.pokemon-pool', ['league' => $league->id])
            ->with('success', 'League Pokémon pool updated from template.');
    }

    public function updatePokemon(
        UpdateLeaguePokemonCostRequest $request,
        League $league,
        LeaguePokemon $leaguePokemon,
    ): RedirectResponse {
        $leaguePokemon->cost = $request->integer('cost');
        $leaguePokemon->save();

        return back()->with('success', 'Cost updated.');
    }

    public function destroy(
        League $league,
        LeaguePokemon $leaguePokemon,
        LeaguePokemonDeletionEvaluator $deletionEvaluator,
    ): RedirectResponse {
        $this->authorize('admin', $league);

        if ((int) $leaguePokemon->league_id !== (int) $league->id) {
            abort(404);
        }

        $verdict = $deletionEvaluator->evaluate($leaguePokemon);
        if (! $verdict['allowed']) {
            throw ValidationException::withMessages([
                'delete' => $verdict['reason'] ?? 'Cannot delete this Pokémon.',
            ]);
        }

        $leaguePokemon->delete();

        return back()->with('success', 'Pokémon removed from the league pool.');
    }

    public function store(
        StoreLeaguePokemonRequest $request,
        League $league,
    ): RedirectResponse {
        $pokedex = Pokedex::query()->findOrFail($request->integer('pokedex_id'));

        LeaguePokemon::query()->updateOrCreate(
            [
                'league_id' => $league->id,
                'pokedex_id' => $pokedex->id,
            ],
            [
                'name' => $pokedex->name,
                'cost' => $request->integer('cost'),
            ]
        );

        return back()->with('success', 'Pokémon added to the league pool.');
    }

    public function importCsv(
        ImportLeaguePokemonCsvRequest $request,
        League $league,
        ImportLeaguePokemonToLeagueFromCsvService $importCsv,
    ): RedirectResponse {
        $result = $importCsv->import($league->id, $request->file('csv_file'));

        $message = "Imported {$result['upserted']} Pokémon (CSV).";
        if ($result['skipped_unknown_dex'] > 0) {
            $message .= " Skipped {$result['skipped_unknown_dex']} unknown nationaldex IDs.";
        }

        return back()->with('success', $message);
    }

    public function pokedexSearch(League $league, Request $request, PokedexFilterService $pokedexFilterService): JsonResponse
    {
        $this->authorize('admin', $league);

        $validated = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'type1' => ['sometimes', 'nullable', 'string', 'max:30'],
            'type2' => ['sometimes', 'nullable', 'string', 'max:30'],
            'generation' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:99'],
            'per_page' => ['sometimes', 'integer', 'min:10', 'max:100'],
            'exclude_in_pool' => ['sometimes', 'boolean'],
        ]);

        $perPage = $validated['per_page'] ?? 36;
        $exclude = null;
        if ($request->boolean('exclude_in_pool')) {
            $exclude = LeaguePokemon::query()->where('league_id', $league->id)->pluck('pokedex_id')->all();
        }

        $paginator = $pokedexFilterService->paginate($perPage, [
            'search' => isset($validated['search']) ? trim((string) $validated['search']) : '',
            'type1' => isset($validated['type1']) ? trim((string) $validated['type1']) : '',
            'type2' => isset($validated['type2']) ? trim((string) $validated['type2']) : '',
            'generation' => $validated['generation'] ?? null,
        ], $exclude);

        return response()->json($paginator);
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
