<?php

namespace App\Jobs;

use App\Modules\League\Models\League;
use App\Modules\Trade\Models\Trade;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

class EnforceTradeDeadlineJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $leagueId,
        public readonly Carbon $scheduledDeadline,
    ) {}

    public function handle(): void
    {
        $league = League::query()->find($this->leagueId);

        if ($league === null) {
            return;
        }

        // If the deadline was changed after this job was dispatched, skip — a
        // newer job will have been dispatched for the updated deadline.
        if ($league->trade_deadline_at === null || ! $league->trade_deadline_at->eq($this->scheduledDeadline)) {
            return;
        }

        Trade::query()
            ->where('league_id', $this->leagueId)
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);
    }
}
