<?php

namespace App\Console\Commands;

use App\Modules\Draft\Actions\DraftTimerAction;
use App\Modules\Draft\Actions\SkipCurrentTurnAction;
use App\Modules\Draft\Models\Draft;
use App\Modules\Draft\Models\DraftConfig;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class TickDraftTimersCommand extends Command
{
    protected $signature = 'draft:tick-timers';

    protected $description = 'Advance or expire draft pick timers; shield active turns during quiet hours.';

    public function __construct(
        private readonly DraftTimerAction $draftTimerAction,
        private readonly SkipCurrentTurnAction $skipCurrentTurnAction,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $activeDrafts = Draft::query()->whereIn('status', [1, 2])->get();
        if ($activeDrafts->isEmpty()) {
            return self::SUCCESS;
        }

        $configsByLeague = DraftConfig::query()
            ->whereIn('league_id', $activeDrafts->pluck('league_id'))
            ->get()
            ->keyBy('league_id');

        $now = CarbonImmutable::now();

        foreach ($activeDrafts as $draft) {
            $config = $configsByLeague->get($draft->league_id);
            if ($config === null || ! $config->pick_timer_enabled) {
                continue;
            }

            if ($draft->paused_at !== null) {
                continue;
            }

            if ($this->draftTimerAction->isInQuietHours($config, $now)) {
                ($this->draftTimerAction)([
                    'league_id' => $draft->league_id,
                    'command' => DraftTimerAction::COMMAND_SHIELD_QUIET_HOURS,
                ]);

                continue;
            }

            if ($draft->current_deadline_at !== null && $draft->current_deadline_at->lte($now)) {
                ($this->skipCurrentTurnAction)([
                    'league_id' => $draft->league_id,
                    'reason' => 'timer_expired',
                ]);
            }
        }

        return self::SUCCESS;
    }
}
