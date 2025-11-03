<?php

namespace App\Modules\League\Actions;

/* Define Models */
use App\Modules\League\Models\LeaguePokemon;

/* End Define Models */

/* Define Dependencies */
/* End Define Dependencies */

class ReadLeaguePokemonAction
{
    public function __invoke($league_id)
    {
        $pokemon = LeaguePokemon::when($league_id, function ($query, $league_id) {
            $query->where('league_id', $league_id);
        })
            ->select('pokedex_id', 'cost')
            ->with([
                'pokemon' => function ($query) {
                    $query->select('id', 'sprite_url', 'name', 'type1', 'type2');
                },
            ])
            ->orderBy('cost', 'desc')
            ->get();

        return $pokemon;
    }
}
