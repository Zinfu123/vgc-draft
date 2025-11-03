<?php

namespace App\Modules\League\Models;

use Illuminate\Database\Eloquent\Model;

class LeaguePokemon extends Model
{
    protected $table = 'league_pokemon';

    protected $fillable = [
        'league_id',
        'pokedex_id',
        'name',
        'cost',
    ];

    public function league()
    {
        return $this->belongsTo(League::class);
    }

    public function pokemon()
    {
        return $this->belongsTo(\App\Modules\Pokedex\Models\Pokedex::class, 'pokedex_id');
    }

    public function draftedBy()
    {
        return $this->belongsTo(\App\Modules\Teams\Models\Team::class);
    }

    public function draftPicks()
    {
        return $this->hasMany(\App\Modules\Draft\Models\DraftPick::class, 'league_pokemon_id');
    }
}
