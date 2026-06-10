<?php

namespace App\Modules\Matches\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Match\RespondMatchScheduleRequestRequest;
use App\Http\Requests\Match\StoreMatchScheduleRequestRequest;
use App\Models\User;
use App\Modules\League\Models\League;
use App\Modules\Matches\Enums\ScheduleRequestStatus;
use App\Modules\Matches\Models\MatchScheduleRequest;
use App\Modules\Matches\Models\Set;
use App\Notifications\MatchScheduleRequestedNotification;
use App\Notifications\MatchScheduleRespondedNotification;
use Illuminate\Http\RedirectResponse;

class MatchScheduleRequestController extends Controller
{
    public function store(StoreMatchScheduleRequestRequest $request, int $set): RedirectResponse
    {
        $set = Set::query()->with(['team1.user', 'team2.user'])->findOrFail($set);
        $league = League::query()->find($set->league_id);
        $user = $request->user();

        // Cancel any existing pending request for this set
        MatchScheduleRequest::query()
            ->where('set_id', $set->id)
            ->where('status', ScheduleRequestStatus::Pending->value)
            ->update(['status' => ScheduleRequestStatus::Declined->value]);

        $scheduleRequest = MatchScheduleRequest::query()->create([
            'set_id' => $set->id,
            'proposed_by_user_id' => $user->id,
            'proposed_at' => $request->validated('proposed_at'),
            'status' => ScheduleRequestStatus::Pending->value,
        ]);

        $scheduleRequest->load('proposedByUser');

        $targetUser = $this->resolveTargetUser($set, $user->id);

        if ($league && $targetUser) {
            $league->notify(new MatchScheduleRequestedNotification($scheduleRequest, $set, $targetUser));
        }

        return redirect()->route('sets.show', ['set_id' => $set->id])
            ->with('success', 'Your time request has been sent.');
    }

    public function respond(RespondMatchScheduleRequestRequest $request, int $scheduleRequest): RedirectResponse
    {
        /** @var MatchScheduleRequest $scheduleRequest */
        $scheduleRequest = MatchScheduleRequest::query()
            ->with(['set.team1.user', 'set.team2.user', 'proposedByUser'])
            ->findOrFail($scheduleRequest);

        $set = $scheduleRequest->set;
        $league = League::query()->find($set->league_id);
        $respondingUser = $request->user();
        $action = $request->validated('action');

        if ($action === 'accept') {
            $scheduleRequest->update(['status' => ScheduleRequestStatus::Accepted->value]);
            $set->update(['scheduled_at' => $scheduleRequest->proposed_at]);

            $otherUser = $this->resolveTargetUser($set, $respondingUser->id);

            if ($league && $otherUser) {
                $league->notify(new MatchScheduleRespondedNotification($scheduleRequest, $set, $respondingUser, $otherUser));
            }

            return redirect()->route('sets.show', ['set_id' => $set->id])
                ->with('success', 'Match time confirmed! It has been added to the calendar.');
        }

        if ($action === 'decline') {
            $scheduleRequest->update(['status' => ScheduleRequestStatus::Declined->value]);

            $otherUser = $this->resolveTargetUser($set, $respondingUser->id);

            if ($league && $otherUser) {
                $league->notify(new MatchScheduleRespondedNotification($scheduleRequest, $set, $respondingUser, $otherUser));
            }

            return redirect()->route('sets.show', ['set_id' => $set->id])
                ->with('success', 'Time request declined.');
        }

        if ($action === 'cancel') {
            $scheduleRequest->update(['status' => ScheduleRequestStatus::Declined->value]);

            return redirect()->route('sets.show', ['set_id' => $set->id])
                ->with('success', 'Time request cancelled.');
        }

        // Reschedule: mark current request declined, create new one from the responding user
        $scheduleRequest->update(['status' => ScheduleRequestStatus::Declined->value]);

        $newRequest = MatchScheduleRequest::query()->create([
            'set_id' => $set->id,
            'proposed_by_user_id' => $respondingUser->id,
            'proposed_at' => $request->validated('proposed_at'),
            'status' => ScheduleRequestStatus::Pending->value,
        ]);

        $newRequest->load('proposedByUser');

        $otherUser = $this->resolveTargetUser($set, $respondingUser->id);

        if ($league && $otherUser) {
            $newRequest->status = ScheduleRequestStatus::Reschedule;
            $league->notify(new MatchScheduleRespondedNotification($newRequest, $set, $respondingUser, $otherUser));
            $newRequest->status = ScheduleRequestStatus::Pending;
        }

        return redirect()->route('sets.show', ['set_id' => $set->id])
            ->with('success', 'New time proposed. Waiting for your opponent to respond.');
    }

    private function resolveTargetUser(Set $set, int $currentUserId): ?User
    {
        $team1User = $set->team1?->user;
        $team2User = $set->team2?->user;

        if ($team1User && $team1User->id !== $currentUserId) {
            return $team1User;
        }

        if ($team2User && $team2User->id !== $currentUserId) {
            return $team2User;
        }

        return null;
    }
}
