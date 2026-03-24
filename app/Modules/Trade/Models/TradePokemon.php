<?php

namespace App\Modules\Trade\Models;

use App\Modules\League\Models\LeaguePokemon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TradePokemon extends Model
{
    protected $table = 'trade_pokemon';

    protected $fillable = [
        'trade_id',
        'league_pokemon_id',
        'direction',
    ];

    public function trade(): BelongsTo
    {
        return $this->belongsTo(Trade::class, 'trade_id');
    }

    public function leaguePokemon(): BelongsTo
    {
        return $this->belongsTo(LeaguePokemon::class, 'league_pokemon_id');
    }
}
