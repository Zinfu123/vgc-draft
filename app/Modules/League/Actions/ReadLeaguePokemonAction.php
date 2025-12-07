<?php

namespace App\Modules\League\Actions;

/* Define Models */
use App\Modules\League\Models\LeaguePokemon;
use Illuminate\Support\Facades\Log;
/* End Define Models */

/* Define Dependencies */
/* End Define Dependencies */

class ReadLeaguePokemonAction
{
    public function __invoke($data)
    {
        if(($data['command'] ?? null) == 'draftedpokemon') {
            $pokemon = LeaguePokemon::where('league_id', $data['league_id'])
            ->whereNotNull('drafted_by')
            ->join('pokedex', 'league_pokemon.pokedex_id', '=', 'pokedex.id')
            ->select('league_pokemon.id', 'pokedex.sprite_url', 'pokedex.name', 'pokedex.type1', 'pokedex.type2', 'league_pokemon.cost')
            ->orderBy('league_pokemon.cost', 'desc')
            ->orderBy('pokedex.name', 'asc')
            ->get();
            Log::info('draftedpokemon: ' . $pokemon);
            return $pokemon;
        }
        elseif (($data['command'] ?? null) == 'lastdraftedpokemon') {
            $pokemon = LeaguePokemon::when($data['league_id'], function ($query) use ($data) {
                $query->where('league_id', $data['league_id']);
            })
            ->where('is_drafted', true)
            ->orderBy('league_pokemon.cost', 'desc')
            ->orderBy('league_pokemon.name', 'asc')
            ->first();
        return $pokemon->id;
        }
        else if (($data['command'] ?? null) == 'available') {
            $pokemon = LeaguePokemon::where('league_id', $data['league_id'])
            ->join('pokedex', 'league_pokemon.pokedex_id', '=', 'pokedex.id')
            ->select('league_pokemon.id', 'pokedex.sprite_url', 'pokedex.name', 'pokedex.type1', 'pokedex.type2', 'league_pokemon.cost')
            ->whereNull('drafted_by')
            ->orderBy('league_pokemon.cost', 'desc')
            ->orderBy('pokedex.name', 'asc')
            ->get();
            return $pokemon;
        }
        else {
        $pokemon = LeaguePokemon::when($data['league_id'], function ($query) use ($data) {
            $query->where('league_id', $data['league_id']);
        })
        ->join('pokedex', 'league_pokemon.pokedex_id', '=', 'pokedex.id')
        ->select('league_pokemon.id', 'pokedex.sprite_url', 'pokedex.name', 'pokedex.type1', 'pokedex.type2', 'league_pokemon.cost')
        ->orderBy('league_pokemon.cost', 'desc')
        ->orderBy('pokedex.name', 'asc')
        ->get();
            return $pokemon;
        }
    }
}
