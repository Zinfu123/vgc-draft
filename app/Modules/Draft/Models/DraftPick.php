<?php

namespace App\Modules\Draft\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class DraftPick extends Model
{
    use LogsActivity;

    protected $table = 'draft_picks';

    protected $fillable = [
        'draft_id',
        'team_id',
        'league_pokemon_id',
        'round_number',
        'pick_number',
        'league_id',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function leaguePokemon(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Modules\League\Models\LeaguePokemon::class, 'league_pokemon_id');
    }
}
