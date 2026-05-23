<?php

namespace App\Modules\Trade\Models;

use App\Enums\Trade\TradeCounterparty;
use App\Events\LeagueTransactionEvent;
use App\Events\TradePendingEvent;
use App\Modules\League\Models\League;
use App\Modules\Teams\Models\Team;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Trade extends Model
{
    use LogsActivity;

    protected $table = 'trades';

    protected $fillable = [
        'league_id',
        'requesting_team_id',
        'target_team_id',
        'counterparty',
        'status',
    ];

    protected static function booted(): void
    {
        static::created(function (Trade $trade): void {
            self::afterDatabaseCommit(function () use ($trade): void {
                if ($trade->target_team_id !== null) {
                    TradePendingEvent::dispatch($trade->target_team_id);
                }

                if ($trade->status === 'accepted') {
                    LeagueTransactionEvent::dispatch($trade->league_id);
                }
            });
        });

        static::updated(function (Trade $trade): void {
            if ($trade->wasChanged('status') && $trade->status === 'accepted') {
                self::afterDatabaseCommit(function () use ($trade): void {
                    LeagueTransactionEvent::dispatch($trade->league_id);
                });
            }
        });
    }

    private static function afterDatabaseCommit(callable $callback): void
    {
        $run = function () use ($callback): void {
            try {
                $callback();
            } catch (BroadcastException $exception) {
                report($exception);
            }
        };

        if (DB::transactionLevel() > 0) {
            DB::afterCommit($run);

            return;
        }

        $run();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'counterparty'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'counterparty' => TradeCounterparty::class,
        ];
    }

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class, 'league_id');
    }

    public function requestingTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'requesting_team_id');
    }

    public function targetTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'target_team_id');
    }

    public function tradePokemon(): HasMany
    {
        return $this->hasMany(TradePokemon::class, 'trade_id');
    }

    public function offeredPokemon(): HasMany
    {
        return $this->hasMany(TradePokemon::class, 'trade_id')->where('direction', 'offered');
    }

    public function requestedPokemon(): HasMany
    {
        return $this->hasMany(TradePokemon::class, 'trade_id')->where('direction', 'requested');
    }
}
