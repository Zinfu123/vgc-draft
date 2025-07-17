<?php

namespace App\Modules\Teams\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\League\Models\League;
use App\Models\User;

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
        return $this->belongsTo(League::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}