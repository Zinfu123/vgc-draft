<?php

namespace App\Modules\League\Services;

use App\Modules\League\Models\LeaguePokemon;
use Illuminate\Support\Facades\DB;

class LeaguePokemonDeletionEvaluator
{
    /**
     * @return array{allowed: bool, reason: string|null}
     */
    public function evaluate(LeaguePokemon $leaguePokemon): array
    {
        if ($leaguePokemon->is_drafted || $leaguePokemon->drafted_by !== null) {
            return ['allowed' => false, 'reason' => 'Cannot delete a Pokémon that has been drafted.'];
        }

        if (DB::table('draft_picks')->where('league_pokemon_id', $leaguePokemon->id)->exists()) {
            return ['allowed' => false, 'reason' => 'Cannot delete a Pokémon referenced by draft picks.'];
        }

        if (DB::table('trade_pokemon')->where('league_pokemon_id', $leaguePokemon->id)->exists()) {
            return ['allowed' => false, 'reason' => 'Cannot delete a Pokémon referenced by trades.'];
        }

        if (DB::table('set_team_pokepaste_slots')->where('league_pokemon_id', $leaguePokemon->id)->exists()) {
            return ['allowed' => false, 'reason' => 'Cannot delete a Pokémon referenced by submitted sets.'];
        }

        return ['allowed' => true, 'reason' => null];
    }
}
