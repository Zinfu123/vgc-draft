<?php

namespace App\Modules\Matches\Models;

use App\Modules\League\Models\League;
use App\Modules\Pokepaste\Models\SetTeamPokepaste;
use App\Modules\Teams\Models\Team;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Set extends Model
{
    protected $table = 'sets';

    protected $fillable = [
        'pool_id',
        'league_id',
        'team1_id',
        'team2_id',
        'team1_score',
        'team2_score',
        'winner_id',
        'team1_pokepaste',
        'team2_pokepaste',
        'replay1',
        'replay2',
        'replay3',
        'round',
        'status',
    ];

    public function team1()
    {
        return $this->belongsTo(Team::class, 'team1_id', 'id')->select('id', 'name', 'logo', 'user_id')->with('user:id,name');
    }

    public function team2()
    {
        return $this->belongsTo(Team::class, 'team2_id', 'id')->select('id', 'name', 'logo', 'user_id')->with('user:id,name');
    }

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class, 'league_id');
    }

    public function setTeamPokepastes(): HasMany
    {
        return $this->hasMany(SetTeamPokepaste::class, 'set_id');
    }
}
