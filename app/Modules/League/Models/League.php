<?php

namespace App\Modules\League\Models;

use App\Enums\PokemonGame;
use App\Modules\Pokedex\Models\VersionGroup;
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
        'winner',
        'set_frequency',
        'logo',
        'discord_webhook_url',
        'discord_replay_webhook_url',
        'set_start_date',
        'open',
        'maximum_teams',
        'league_owner',
        'pokemon_generation',
        'pokemon_game',
        'created_at',
        'updated_at',
    ];

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

    public function teams(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Modules\Teams\Models\Team::class, 'league_id');
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
