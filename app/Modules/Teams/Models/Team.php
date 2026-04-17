<?php

namespace App\Modules\Teams\Models;

use App\Modules\Matches\Models\Pool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Team extends Model
{
    use LogsActivity;

    protected $table = 'teams';

    protected static function booted(): void
    {
        static::creating(function (Team $team) {
            if ($team->pool_id === null && $team->league_id) {
                $firstPool = Pool::where('league_id', $team->league_id)->orderBy('id')->first();
                if ($firstPool) {
                    $team->pool_id = $firstPool->id;
                }
            }
        });
    }

    protected $fillable = [
        'name',
        'showdown_username',
        'logo',
        'league_id',
        'user_id',
        'pick_position',
        'trades',
        'draft_points',
        'victory_points',
        'admin_flag',
        'set_wins',
        'set_losses',
        'game_wins',
        'game_losses',
        'seed',
        'pool_id',
        'medal_placement',
        'dropped_at',
        'created_at',
        'updated_at',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['draft_points', 'trades', 'dropped_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dropped_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeNotDropped(Builder $query): Builder
    {
        return $query->whereNull('dropped_at');
    }

    public function league()
    {
        return $this->belongsTo(\App\Modules\League\Models\League::class, 'league_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    /**
     * Showdown name for replay matching: team-specific override, otherwise the coach's profile value.
     */
    public function effectiveShowdownUsername(): ?string
    {
        if (is_string($this->showdown_username) && trim($this->showdown_username) !== '') {
            return trim($this->showdown_username);
        }

        $fromUser = $this->user?->showdown_username;
        if (is_string($fromUser) && trim($fromUser) !== '') {
            return trim($fromUser);
        }

        return null;
    }

    public function draftPicks()
    {
        return $this->hasMany(\App\Modules\Draft\Models\DraftPick::class, 'team_id');
    }

    public function draftWishlistItems()
    {
        return $this->hasMany(\App\Modules\Draft\Models\DraftWishlistItem::class, 'team_id');
    }

    public function pokemon()
    {
        return $this->hasMany(\App\Modules\League\Models\LeaguePokemon::class, 'drafted_by', 'id')->orderBy('cost', 'desc');
    }
}
