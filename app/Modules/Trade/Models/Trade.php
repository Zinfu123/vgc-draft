<?php

namespace App\Modules\Trade\Models;

use App\Modules\League\Models\League;
use App\Modules\Teams\Models\Team;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Trade extends Model
{
    protected $table = 'trades';

    protected $fillable = [
        'league_id',
        'requesting_team_id',
        'target_team_id',
        'status',
    ];

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class, 'league_id');
    }

    public function requestingTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'requesting_team_id');
    }

    public function targetTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'target_team_id');
    }

    public function tradePokemon(): HasMany
    {
        return $this->hasMany(TradePokemon::class, 'trade_id');
    }

    public function offeredPokemon(): HasMany
    {
        return $this->hasMany(TradePokemon::class, 'trade_id')->where('direction', 'offered');
    }

    public function requestedPokemon(): HasMany
    {
        return $this->hasMany(TradePokemon::class, 'trade_id')->where('direction', 'requested');
    }
}
