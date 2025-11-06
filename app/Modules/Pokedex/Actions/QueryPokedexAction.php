<?php

namespace App\Modules\Pokedex\Actions;

/* Define Models */
use App\Modules\Pokedex\Models\Pokedex; 
use Illuminate\Support\Facades\Log;
/* End Define Models */

/* Define Dependencies */
/* End Define Dependencies */

class QueryPokedexAction
{
    public function __invoke(array $data)
    {
        $pokemon = Pokedex::when($data['league_id'], function ($query) use ($data) {
            $query->whereHas('leaguePokemon', function ($query) use ($data) {
                $query->where('league_id', $data['league_id']);
            });
        })
            ->select('pokedex.id', 'sprite_url', 'pokedex.name', 'type1', 'type2')
            ->with('league')
            ->join('league_pokemon', 'pokedex.id', '=', 'league_pokemon.pokedex_id')
            ->when($data['command'] ?? null == 'draftedpokemon', function ($query) {
                $query->where('league_pokemon.is_drafted', 0);
            })
            ->orderBy('league_pokemon.cost', 'desc')
            ->orderBy('pokedex.name', 'asc')
            ->get();

        return $pokemon;
    }
}
