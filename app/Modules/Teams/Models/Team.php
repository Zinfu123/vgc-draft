<?php

namespace App\Modules\Teams\Models;

use App\Modules\Draft\Actions\DraftPokemonAction;
use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    protected $table = 'teams';

    protected $fillable = [
        'name',
        'logo',
        'league_id',
        'user_id',
        'pick_position',
        'trades',
        'draft_points',
        'victory_points',
        'admin_flag',
        'set_wins',
        'set_losses',
        'game_wins',
        'game_losses',
        'seed',
        'pool_id',
        'created_at',
        'updated_at',
    ];

    public function league()
    {
        return $this->belongsTo(\App\Modules\League\Models\League::class, 'league_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function draftPicks()
    {
        return $this->hasMany(\App\Modules\Draft\Models\DraftPick::class, 'team_id');
    }

    public function pokemon()
    {
        return $this->hasMany(\App\Modules\League\Models\LeaguePokemon::class, 'drafted_by', 'id');
    }

}
