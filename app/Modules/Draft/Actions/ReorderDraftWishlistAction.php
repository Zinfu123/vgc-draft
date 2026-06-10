<?php

namespace App\Modules\Draft\Actions;

use App\Modules\Draft\Models\DraftWishlistItem;
use App\Modules\Teams\Models\Team;
use Illuminate\Support\Facades\DB;

class ReorderDraftWishlistAction
{
    /**
     * @param  array<int, int>  $orderedLeaguePokemonIds
     */
    public function __invoke(Team $team, array $orderedLeaguePokemonIds): void
    {
        DB::transaction(function () use ($team, $orderedLeaguePokemonIds): void {
            foreach ($orderedLeaguePokemonIds as $index => $leaguePokemonId) {
                DraftWishlistItem::query()
                    ->where('team_id', $team->id)
                    ->where('league_pokemon_id', $leaguePokemonId)
                    ->update(['sort_order' => $index]);
            }
        });
    }
}
