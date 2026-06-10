<?php

namespace App\Modules\League\Services;

use App\Modules\League\Models\LeaguePokemon;
use App\Modules\League\Models\LeaguePokemonTemplateRow;
use App\Modules\Pokedex\Models\Pokedex;
use App\Modules\Pokedex\Services\DraftPoolPokedexResolver;
use Illuminate\Support\Facades\DB;

class NormalizeDraftPoolPokemonFormsService
{
    public function __construct(
        private DraftPoolPokedexResolver $pokedexResolver,
    ) {}

    /**
     * @return array{template_rows_updated: int, template_rows_merged: int, league_pokemon_updated: int, league_pokemon_merged: int}
     */
    public function normalize(bool $dryRun = false): array
    {
        $stats = [
            'template_rows_updated' => 0,
            'template_rows_merged' => 0,
            'league_pokemon_updated' => 0,
            'league_pokemon_merged' => 0,
        ];

        /** @var list<int> $formPokedexIds */
        $formPokedexIds = [];

        foreach (Pokedex::query()->select(['id', 'name', 'nationaldex_id'])->cursor() as $row) {
            $canonical = $this->pokedexResolver->resolvePokedex($row);
            if ($canonical !== null && (int) $canonical->id !== (int) $row->id) {
                $formPokedexIds[] = (int) $row->id;
            }
        }

        if ($formPokedexIds === []) {
            return $stats;
        }

        $callback = function () use ($formPokedexIds, $dryRun, &$stats): void {
            $stats['template_rows_updated'] += $this->normalizeTemplateRows($formPokedexIds, $dryRun, $stats);
            $stats['league_pokemon_updated'] += $this->normalizeLeaguePokemon($formPokedexIds, $dryRun, $stats);
        };

        if ($dryRun) {
            $callback();
        } else {
            DB::transaction($callback);
        }

        return $stats;
    }

    /**
     * @param  list<int>  $formPokedexIds
     */
    private function normalizeTemplateRows(array $formPokedexIds, bool $dryRun, array &$stats): int
    {
        $updated = 0;

        $rows = LeaguePokemonTemplateRow::query()
            ->whereIn('pokedex_id', $formPokedexIds)
            ->with('pokedex:id,name')
            ->get();

        foreach ($rows as $row) {
            $pokedex = $row->pokedex;
            if ($pokedex === null) {
                continue;
            }

            $canonical = $this->pokedexResolver->resolvePokedex($pokedex);
            if ($canonical === null || (int) $canonical->id === (int) $row->pokedex_id) {
                continue;
            }

            $duplicate = LeaguePokemonTemplateRow::query()
                ->where('league_pokemon_template_id', $row->league_pokemon_template_id)
                ->where('pokedex_id', $canonical->id)
                ->first();

            if ($duplicate !== null) {
                if (! $dryRun) {
                    if ((int) $duplicate->cost < (int) $row->cost) {
                        $duplicate->update(['cost' => $row->cost]);
                    }
                    $row->delete();
                }
                $stats['template_rows_merged']++;

                continue;
            }

            if (! $dryRun) {
                $row->update(['pokedex_id' => $canonical->id]);
            }

            $updated++;
        }

        return $updated;
    }

    /**
     * @param  list<int>  $formPokedexIds
     */
    private function normalizeLeaguePokemon(array $formPokedexIds, bool $dryRun, array &$stats): int
    {
        $updated = 0;

        $rows = LeaguePokemon::query()
            ->whereIn('pokedex_id', $formPokedexIds)
            ->with('pokemon:id,name')
            ->get();

        foreach ($rows as $row) {
            $pokedex = $row->pokemon;
            if ($pokedex === null) {
                continue;
            }

            $canonical = $this->pokedexResolver->resolvePokedex($pokedex);
            if ($canonical === null || (int) $canonical->id === (int) $row->pokedex_id) {
                continue;
            }

            $duplicate = LeaguePokemon::query()
                ->where('league_id', $row->league_id)
                ->where('pokedex_id', $canonical->id)
                ->first();

            if ($duplicate !== null) {
                if (! $dryRun) {
                    $this->mergeLeaguePokemonReferences($row, $duplicate);
                    $row->delete();
                }
                $stats['league_pokemon_merged']++;

                continue;
            }

            if (! $dryRun) {
                $row->update([
                    'pokedex_id' => $canonical->id,
                    'name' => $canonical->name,
                ]);
            }

            $updated++;
        }

        return $updated;
    }

    private function mergeLeaguePokemonReferences(LeaguePokemon $from, LeaguePokemon $into): void
    {
        if ($from->is_drafted && ! $into->is_drafted) {
            $into->update([
                'is_drafted' => true,
                'drafted_by' => $from->drafted_by,
            ]);
        }

        DB::table('draft_picks')
            ->where('league_pokemon_id', $from->id)
            ->update(['league_pokemon_id' => $into->id]);

        DB::table('draft_wishlist_items')
            ->where('league_pokemon_id', $from->id)
            ->update(['league_pokemon_id' => $into->id]);

        DB::table('trade_pokemon')
            ->where('league_pokemon_id', $from->id)
            ->update(['league_pokemon_id' => $into->id]);

        DB::table('set_team_pokepaste_slots')
            ->where('league_pokemon_id', $from->id)
            ->update(['league_pokemon_id' => $into->id]);
    }
}
