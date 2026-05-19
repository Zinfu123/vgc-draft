<?php

namespace App\Console\Commands;

use App\Modules\Draft\Actions\StartDraftAction;
use App\Modules\Draft\Models\Draft;
use App\Modules\Draft\Models\DraftConfig;
use App\Modules\League\Models\League;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class StartScheduledDraftsCommand extends Command
{
    protected $signature = 'draft:start-scheduled';

    protected $description = 'Start any drafts whose scheduled start time has arrived.';

    public function __construct(
        private readonly StartDraftAction $startDraftAction,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $due = DraftConfig::query()
            ->whereNotNull('draft_start_at')
            ->where('draft_start_at', '<=', Carbon::now())
            ->get();

        if ($due->isEmpty()) {
            $this->info('No scheduled drafts are due.');

            return self::SUCCESS;
        }

        foreach ($due as $config) {
            $leagueId = $config->league_id;

            if (Draft::where('league_id', $leagueId)->exists()) {
                $this->line("League {$leagueId}: draft already started, skipping.");

                continue;
            }

            $league = League::query()->find($leagueId);

            if ($league === null) {
                $this->warn("League {$leagueId}: not found, skipping.");

                continue;
            }

            $started = ($this->startDraftAction)($leagueId);

            if ($started) {
                $this->info("League {$leagueId} ({$league->name}): draft started.");
            } else {
                $this->line("League {$leagueId}: draft could not be started, skipping.");
            }
        }

        return self::SUCCESS;
    }
}
