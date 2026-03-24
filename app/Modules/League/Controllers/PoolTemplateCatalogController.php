<?php

namespace App\Modules\League\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\League\Models\LeaguePokemonTemplate;
use App\Modules\Pokedex\Models\VersionGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PoolTemplateCatalogController extends Controller
{
    public function index(Request $request): Response
    {
        $templates = LeaguePokemonTemplate::query()
            ->published()
            ->with('versionGroup')
            ->orderBy('name')
            ->get();

        $byGeneration = $templates->groupBy(fn (LeaguePokemonTemplate $t): int => (int) ($t->versionGroup?->generation ?? 0))
            ->sortKeysDesc()
            ->map(function ($group) {
                return $group->groupBy(fn (LeaguePokemonTemplate $t): string => $t->versionGroup?->name ?? 'Unknown')
                    ->map(fn ($items) => $items->values()->map(fn (LeaguePokemonTemplate $t) => [
                        'id' => $t->id,
                        'name' => $t->name,
                        'slug' => $t->slug,
                        'description' => $t->description,
                        'version_group' => $t->versionGroup !== null ? [
                            'id' => $t->versionGroup->id,
                            'name' => $t->versionGroup->name,
                            'slug' => $t->versionGroup->slug,
                            'generation' => $t->versionGroup->generation,
                        ] : null,
                    ])->values()->all());
            });

        return Inertia::render('pool-templates/Index', [
            'templatesByGeneration' => $byGeneration,
            'versionGroups' => VersionGroup::query()->orderByDesc('sort_order')->get(['id', 'name', 'slug', 'generation']),
        ]);
    }

    public function preview(string $slug): JsonResponse
    {
        $template = LeaguePokemonTemplate::query()->where('slug', $slug)->first();
        if ($template === null || ! $template->is_published) {
            abort(404);
        }

        $template->loadMissing('versionGroup');

        $rows = $template->rows()
            ->join('pokedex', 'league_pokemon_template_rows.pokedex_id', '=', 'pokedex.id')
            ->orderByDesc('league_pokemon_template_rows.cost')
            ->get([
                'league_pokemon_template_rows.pokedex_id',
                'league_pokemon_template_rows.cost',
                'pokedex.name',
                'pokedex.nationaldex_id',
            ]);

        return response()->json([
            'template' => [
                'id' => $template->id,
                'name' => $template->name,
                'slug' => $template->slug,
                'description' => $template->description,
                'version_group' => $template->versionGroup !== null ? [
                    'name' => $template->versionGroup->name,
                    'generation' => $template->versionGroup->generation,
                ] : null,
            ],
            'rows' => $rows->map(fn ($r) => [
                'pokedex_id' => $r->pokedex_id,
                'cost' => (int) $r->cost,
                'name' => $r->name,
                'nationaldex_id' => $r->nationaldex_id,
            ])->values()->all(),
        ]);
    }
}
