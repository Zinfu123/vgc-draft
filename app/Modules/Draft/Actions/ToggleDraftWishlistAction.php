<?php

namespace App\Modules\Draft\Actions;

use App\Modules\Draft\Models\DraftWishlistItem;
use App\Modules\Teams\Models\Team;

class ToggleDraftWishlistAction
{
    public function __invoke(Team $team, int $leaguePokemonId): void
    {
        $existing = DraftWishlistItem::query()
            ->where('team_id', $team->id)
            ->where('league_pokemon_id', $leaguePokemonId)
            ->first();

        if ($existing !== null) {
            $existing->delete();

            return;
        }

        $maxSort = DraftWishlistItem::query()
            ->where('team_id', $team->id)
            ->max('sort_order');

        $nextSort = $maxSort === null ? 0 : ((int) $maxSort) + 1;

        DraftWishlistItem::query()->create([
            'team_id' => $team->id,
            'league_pokemon_id' => $leaguePokemonId,
            'sort_order' => $nextSort,
        ]);
    }
}
