<?php

namespace App\Modules\Draft\Actions;

use App\Events\DraftDetailEvent;
use App\Modules\Draft\Models\BanOrder;
use App\Modules\Draft\Models\Draft;
use App\Modules\Draft\Models\DraftOrder;

class SkipCurrentTurnAction
{
    public function __construct(
        private readonly DraftTimerAction $draftTimerAction,
        private readonly NotifyDraftNextTurnAction $notifyDraftNextTurnAction,
    ) {}

    /**
     * Advance past the current picker/banner without making a pick/ban.
     *
     * @param  array{league_id:int, reason?:string}  $data
     */
    public function __invoke(array $data): void
    {
        $leagueId = (int) $data['league_id'];
        $reason = (string) ($data['reason'] ?? 'timer_expired');

        $draft = Draft::query()->where('league_id', $leagueId)->first();
        if ($draft === null || (int) $draft->status === 0) {
            return;
        }

        $status = (int) $draft->status;

        if ($status === 2) {
            $this->skipBanPhase($draft, $leagueId, $reason);
        } else {
            $this->skipDraftPhase($draft, $leagueId, $reason);
        }

        DraftDetailEvent::dispatch([
            'league_id' => $leagueId,
            'end_draft' => 0,
        ]);

        ($this->notifyDraftNextTurnAction)(['league_id' => $leagueId]);
    }

    private function skipBanPhase(Draft $draft, int $leagueId, string $reason): void
    {
        $currentBanOrder = BanOrder::query()
            ->where('league_id', $leagueId)
            ->where('status', 1)
            ->orderBy('round_number')
            ->orderBy('ban_number')
            ->first();

        if ($currentBanOrder === null) {
            return;
        }

        $currentBanOrder->status = 0;
        $currentBanOrder->save();

        activity()
            ->performedOn($currentBanOrder)
            ->withProperties([
                'league_id' => $leagueId,
                'team_id' => $currentBanOrder->team_id,
                'reason' => $reason,
            ])
            ->log('Draft ban auto-skipped (timer expired)');

        $pendingBans = BanOrder::query()
            ->where('league_id', $leagueId)
            ->where('status', 1)
            ->count();

        if ($pendingBans === 0) {
            $draft->status = 1;
            $draft->save();

            (new CreateEditDraftOrderAction)(['league_id' => $leagueId]);
        }

        ($this->draftTimerAction)(['league_id' => $leagueId, 'command' => DraftTimerAction::COMMAND_START_TURN]);
    }

    private function skipDraftPhase(Draft $draft, int $leagueId, string $reason): void
    {
        $currentOrder = DraftOrder::query()
            ->where('league_id', $leagueId)
            ->where('status', 1)
            ->orderBy('pick_number')
            ->first();

        if ($currentOrder === null) {
            return;
        }

        $currentOrder->status = 0;
        $currentOrder->save();

        activity()
            ->performedOn($currentOrder)
            ->withProperties([
                'league_id' => $leagueId,
                'team_id' => $currentOrder->team_id,
                'reason' => $reason,
            ])
            ->log('Draft pick auto-skipped (timer expired)');

        $draft->pick_number = (int) $draft->pick_number + 1;
        $draft->save();

        if ((int) $currentOrder->is_last_pick === 1) {
            $draft->round_number = (int) $draft->round_number + 1;
            $draft->pick_number = 1;
            $draft->save();

            (new CreateEditDraftOrderAction)(['league_id' => $leagueId]);
        }

        $draft->refresh();
        if ((int) $draft->status === 0) {
            ($this->draftTimerAction)(['league_id' => $leagueId, 'command' => DraftTimerAction::COMMAND_CLEAR]);

            return;
        }

        ($this->draftTimerAction)(['league_id' => $leagueId, 'command' => DraftTimerAction::COMMAND_START_TURN]);
    }
}
