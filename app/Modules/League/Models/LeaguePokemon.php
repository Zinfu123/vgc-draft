<?php

namespace App\Modules\League\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\League\Models\League;
use App\Models\User;

class LeaguePokemon extends Model
{
    protected $table = 'league_pokemon';

    protected $fillable = [
        'league_id',
        'pokemon_id',
        'pokemon_name',
    ];
}
