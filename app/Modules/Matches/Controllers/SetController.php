<?php

namespace App\Modules\Matches\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Match\ImportSetReplayTeamsRequest;
use App\Http\Requests\Match\PreviewSetReplayPlayersRequest;
use App\Http\Requests\Match\UpdateSetReplaysRequest;
use App\Http\Requests\Match\UpdateSetRequest;
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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SetController extends Controller
{
    public function create(Request $request, CreateEditSetsAction $createEditSetsAction)
    {
        $sets = $createEditSetsAction(['league_id' => $request->league_id, 'command' => 'create']);

        return redirect()->route('leagues.detail', ['league' => $request->league_id]);
    }

    public function show(
        Request $request,
        $match_id,
        ShowSetsAction $showSetsAction,
        ReadMatchPokepastePayloadAction $readMatchPokepastePayloadAction,
        ReadMatchPokepasteSideSummariesAction $readMatchPokepasteSideSummariesAction,
    ) {
        $set = $showSetsAction(['set_id' => $match_id, 'command' => 'detail']);
        if (! $set) {
            abort(404, 'Set not found');
        }

        $currentUserTeam = Team::query()
            ->where('user_id', Auth::id())
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
            $matchPokepaste = $readMatchPokepastePayloadAction($set, $currentUserTeam);
        }

        $league = League::query()->with('matchConfig')->find($set->league_id);
        $user = $request->user();
        $isLeagueAdmin = $league !== null
            && $user !== null
            && $user->can('admin', $league);

        $isParticipant = $currentUserTeam !== null
            && ($currentUserTeam->id === $set->team1_id || $currentUserTeam->id === $set->team2_id);

        return Inertia::render('match/MatchDetail', [
            'set' => fn () => $set,
            'currentUserTeam' => fn () => $currentUserTeam,
            'matchPokepaste' => fn () => $matchPokepaste,
            'matchPokepasteSides' => fn () => $readMatchPokepasteSideSummariesAction($set),
            'isLeagueAdmin' => fn () => $isLeagueAdmin,
            'requireTeamMatchPokepasteBeforeResults' => fn () => true,
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
        ]);
    }

    public function update(UpdateSetRequest $request, CreateEditSetsAction $createEditSetsAction)
    {
        $createEditSetsAction($request->validated());

        return redirect()->route('sets.show', ['set_id' => $request->set_id]);
    }

    public function updatePokepaste(Request $request, CreateEditSetsAction $createEditSetsAction)
    {
        $result = $createEditSetsAction($request->all());

        return redirect()->route('sets.show', ['set_id' => $request->set_id]);
    }

    public function updateReplays(UpdateSetReplaysRequest $request, CreateEditSetsAction $createEditSetsAction)
    {
        $data = $request->validated();

        $createEditSetsAction([
            'command' => 'updateReplays',
            'set_id' => $data['set_id'],
            'replay1' => $data['replay1'] ?? null,
            'replay2' => $data['replay2'] ?? null,
            'replay3' => $data['replay3'] ?? null,
        ]);

        return redirect()->route('sets.show', ['set_id' => $data['set_id']]);
    }

    public function previewReplayPlayers(
        PreviewSetReplayPlayersRequest $request,
        ShowdownReplayLogFetcher $logFetcher,
        ShowdownReplayPlayerNamesParser $playerNamesParser,
        SuggestP1TeamFromShowdownReplay $suggestP1TeamFromShowdownReplay,
    ): JsonResponse {
        $set = Set::query()->with(['team1.user', 'team2.user'])->findOrFail($request->validated('set_id'));
        $slot = (int) $request->validated('replay_slot');
        $replayUrl = match ($slot) {
            1 => $set->replay1,
            2 => $set->replay2,
            3 => $set->replay3,
            default => null,
        };

        try {
            $logUrl = ShowdownReplayLogUrl::resolveLogDownloadUrl((string) $replayUrl);
            $logText = $logFetcher->fetch($logUrl);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $parsed = $playerNamesParser->parse($logText);
        if ($parsed['errors'] !== []) {
            return response()->json([
                'ok' => false,
                'errors' => $parsed['errors'],
            ], 422);
        }

        $suggested = $suggestP1TeamFromShowdownReplay->suggest($set, $parsed['p1'], $parsed['p2']);

        return response()->json([
            'ok' => true,
            'p1_name' => $parsed['p1'],
            'p2_name' => $parsed['p2'],
            'suggested_p1_team_id' => $suggested,
            'needs_manual_p1_map' => $suggested === null,
        ]);
    }

    public function importReplayTeams(
        ImportSetReplayTeamsRequest $request,
        ImportSetTeamsFromShowdownReplayAction $importSetTeamsFromShowdownReplayAction,
    ): RedirectResponse {
        $set = Set::query()->findOrFail($request->validated('set_id'));

        return $importSetTeamsFromShowdownReplayAction(
            $set,
            (int) $request->validated('replay_slot'),
            (int) $request->validated('p1_team_id'),
        );
    }
}
