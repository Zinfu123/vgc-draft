<?php

namespace App\Modules\Pokedex\Models;

use Illuminate\Database\Eloquent\Model;

class Pokedex extends Model
{
    protected $table = 'pokedex';

    protected $fillable = [
        'name',
        'type1',
        'type2',
        'sprite_url',
    ];

    public function league()
    {
        return $this->belongsToMany(\App\Modules\League\Models\League::class, 'league_pokemon', 'pokedex_id')->withPivot('cost');
    }

    public function leaguePokemon()
    {
        return $this->hasMany(\App\Modules\League\Models\LeaguePokemon::class, 'pokedex_id');
    }
}
