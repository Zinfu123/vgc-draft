<?php

namespace App\Modules\Trade\Actions;

use App\Modules\Trade\Models\Trade;
use Illuminate\Support\Collection;

class ReadTradesAction
{
    /**
     * @param  array{league_id: int, team_id: int}  $data
     * @return Collection<int, Trade>
     */
    public function __invoke(array $data): Collection
    {
        return Trade::where('league_id', $data['league_id'])
            ->where(function ($query) use ($data) {
                $query->where('requesting_team_id', $data['team_id'])
                    ->orWhere('target_team_id', $data['team_id']);
            })
            ->with([
                'requestingTeam:id,name,user_id',
                'targetTeam:id,name,user_id',
                'offeredPokemon.leaguePokemon.pokemon:id,name,sprite_url',
                'requestedPokemon.leaguePokemon.pokemon:id,name,sprite_url',
            ])
            ->latest()
            ->get();
    }
}
