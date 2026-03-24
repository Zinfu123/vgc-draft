<?php

namespace App\Modules\Stats\Models;

use Illuminate\Database\Eloquent\Model;

class PokemonUsageStatsMeta extends Model
{
    protected $table = 'pokemon_usage_stats_meta';

    protected $fillable = [
        'total_picks',
        'total_bans',
        'total_bring_units',
        'rebuilt_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rebuilt_at' => 'datetime',
        ];
    }
}
