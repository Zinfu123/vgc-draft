<?php

namespace App\Modules\Matches\Controllers;

use App\Events\BattleUpdatedEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Battle\SubmitBattleActionRequest;
use App\Http\Requests\Battle\SubmitBattleTeamRequest;
use App\Modules\Matches\Models\Battle;
use App\Modules\Matches\Models\Set;
use App\Modules\Matches\Services\BattleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class BattleController extends Controller
{
    public function __construct(private readonly BattleService $battleService) {}

    /**
     * Show the battle view for a given set. Creates a Battle record if one doesn't exist yet.
     */
    public function show(Set $set): Response
    {
        $set->load(['team1.user', 'team2.user']);

        $battle = Battle::firstOrCreate(
            ['set_id' => $set->id],
            [
                'p1_team_id' => $set->team1_id,
                'p2_team_id' => $set->team2_id,
                'format' => 'gen9vgc2024regg',
                'battle_log' => [],
            ],
        );

        $battle->load(['p1Team.user', 'p2Team.user']);

        /** @var \App\Models\User $authUser */
        $authUser = Auth::user();

        return Inertia::render('match/BattleView', [
            'set' => $set,
            'battle' => $battle,
            'myPlayer' => $this->resolvePlayerSlot($battle, $authUser->id),
        ]);
    }

    /**
     * Accept a player's packed team string and, once both are submitted, kick off the battle.
     */
    public function submitTeam(SubmitBattleTeamRequest $request, Battle $battle): JsonResponse
    {
        if ($battle->isFinished()) {
            return response()->json(['error' => 'Battle is already finished.'], 422);
        }

        /** @var \App\Models\User $authUser */
        $authUser = Auth::user();
        $player = $this->resolvePlayerSlot($battle, $authUser->id);

        if ($player === null) {
            return response()->json(['error' => 'You are not a participant in this battle.'], 403);
        }

        $field = $player === 'p1' ? 'p1_packed_team' : 'p2_packed_team';
        $battle->update([$field => $request->packed_team]);

        if ($battle->fresh()->hasTeams()) {
            $result = $this->battleService->startBattle($battle->fresh());

            $battle->update([
                'status' => 'team_preview',
                'battle_log' => $result['log'] ?? [],
            ]);

            broadcast(new BattleUpdatedEvent($battle->fresh(), $result['output'] ?? []));
        }

        return response()->json(['status' => $battle->fresh()->status]);
    }

    /**
     * Submit a move or switch action for the authenticated player.
     */
    public function action(SubmitBattleActionRequest $request, Battle $battle): JsonResponse
    {
        if (! in_array($battle->status, ['team_preview', 'active'], strict: true)) {
            return response()->json(['error' => 'Battle is not accepting actions right now.'], 422);
        }

        /** @var \App\Models\User $authUser */
        $authUser = Auth::user();
        $player = $this->resolvePlayerSlot($battle, $authUser->id);

        if ($player === null) {
            return response()->json(['error' => 'You are not a participant in this battle.'], 403);
        }

        $result = $this->battleService->submitAction($battle, $player, $request->action);

        $winnerName = $this->battleService->extractWinner($result['output'] ?? []);
        $newStatus = $winnerName !== null ? 'finished' : 'active';

        // Advance from team_preview to active after team selection
        if ($battle->status === 'team_preview' && str_starts_with($request->action, 'team ')) {
            $newStatus = 'active';
        }

        $battle->update([
            'status' => $newStatus,
            'winner' => $winnerName,
            'battle_log' => $result['log'] ?? [],
        ]);

        broadcast(new BattleUpdatedEvent($battle->fresh(), $result['output'] ?? []));

        if ($battle->isFinished()) {
            $this->battleService->destroyBattle($battle);
        }

        return response()->json($result);
    }

    /**
     * Returns 'p1', 'p2', or null depending on which side the user is on.
     */
    private function resolvePlayerSlot(Battle $battle, int $userId): ?string
    {
        if ($battle->p1Team->user_id === $userId) {
            return 'p1';
        }

        if ($battle->p2Team->user_id === $userId) {
            return 'p2';
        }

        return null;
    }
}
