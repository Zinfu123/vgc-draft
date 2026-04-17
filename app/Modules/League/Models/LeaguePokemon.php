<?php

namespace App\Modules\League\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class LeaguePokemon extends Model
{
    use LogsActivity;

    protected $table = 'league_pokemon';

    protected $fillable = [
        'league_id',
        'pokedex_id',
        'name',
        'cost',
        'banned',
        'drafted_by',
        'is_drafted',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['drafted_by', 'is_drafted', 'banned', 'cost'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    protected function casts(): array
    {
        return [
            'banned' => 'boolean',
            'is_drafted' => 'boolean',
        ];
    }

    public function league()
    {
        return $this->belongsTo(League::class);
    }

    public function pokemon()
    {
        return $this->belongsTo(\App\Modules\Pokedex\Models\Pokedex::class, 'pokedex_id');
    }

    public function draftedBy()
    {
        return $this->belongsTo(\App\Modules\Teams\Models\Team::class, 'drafted_by');
    }

    public function draftPicks()
    {
        return $this->hasMany(\App\Modules\Draft\Models\DraftPick::class, 'league_pokemon_id');
    }

    public function draftWishlistItems()
    {
        return $this->hasMany(\App\Modules\Draft\Models\DraftWishlistItem::class, 'league_pokemon_id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeFreeAgencyEligible(Builder $query): Builder
    {
        return $query->whereNull('drafted_by')
            ->where('banned', false)
            ->where('is_drafted', false);
    }
}
