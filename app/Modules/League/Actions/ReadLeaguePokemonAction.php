<?php

namespace App\Modules\League\Actions;

/* Define Models */
use App\Modules\League\Models\LeaguePokemon;

/* End Define Models */

/* Define Dependencies */
/* End Define Dependencies */

class ReadLeaguePokemonAction
{
    public function __invoke($data)
    {
        if (($data['command'] ?? null) == 'draftedpokemon') {
            $pokemon = LeaguePokemon::where('league_id', $data['league_id'])
                ->whereNotNull('drafted_by')
                ->join('pokedex', 'league_pokemon.pokedex_id', '=', 'pokedex.id')
                ->select('league_pokemon.id', 'pokedex.sprite_url', 'pokedex.name', 'pokedex.type1', 'pokedex.type2', 'league_pokemon.cost')
                ->orderBy('league_pokemon.cost', 'desc')
                ->orderBy('pokedex.name', 'asc')
                ->get();

            return $pokemon;
        } elseif (($data['command'] ?? null) == 'lastdraftedpokemon') {
            $pokemon = LeaguePokemon::when($data['league_id'], function ($query) use ($data) {
                $query->where('league_id', $data['league_id']);
            })
                ->where('is_drafted', true)
                ->orderBy('league_pokemon.cost', 'desc')
                ->orderBy('league_pokemon.name', 'asc')
                ->first();

            return $pokemon->id;
        } elseif (($data['command'] ?? null) == 'available') {
            $pokemon = LeaguePokemon::where('league_id', $data['league_id'])
                ->join('pokedex', 'league_pokemon.pokedex_id', '=', 'pokedex.id')
                ->select('league_pokemon.id', 'pokedex.sprite_url', 'pokedex.name', 'pokedex.type1', 'pokedex.type2', 'league_pokemon.cost')
                ->whereNull('drafted_by')
                ->orderBy('league_pokemon.cost', 'desc')
                ->orderBy('pokedex.name', 'asc')
                ->get();

            return $pokemon;
        } elseif (($data['command'] ?? null) == 'all_with_status') {
            return LeaguePokemon::where('league_id', $data['league_id'])
                ->with(['pokemon:id,name,sprite_url,type1,type2', 'draftedBy:id,name'])
                ->get()
                ->map(fn ($lp) => [
                    'id' => $lp->id,
                    'name' => $lp->pokemon?->name ?? '',
                    'sprite_url' => $lp->pokemon?->sprite_url ?? '',
                    'type1' => $lp->pokemon?->type1 ?? '',
                    'type2' => $lp->pokemon?->type2 ?? '',
                    'cost' => (int) $lp->cost,
                    'banned' => (bool) $lp->banned,
                    'is_drafted' => ($lp->is_drafted || $lp->drafted_by !== null) ? 1 : 0,
                    'drafted_by_team_name' => $lp->draftedBy?->name,
                ])
                ->sortBy(fn ($item) => [-$item['cost'], $item['name']])
                ->values();
        } else {
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
