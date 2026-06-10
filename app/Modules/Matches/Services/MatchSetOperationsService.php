<?php

namespace App\Modules\Matches\Services;

use App\Events\MatchMessageSentEvent;
use App\Kernel\Contracts\MatchSetOperations;
use App\Models\User;
use App\Modules\League\Models\League;
use App\Modules\Matches\Actions\CreateEditSetsAction;
use App\Modules\Matches\Actions\ShowSetsAction;
use App\Modules\Matches\Enums\ScheduleRequestStatus;
use App\Modules\Matches\Models\MatchMessage;
use App\Modules\Matches\Models\MatchScheduleRequest;
use App\Modules\Matches\Models\Set;
use App\Modules\Pokepaste\Actions\ImportSetTeamsFromShowdownReplayAction;
use App\Modules\Pokepaste\Actions\ReadMatchPokepastePayloadAction;
use App\Modules\Pokepaste\Actions\ReadMatchPokepasteSideSummariesAction;
use App\Modules\Pokepaste\Services\ShowdownReplayLogFetcher;
use App\Modules\Pokepaste\Services\ShowdownReplayLogUrl;
use App\Modules\Pokepaste\Services\ShowdownReplayPlayerNamesParser;
use App\Modules\Pokepaste\Services\SuggestP1TeamFromShowdownReplay;
use App\Modules\Teams\Models\Team;
use App\Notifications\MatchScheduleRequestedNotification;
use App\Notifications\MatchScheduleRespondedNotification;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class MatchSetOperationsService implements MatchSetOperations
{
    public function __construct(
        private CreateEditSetsAction $createEditSetsAction,
        private ShowSetsAction $showSetsAction,
        private ReadMatchPokepastePayloadAction $readMatchPokepastePayloadAction,
        private ReadMatchPokepasteSideSummariesAction $readMatchPokepasteSideSummariesAction,
        private ShowdownReplayLogFetcher $logFetcher,
        private ShowdownReplayPlayerNamesParser $playerNamesParser,
        private SuggestP1TeamFromShowdownReplay $suggestP1TeamFromShowdownReplay,
        private ImportSetTeamsFromShowdownReplayAction $importSetTeamsFromShowdownReplayAction,
    ) {}

    public function showPageProps(int $setId, int $userId): ?array
    {
        $set = ($this->showSetsAction)(['set_id' => $setId, 'command' => 'detail']);
        if (! $set) {
            return null;
        }

        $currentUserTeam = Team::query()
            ->where('user_id', $userId)
            ->where('league_id', $set->league_id)
            ->first();

        $isTeam1 = $currentUserTeam !== null && $currentUserTeam->id === $set->team1_id;
        $isTeam2 = $currentUserTeam !== null && $currentUserTeam->id === $set->team2_id;
        if ($isTeam1 && ! $isTeam2) {
            $set->setAttribute('team2_pokepaste', null);
        } elseif ($isTeam2 && ! $isTeam1) {
            $set->setAttribute('team1_pokepaste', null);
        } else {
            $set->setAttribute('team1_pokepaste', null);
            $set->setAttribute('team2_pokepaste', null);
        }

        $matchPokepaste = null;
        if ($currentUserTeam !== null
            && ($currentUserTeam->id === $set->team1_id || $currentUserTeam->id === $set->team2_id)) {
            $matchPokepaste = ($this->readMatchPokepastePayloadAction)($set, $currentUserTeam);
        }

        $league = League::query()->with('matchConfig')->find($set->league_id);
        $user = User::query()->find($userId);
        $isLeagueAdmin = $league !== null
            && $user !== null
            && $user->can('admin', $league);

        $adminMatchPokepastes = null;
        if ($isLeagueAdmin && (int) $set->status !== 0) {
            $set->loadMissing(['team1', 'team2']);
            if ($set->team1 !== null && $set->team2 !== null) {
                $adminMatchPokepastes = [
                    'team1' => ($this->readMatchPokepastePayloadAction)($set, $set->team1),
                    'team2' => ($this->readMatchPokepastePayloadAction)($set, $set->team2),
                ];
            }
        }

        $isParticipant = $currentUserTeam !== null
            && ($currentUserTeam->id === $set->team1_id || $currentUserTeam->id === $set->team2_id);

        return [
            'set' => fn () => $set,
            'currentUserTeam' => fn () => $currentUserTeam,
            'matchPokepaste' => fn () => $matchPokepaste,
            'adminMatchPokepastes' => fn () => $adminMatchPokepastes,
            'matchPokepasteSides' => fn () => ($this->readMatchPokepasteSideSummariesAction)($set),
            'isLeagueAdmin' => fn () => $isLeagueAdmin,
            'requireTeamMatchPokepasteBeforeResults' => fn () => (bool) ($league?->matchConfig?->require_team_match_pokepaste_before_results ?? false),
            'requireReplaysBeforeResults' => fn () => (bool) ($league?->matchConfig?->require_replays_before_results ?? false),
            'autoCompleteFromReplays' => fn () => (bool) ($league?->matchConfig?->auto_complete_set_from_replays ?? false),
            'matchMessages' => Inertia::defer(function () use ($set): array {
                return MatchMessage::query()
                    ->where('set_id', $set->id)
                    ->with('user:id,name')
                    ->orderBy('created_at')
                    ->get()
                    ->map(fn (MatchMessage $msg) => [
                        'id' => $msg->id,
                        'set_id' => $msg->set_id,
                        'user_id' => $msg->user_id,
                        'user_name' => $msg->user->name,
                        'body' => $msg->body,
                        'is_read' => $msg->is_read,
                        'created_at' => $msg->created_at?->toISOString(),
                    ])
                    ->all();
            }),
            'isParticipant' => fn () => $isParticipant,
            'pendingScheduleRequest' => Inertia::defer(function () use ($set, $user): ?array {
                $request = MatchScheduleRequest::query()
                    ->where('set_id', $set->id)
                    ->where('status', ScheduleRequestStatus::Pending->value)
                    ->latest()
                    ->first();

                if ($request === null) {
                    return null;
                }

                return [
                    'id' => $request->id,
                    'proposed_at' => $request->proposed_at?->toISOString(),
                    'proposed_by_user_id' => $request->proposed_by_user_id,
                    'status' => $request->status->value,
                    'is_mine' => $user !== null && $request->proposed_by_user_id === $user->id,
                ];
            }),
        ];
    }

    public function createSetsForLeague(int $leagueId): int
    {
        ($this->createEditSetsAction)(['league_id' => $leagueId, 'command' => 'create']);

        return $leagueId;
    }

    public function updateSet(array $validated): array
    {
        $result = ($this->createEditSetsAction)($validated);

        return [
            'set_id' => (int) $validated['set_id'],
            'success' => $result === true,
        ];
    }

    public function updateReplays(array $data): int
    {
        ($this->createEditSetsAction)([
            'command' => 'updateReplays',
            'set_id' => $data['set_id'],
            'replay1' => $data['replay1'] ?? null,
            'replay2' => $data['replay2'] ?? null,
            'replay3' => $data['replay3'] ?? null,
        ]);

        return (int) $data['set_id'];
    }

    public function previewReplayPlayers(array $validated): array
    {
        $set = Set::query()->with(['team1.user', 'team2.user'])->findOrFail($validated['set_id']);
        $slot = (int) $validated['replay_slot'];
        $replayUrl = match ($slot) {
            1 => $set->replay1,
            2 => $set->replay2,
            3 => $set->replay3,
            default => null,
        };

        try {
            $logUrl = ShowdownReplayLogUrl::resolveLogDownloadUrl((string) $replayUrl);
            $logText = $this->logFetcher->fetch($logUrl);
        } catch (\Throwable $e) {
            return [
                'status' => 422,
                'body' => [
                    'ok' => false,
                    'message' => $e->getMessage(),
                ],
            ];
        }

        $parsed = $this->playerNamesParser->parse($logText);
        if ($parsed['errors'] !== []) {
            return [
                'status' => 422,
                'body' => [
                    'ok' => false,
                    'errors' => $parsed['errors'],
                ],
            ];
        }

        $suggested = $this->suggestP1TeamFromShowdownReplay->suggest($set, $parsed['p1'], $parsed['p2']);

        return [
            'status' => 200,
            'body' => [
                'ok' => true,
                'p1_name' => $parsed['p1'],
                'p2_name' => $parsed['p2'],
                'suggested_p1_team_id' => $suggested,
                'needs_manual_p1_map' => $suggested === null,
            ],
        ];
    }

    public function importReplayTeams(int $setId, int $replaySlot, int $p1TeamId): RedirectResponse
    {
        $set = Set::query()->findOrFail($setId);

        return ($this->importSetTeamsFromShowdownReplayAction)($set, $replaySlot, $p1TeamId);
    }

    public function listMessages(int $setId): array
    {
        Set::query()->findOrFail($setId);

        return MatchMessage::query()
            ->where('set_id', $setId)
            ->with('user:id,name')
            ->orderBy('created_at')
            ->get()
            ->map(fn (MatchMessage $message) => [
                'id' => $message->id,
                'set_id' => $message->set_id,
                'user_id' => $message->user_id,
                'user_name' => $message->user->name,
                'body' => $message->body,
                'is_read' => $message->is_read,
                'created_at' => $message->created_at?->toISOString(),
            ])
            ->all();
    }

    public function storeMessage(int $setId, int $userId, string $body): void
    {
        $set = Set::query()->findOrFail($setId);

        $message = MatchMessage::query()->create([
            'set_id' => $set->id,
            'user_id' => $userId,
            'body' => $body,
            'is_read' => false,
        ]);

        $message->load('user:id,name');

        MatchMessageSentEvent::dispatch([
            'id' => $message->id,
            'set_id' => $message->set_id,
            'user_id' => $message->user_id,
            'user_name' => $message->user->name,
            'body' => $message->body,
            'is_read' => false,
            'created_at' => $message->created_at?->toISOString(),
        ]);
    }

    public function markMessagesRead(int $setId, int $userId): array
    {
        Set::query()->findOrFail($setId);

        MatchMessage::query()
            ->where('set_id', $setId)
            ->where('user_id', '!=', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return ['ok' => true];
    }

    public function storeScheduleRequest(int $setId, int $userId, string $proposedAt): array
    {
        $set = Set::query()->with(['team1.user', 'team2.user'])->findOrFail($setId);
        $league = League::query()->find($set->league_id);

        MatchScheduleRequest::query()
            ->where('set_id', $set->id)
            ->where('status', ScheduleRequestStatus::Pending->value)
            ->update(['status' => ScheduleRequestStatus::Declined->value]);

        $scheduleRequest = MatchScheduleRequest::query()->create([
            'set_id' => $set->id,
            'proposed_by_user_id' => $userId,
            'proposed_at' => $proposedAt,
            'status' => ScheduleRequestStatus::Pending->value,
        ]);

        $scheduleRequest->load('proposedByUser');

        $targetUser = $this->resolveTargetUser($set, $userId);

        if ($league && $targetUser) {
            $league->notify(new MatchScheduleRequestedNotification($scheduleRequest, $set, $targetUser));
        }

        return [
            'set_id' => (int) $set->id,
            'flash' => ['success' => 'Your time request has been sent.'],
        ];
    }

    public function respondScheduleRequest(int $scheduleRequestId, int $userId, array $validated): array
    {
        /** @var MatchScheduleRequest $scheduleRequest */
        $scheduleRequest = MatchScheduleRequest::query()
            ->with(['set.team1.user', 'set.team2.user', 'proposedByUser'])
            ->findOrFail($scheduleRequestId);

        $set = $scheduleRequest->set;
        $league = League::query()->find($set->league_id);
        $action = $validated['action'];

        if ($action === 'accept') {
            $scheduleRequest->update(['status' => ScheduleRequestStatus::Accepted->value]);
            $set->update(['scheduled_at' => $scheduleRequest->proposed_at]);

            $otherUser = $this->resolveTargetUser($set, $userId);

            if ($league && $otherUser) {
                $league->notify(new MatchScheduleRespondedNotification($scheduleRequest, $set, User::query()->findOrFail($userId), $otherUser));
            }

            return [
                'set_id' => (int) $set->id,
                'flash' => ['success' => 'Match time confirmed! It has been added to the calendar.'],
            ];
        }

        if ($action === 'decline') {
            $scheduleRequest->update(['status' => ScheduleRequestStatus::Declined->value]);

            $otherUser = $this->resolveTargetUser($set, $userId);

            if ($league && $otherUser) {
                $league->notify(new MatchScheduleRespondedNotification($scheduleRequest, $set, User::query()->findOrFail($userId), $otherUser));
            }

            return [
                'set_id' => (int) $set->id,
                'flash' => ['success' => 'Time request declined.'],
            ];
        }

        if ($action === 'cancel') {
            $scheduleRequest->update(['status' => ScheduleRequestStatus::Declined->value]);

            return [
                'set_id' => (int) $set->id,
                'flash' => ['success' => 'Time request cancelled.'],
            ];
        }

        $scheduleRequest->update(['status' => ScheduleRequestStatus::Declined->value]);

        $newRequest = MatchScheduleRequest::query()->create([
            'set_id' => $set->id,
            'proposed_by_user_id' => $userId,
            'proposed_at' => $validated['proposed_at'],
            'status' => ScheduleRequestStatus::Pending->value,
        ]);

        $newRequest->load('proposedByUser');

        $otherUser = $this->resolveTargetUser($set, $userId);

        if ($league && $otherUser) {
            $newRequest->status = ScheduleRequestStatus::Reschedule;
            $league->notify(new MatchScheduleRespondedNotification($newRequest, $set, User::query()->findOrFail($userId), $otherUser));
            $newRequest->status = ScheduleRequestStatus::Pending;
        }

        return [
            'set_id' => (int) $set->id,
            'flash' => ['success' => 'New time proposed. Waiting for your opponent to respond.'],
        ];
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
