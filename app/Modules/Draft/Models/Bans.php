<?php

namespace App\Modules\Draft\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Bans extends Model
{
    use LogsActivity;

    protected $table = 'draft_bans';

    protected $fillable = [
        'league_id',
        'team_id',
        'pokedex_id',
        'round_number',
        'status',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function league(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Modules\League\Models\League::class, 'league_id');
    }

    public function team(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Modules\Teams\Models\Team::class, 'team_id');
    }

    public function pokedex(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Modules\Pokedex\Models\Pokedex::class, 'pokedex_id');
    }
}
