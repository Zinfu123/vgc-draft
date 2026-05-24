<?php

namespace App\Modules\Trade\Actions;

use App\Modules\Trade\Models\Trade;
use Illuminate\Support\Collection;

class ReadLeagueTradeHistoryAction
{
    /**
     * @return Collection<int, Trade>
     */
    public function __invoke(int $leagueId, int $limit = 80): Collection
    {
        return Trade::query()
            ->where('league_id', $leagueId)
            ->where('status', 'accepted')
            ->with([
                'requestingTeam:id,name,user_id',
                'targetTeam:id,name,user_id',
                'offeredPokemon.leaguePokemon.pokemon:id,name,sprite_url',
                'requestedPokemon.leaguePokemon.pokemon:id,name,sprite_url',
            ])
            ->latest()
            ->take($limit)
            ->get();
    }
}
