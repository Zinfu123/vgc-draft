<?php

namespace App\Modules\League\Models;

use App\Enums\PokemonGame;
use App\Modules\League\Enums\LeagueStagingStatus;
use App\Modules\League\Enums\LeagueStatus;
use App\Modules\Pokedex\Models\VersionGroup;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class League extends Model
{
    use LogsActivity, Notifiable;

    protected $table = 'leagues';

    protected $fillable = [
        'name',
        'status',
        'staging_sub_status',
        'free_trade_window_hours',
        'playoffs_enabled',
        'trade_deadline_at',
        'winner',
        'set_frequency',
        'logo',
        'discord_webhook_url',
        'discord_replay_webhook_url',
        'set_start_date',
        'set_end_date',
        'open',
        'require_showdown_username',
        'maximum_teams',
        'league_owner',
        'pokemon_generation',
        'pokemon_game',
        'created_at',
        'updated_at',
    ];

    protected static function booted(): void
    {
        static::updating(function (League $league): void {
            if ($league->isDirty('status') && $league->status === LeagueStatus::Completed) {
                $league->set_end_date = now()->toDateString();
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'status', 'open', 'winner'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pokemon_generation' => 'integer',
            'pokemon_game' => PokemonGame::class,
            'require_showdown_username' => 'boolean',
            'playoffs_enabled' => 'boolean',
            'free_trade_window_hours' => 'integer',
            'trade_deadline_at' => 'datetime',
            'status' => LeagueStatus::class,
            'staging_sub_status' => LeagueStagingStatus::class,
        ];
    }

    public function routeNotificationForDiscord(): ?string
    {
        return $this->discord_webhook_url ?: null;
    }

    public function routeNotificationForDiscordReplay(): ?string
    {
        return $this->discord_replay_webhook_url ?: $this->discord_webhook_url ?: null;
    }

    /**
     * Whether the trade deadline has passed and trades are locked.
     */
    public function isTradeDeadlinePassed(): bool
    {
        return $this->trade_deadline_at !== null && Carbon::now()->gte($this->trade_deadline_at);
    }

    /**
     * Whether the league is in a running state (Registration through Playoffs).
     */
    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    /**
     * Whether the free trade window is currently open.
     * Requires the league to be in Staging with FreeTradeWindow sub-status,
     * and the window to not have expired yet.
     */
    public function isFreeTradeWindowActive(): bool
    {
        if ($this->status !== LeagueStatus::Staging) {
            return false;
        }

        if ($this->staging_sub_status !== LeagueStagingStatus::FreeTradeWindow) {
            return false;
        }

        $draftEndedAt = $this->draftConfig?->draft_ended_at;

        if ($draftEndedAt === null) {
            return false;
        }

        return Carbon::now()->lt($draftEndedAt->addHours($this->free_trade_window_hours));
    }

    public function teams(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Modules\Teams\Models\Team::class, 'league_id');
    }

    public function draft(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Modules\Draft\Models\Draft::class, 'league_id');
    }

    public function draftConfig(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Modules\Draft\Models\DraftConfig::class, 'league_id');
    }

    public function matchConfig(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Modules\Matches\Models\MatchConfig::class, 'league_id');
    }

    public function playoff(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Modules\Playoffs\Models\Playoff::class, 'league_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Modules\League\Models\LeaguePokemon, $this>
     */
    public function leaguePokemon(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(LeaguePokemon::class, 'league_id');
    }

    public function winnerUser(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'winner');
    }

    public function versionGroup(): ?VersionGroup
    {
        return VersionGroup::query()->where('slug', $this->pokemon_game->versionGroupSlug())->first();
    }
}
