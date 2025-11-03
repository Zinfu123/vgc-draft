<?php

namespace App\Modules\Draft\Models;

use Illuminate\Database\Eloquent\Model;

class DraftPick extends Model
{
    protected $table = 'draft_picks';

    protected $fillable = [
        'draft_id',
        'team_id',
        'league_pokemon_id',
        'round_number',
        'pick_number',
    ];

    public function leaguePokemon()
    {
        return $this->belongsTo(\App\Modules\League\Models\LeaguePokemon::class, 'league_pokemon_id');
    }
}
