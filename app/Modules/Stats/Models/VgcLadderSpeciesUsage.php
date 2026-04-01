<?php

namespace App\Modules\Stats\Models;

use Illuminate\Database\Eloquent\Model;

class VgcLadderSpeciesUsage extends Model
{
    protected $table = 'vgc_ladder_species_usage';

    protected $fillable = [
        'format_key',
        'period',
        'species_key',
        'usage_percent',
        'detail',
        'imported_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'usage_percent' => 'float',
            'detail' => 'array',
            'imported_at' => 'datetime',
        ];
    }
}
