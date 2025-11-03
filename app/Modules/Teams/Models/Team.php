<?php

namespace App\Modules\Teams\Models;

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
        'set_wins',
        'set_losses',
        'game_wins',
        'game_losses',
        'created_at',
        'updated_at',
    ];

    public function league()
    {
        return $this->belongsToMany(\App\Modules\League\Models\League::class, 'league_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function draftPicks()
    {
        return $this->hasMany(\App\Modules\Draft\Models\DraftPick::class, 'team_id');
    }
}
