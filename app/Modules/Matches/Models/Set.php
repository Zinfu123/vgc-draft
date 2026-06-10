<?php

namespace App\Modules\Matches\Models;

use App\Modules\League\Models\League;
use App\Modules\Pokepaste\Models\SetTeamPokepaste;
use App\Modules\Teams\Models\Team;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Set extends Model
{
    use LogsActivity;

    protected $table = 'sets';

    protected $fillable = [
        'pool_id',
        'league_id',
        'team1_id',
        'team2_id',
        'team1_score',
        'team2_score',
        'winner_id',
        'team1_pokepaste',
        'team2_pokepaste',
        'replay1',
        'replay2',
        'replay3',
        'round',
        'status',
        'is_bye',
        'scheduled_at',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'team1_score', 'team2_score', 'winner_id', 'scheduled_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_bye' => 'boolean',
            'scheduled_at' => 'datetime',
        ];
    }

    public function team1()
    {
        return $this->belongsTo(Team::class, 'team1_id', 'id')->select('id', 'name', 'logo', 'user_id', 'showdown_username')->with('user:id,name,showdown_username');
    }

    public function team2()
    {
        return $this->belongsTo(Team::class, 'team2_id', 'id')->select('id', 'name', 'logo', 'user_id', 'showdown_username')->with('user:id,name,showdown_username');
    }

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class, 'league_id');
    }

    public function setTeamPokepastes(): MorphMany
    {
        return $this->morphMany(SetTeamPokepaste::class, 'matchable');
    }

    public function matchMessages(): HasMany
    {
        return $this->hasMany(MatchMessage::class, 'set_id');
    }

    public function scheduleRequests(): HasMany
    {
        return $this->hasMany(MatchScheduleRequest::class, 'set_id');
    }

    public function isComplete(): bool
    {
        return $this->winner_id !== null || (int) $this->status === 0;
    }
}
