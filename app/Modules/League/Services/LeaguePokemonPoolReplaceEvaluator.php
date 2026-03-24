<?php

namespace App\Modules\League\Services;

use App\Modules\League\Models\League;
use App\Modules\League\Models\LeaguePokemon;
use Illuminate\Support\Facades\DB;

class LeaguePokemonPoolReplaceEvaluator
{
    /**
     * When the league already has pool rows, full replacement is allowed only if no row is drafted
     * or referenced by draft picks, trades, or set team pokepaste slots.
     *
     * @return array{allowed: bool, reason: string|null}
     */
    public function evaluate(League $league): array
    {
        $ids = LeaguePokemon::query()->where('league_id', $league->id)->pluck('id');
        if ($ids->isEmpty()) {
            return ['allowed' => true, 'reason' => null];
        }

        $drafted = LeaguePokemon::query()
            ->where('league_id', $league->id)
            ->where(function ($q): void {
                $q->where('is_drafted', true)->orWhereNotNull('drafted_by');
            })
            ->exists();

        if ($drafted) {
            return [
                'allowed' => false,
                'reason' => 'Cannot replace the pool while Pokémon are drafted.',
            ];
        }

        if (DB::table('draft_picks')->whereIn('league_pokemon_id', $ids)->exists()) {
            return [
                'allowed' => false,
                'reason' => 'Cannot replace the pool while draft picks reference existing Pokémon.',
            ];
        }

        if (DB::table('trade_pokemon')->whereIn('league_pokemon_id', $ids)->exists()) {
            return [
                'allowed' => false,
                'reason' => 'Cannot replace the pool while trades reference existing Pokémon.',
            ];
        }

        if (DB::table('set_team_pokepaste_slots')->whereIn('league_pokemon_id', $ids)->whereNotNull('league_pokemon_id')->exists()) {
            return [
                'allowed' => false,
                'reason' => 'Cannot replace the pool while submitted sets reference existing Pokémon.',
            ];
        }

        return ['allowed' => true, 'reason' => null];
    }
}
