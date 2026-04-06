<?php

namespace App\Modules\Matches\Controllers;

use App\Events\MatchScheduleRequestUpdatedEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Match\ProposeMatchScheduleRequest;
use App\Http\Requests\Match\RespondToMatchScheduleRequest;
use App\Models\User;
use App\Modules\League\Models\League;
use App\Modules\Matches\Enums\ScheduleRequestStatus;
use App\Modules\Matches\Models\MatchScheduleRequest;
use App\Modules\Matches\Models\Set;
use App\Notifications\MatchScheduleRequestNotification;
use Illuminate\Http\RedirectResponse;

class MatchScheduleRequestController extends Controller
{
    public function store(ProposeMatchScheduleRequest $request, int $set): RedirectResponse
    {
        $setModel = Set::query()->findOrFail($set);

        MatchScheduleRequest::query()
            ->where('set_id', $setModel->id)
            ->where('status', ScheduleRequestStatus::Pending->value)
            ->update(['status' => ScheduleRequestStatus::Declined->value]);

        $scheduleRequest = MatchScheduleRequest::query()->create([
            'set_id' => $setModel->id,
            'proposed_by_user_id' => $request->user()->id,
            'proposed_at' => $request->validated('proposed_at'),
            'status' => ScheduleRequestStatus::Pending->value,
        ]);

        $scheduleRequest->load('proposedBy:id,name');

        MatchScheduleRequestUpdatedEvent::dispatch([
            'id' => $scheduleRequest->id,
            'set_id' => $scheduleRequest->set_id,
            'proposed_by_user_id' => $scheduleRequest->proposed_by_user_id,
            'proposed_by_user_name' => $scheduleRequest->proposedBy->name,
            'proposed_at' => $scheduleRequest->proposed_at->toISOString(),
            'status' => $scheduleRequest->status->value,
        ]);

        $this->notifyOpponentViaDiscord($setModel, $scheduleRequest, $request->user());

        return redirect()->back();
    }

    private function notifyOpponentViaDiscord(Set $set, MatchScheduleRequest $scheduleRequest, User $proposer): void
    {
        $league = League::query()->find($set->league_id);

        if ($league === null || $league->routeNotificationFor('discord') === null) {
            return;
        }

        $set->load(['team1.user', 'team2.user']);

        $opponentUser = $set->team1->user_id === $proposer->id
            ? $set->team2->user
            : $set->team1->user;

        if (! $opponentUser instanceof User) {
            return;
        }

        $league->notify(new MatchScheduleRequestNotification(
            league: $league,
            set: $set,
            scheduleRequest: $scheduleRequest,
            proposer: $proposer,
            opponent: $opponentUser,
        ));
    }

    public function update(RespondToMatchScheduleRequest $request, int $set, int $scheduleRequest): RedirectResponse
    {
        $scheduleRequestModel = MatchScheduleRequest::query()
            ->where('set_id', $set)
            ->findOrFail($scheduleRequest);

        $status = ScheduleRequestStatus::from($request->validated('status'));
        $scheduleRequestModel->update(['status' => $status->value]);

        if ($status === ScheduleRequestStatus::Accepted) {
            Set::query()
                ->where('id', $set)
                ->update(['scheduled_at' => $scheduleRequestModel->proposed_at]);
        }

        $scheduleRequestModel->load('proposedBy:id,name');

        MatchScheduleRequestUpdatedEvent::dispatch([
            'id' => $scheduleRequestModel->id,
            'set_id' => $scheduleRequestModel->set_id,
            'proposed_by_user_id' => $scheduleRequestModel->proposed_by_user_id,
            'proposed_by_user_name' => $scheduleRequestModel->proposedBy->name,
            'proposed_at' => $scheduleRequestModel->proposed_at->toISOString(),
            'status' => $scheduleRequestModel->status->value,
        ]);

        return redirect()->back();
    }
}
