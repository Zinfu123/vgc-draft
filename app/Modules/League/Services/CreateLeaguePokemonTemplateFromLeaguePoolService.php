<?php

namespace App\Modules\League\Services;

use App\Modules\League\Models\League;
use App\Modules\League\Models\LeaguePokemon;
use App\Modules\League\Models\LeaguePokemonTemplate;
use App\Modules\League\Models\LeaguePokemonTemplateRow;
use App\Modules\Pokedex\Models\VersionGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreateLeaguePokemonTemplateFromLeaguePoolService
{
    /**
     * @return array{template: LeaguePokemonTemplate, rows_written: int}
     */
    public function createFromLeague(int $leagueId, string $displayName, ?string $slug = null, bool $replace = false, ?int $versionGroupId = null, bool $isPublished = true): array
    {
        $league = League::query()->find($leagueId);
        if ($league === null) {
            throw new InvalidArgumentException("League id {$leagueId} does not exist.");
        }

        $resolvedVersionGroupId = $versionGroupId;
        if ($resolvedVersionGroupId === null) {
            $vg = $league->versionGroup();
            if ($vg === null) {
                $fallback = VersionGroup::query()->orderByDesc('sort_order')->first();
                if ($fallback === null) {
                    throw new InvalidArgumentException('No version group is available for this template.');
                }
                $resolvedVersionGroupId = $fallback->id;
            } else {
                $resolvedVersionGroupId = $vg->id;
            }
        }

        $slug = $slug !== null && $slug !== ''
            ? Str::slug($slug)
            : Str::slug($displayName);

        if ($slug === '') {
            throw new InvalidArgumentException('Slug cannot be empty; provide --slug or a display name that slugifies to a non-empty value.');
        }

        $poolRows = LeaguePokemon::query()
            ->where('league_id', $leagueId)
            ->orderBy('id')
            ->get(['pokedex_id', 'cost'])
            ->keyBy('pokedex_id')
            ->values();

        if ($poolRows->isEmpty()) {
            throw new InvalidArgumentException("League id {$leagueId} has no Pokémon in its pool.");
        }

        return DB::transaction(function () use ($displayName, $slug, $replace, $poolRows, $resolvedVersionGroupId, $isPublished): array {
            $existing = LeaguePokemonTemplate::query()->where('slug', $slug)->first();

            if ($existing !== null && ! $replace) {
                throw new InvalidArgumentException("A template with slug \"{$slug}\" already exists. Use --replace to overwrite its rows.");
            }

            if ($existing === null) {
                $template = LeaguePokemonTemplate::query()->create([
                    'name' => $displayName,
                    'slug' => $slug,
                    'description' => null,
                    'version_group_id' => $resolvedVersionGroupId,
                    'is_published' => $isPublished,
                ]);
            } else {
                $template = $existing;
                $template->update([
                    'name' => $displayName,
                    'version_group_id' => $resolvedVersionGroupId,
                    'is_published' => $isPublished,
                ]);
                LeaguePokemonTemplateRow::query()->where('league_pokemon_template_id', $template->id)->delete();
            }

            $written = 0;
            foreach ($poolRows as $row) {
                LeaguePokemonTemplateRow::query()->updateOrCreate(
                    [
                        'league_pokemon_template_id' => $template->id,
                        'pokedex_id' => $row->pokedex_id,
                    ],
                    [
                        'cost' => (int) $row->cost,
                    ]
                );
                $written++;
            }

            return [
                'template' => $template->fresh(),
                'rows_written' => $written,
            ];
        });
    }
}
