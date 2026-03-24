<?php

namespace App\Modules\Playoffs\Models;

use App\Enums\Playoffs\PlayoffFormat;
use App\Enums\Playoffs\PlayoffStatus;
use App\Modules\League\Models\League;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Playoff extends Model
{
    protected $table = 'playoffs';

    protected $fillable = [
        'league_id',
        'format',
        'bracket_size',
        'status',
        'seed_order',
        'created_at',
        'updated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'format' => PlayoffFormat::class,
            'status' => PlayoffStatus::class,
            'seed_order' => 'array',
        ];
    }

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class, 'league_id');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(PlayoffMatch::class, 'playoff_id');
    }
}
