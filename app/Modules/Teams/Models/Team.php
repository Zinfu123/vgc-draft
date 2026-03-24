<?php

namespace App\Modules\Teams\Models;

use App\Modules\Matches\Models\Pool;
use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    protected $table = 'teams';

    protected static function booted(): void
    {
        static::creating(function (Team $team) {
            if ($team->pool_id === null && $team->league_id) {
                $firstPool = Pool::where('league_id', $team->league_id)->orderBy('id')->first();
                if ($firstPool) {
                    $team->pool_id = $firstPool->id;
                }
            }
        });
    }

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
        'medal_placement',
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
        return $this->hasMany(\App\Modules\League\Models\LeaguePokemon::class, 'drafted_by', 'id')->orderBy('cost', 'desc');
    }
}
