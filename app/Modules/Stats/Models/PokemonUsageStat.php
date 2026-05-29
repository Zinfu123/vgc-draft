<?php

namespace App\Modules\Stats\Models;

use App\Modules\Pokedex\Models\Pokedex;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PokemonUsageStat extends Model
{
    protected $table = 'pokemon_usage_stats';

    protected $fillable = [
        'pokedex_id',
        'draft_pick_count',
        'draft_ban_count',
        'match_bring_count',
        'game_bring_count',
        'game_wins',
        'game_losses',
        'ko_count',
        'avg_ko_per_game',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'avg_ko_per_game' => 'float',
        ];
    }

    /**
     * @return BelongsTo<Pokedex, $this>
     */
    public function pokedex(): BelongsTo
    {
        return $this->belongsTo(Pokedex::class, 'pokedex_id');
    }
}
