<?php

namespace App\Console\Commands;

use App\Events\DraftDetailEvent;
use App\Modules\Draft\Actions\DraftTimerAction;
use App\Modules\Draft\Actions\RemindCurrentPickerAction;
use App\Modules\Draft\Actions\SkipCurrentTurnAction;
use App\Modules\Draft\Models\Draft;
use App\Modules\Draft\Models\DraftConfig;
use App\Modules\Draft\Models\DraftReminder;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TickDraftTimersCommand extends Command
{
    protected $signature = 'draft:tick-timers';

    protected $description = 'Advance or expire draft pick timers; shield active turns during quiet hours; deliver due reminder notifications.';

    public function __construct(
        private readonly DraftTimerAction $draftTimerAction,
        private readonly SkipCurrentTurnAction $skipCurrentTurnAction,
        private readonly RemindCurrentPickerAction $remindCurrentPickerAction,
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

                DraftDetailEvent::dispatch([
                    'league_id' => $draft->league_id,
                    'end_draft' => 0,
                ]);

                continue;
            }

            if ($draft->current_deadline_at !== null && $draft->current_deadline_at->lte($now)) {
                ($this->skipCurrentTurnAction)([
                    'league_id' => $draft->league_id,
                    'reason' => 'timer_expired',
                ]);

                continue;
            }

            $this->processDueReminders($draft, $now);
        }

        return self::SUCCESS;
    }

    /**
     * Mark each due reminder sent atomically before dispatching, so a failed Discord call
     * never causes the same reminder to fire repeatedly. The keep-most-urgent rule means
     * a single tick will only ever ping the smallest applicable threshold for a draft —
     * older overdue reminders are auto-cancelled.
     */
    private function processDueReminders(Draft $draft, CarbonImmutable $now): void
    {
        $dueReminders = DraftReminder::query()
            ->where('draft_id', $draft->id)
            ->whereNull('sent_at')
            ->whereNull('cancelled_at')
            ->where('fire_at', '<=', $now)
            ->orderBy('threshold_seconds')
            ->get();

        if ($dueReminders->isEmpty()) {
            return;
        }

        $toSend = $dueReminders->first();
        $stale = $dueReminders->slice(1);

        $claimed = DB::table('draft_reminders')
            ->where('id', $toSend->id)
            ->whereNull('sent_at')
            ->whereNull('cancelled_at')
            ->update(['sent_at' => Carbon::now(), 'updated_at' => Carbon::now()]);

        if ($claimed === 0) {
            return;
        }

        if ($stale->isNotEmpty()) {
            DraftReminder::query()
                ->whereIn('id', $stale->pluck('id'))
                ->whereNull('sent_at')
                ->whereNull('cancelled_at')
                ->update(['cancelled_at' => Carbon::now()]);
        }

        ($this->remindCurrentPickerAction)([
            'league_id' => (int) $draft->league_id,
            'remaining_seconds' => (int) $toSend->threshold_seconds,
        ]);
    }
}
