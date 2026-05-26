<?php

namespace App\Console\Commands;

use App\Modules\League\Actions\StartRegularSeasonAction;
use App\Modules\League\Enums\LeagueStatus;
use App\Modules\League\Models\League;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class StartScheduledRegularSeasonsCommand extends Command
{
    protected $signature = 'leagues:start-scheduled-regular-season';

    protected $description = 'Move leagues from Staging to Regular Season when their season start date has arrived.';

    public function __construct(
        private readonly StartRegularSeasonAction $startRegularSeasonAction,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $today = Carbon::today()->toDateString();

        $due = League::query()
            ->where('status', LeagueStatus::Staging->value)
            ->whereNotNull('set_start_date')
            ->whereDate('set_start_date', '<=', $today)
            ->get();

        if ($due->isEmpty()) {
            $this->info('No leagues are due to start their regular season.');

            return self::SUCCESS;
        }

        foreach ($due as $league) {
            $started = ($this->startRegularSeasonAction)($league);

            if ($started) {
                $this->info("League {$league->id} ({$league->name}): regular season started.");

                continue;
            }

            $this->line("League {$league->id} ({$league->name}): could not start regular season yet, skipping.");
        }

        return self::SUCCESS;
    }
}
