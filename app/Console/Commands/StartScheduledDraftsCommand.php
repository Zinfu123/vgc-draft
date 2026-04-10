<?php

namespace App\Console\Commands;

use App\Modules\Draft\Actions\CreateEditDraftAction;
use App\Modules\Draft\Actions\CreateEditDraftOrderAction;
use App\Modules\Draft\Models\Draft;
use App\Modules\Draft\Models\DraftConfig;
use App\Modules\League\Models\League;
use App\Notifications\DraftStartedBroadcastNotification;
use App\Notifications\DraftStartedNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class StartScheduledDraftsCommand extends Command
{
    protected $signature = 'draft:start-scheduled';

    protected $description = 'Start any drafts whose scheduled start time has arrived.';

    public function __construct(
        private readonly CreateEditDraftAction $createEditDraftAction,
        private readonly CreateEditDraftOrderAction $createEditDraftOrderAction,
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

            $league = League::with('draftConfig')->find($leagueId);

            if ($league === null) {
                $this->warn("League {$leagueId}: not found, skipping.");

                continue;
            }

            ($this->createEditDraftAction)(['league_id' => $leagueId, 'command' => 'create']);

            if ($league->draftConfig->ban_enabled) {
                ($this->createEditDraftAction)(['league_id' => $leagueId, 'command' => 'create_ban']);
                ($this->createEditDraftOrderAction)(['league_id' => $leagueId, 'command' => 'create_ban_order']);
            } else {
                ($this->createEditDraftOrderAction)(['league_id' => $leagueId]);
            }

            $league->notify(new DraftStartedNotification($league));

            $league->load('teams.user');
            foreach ($league->teams as $team) {
                if ($team->user !== null) {
                    $team->user->notifyNow(new DraftStartedBroadcastNotification($league));
                }
            }

            $this->info("League {$leagueId} ({$league->name}): draft started.");
        }

        return self::SUCCESS;
    }
}
