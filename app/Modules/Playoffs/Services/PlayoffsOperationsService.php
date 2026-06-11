<?php

namespace App\Modules\Playoffs\Services;

use App\Enums\Playoffs\PlayoffFormat;
use App\Enums\Playoffs\PlayoffStatus;
use App\Kernel\Contracts\PlayoffsOperations;
use App\Models\User;
use App\Modules\League\Actions\LeagueDetailLayoutDataAction;
use App\Modules\League\Enums\LeagueStatus;
use App\Modules\League\Models\League;
use App\Modules\Playoffs\Models\Playoff;
use App\Modules\Playoffs\Models\PlayoffMatch;
use App\Modules\Pokepaste\Actions\ReadPlayoffMatchPokepasteSideSummariesAction;
use InvalidArgumentException;

class PlayoffsOperationsService implements PlayoffsOperations
{
    public function __construct(
        private LeagueDetailLayoutDataAction $leagueDetailLayoutDataAction,
        private PlayoffBracketService $playoffBracketService,
        private ReadPlayoffMatchPokepasteSideSummariesAction $readPlayoffMatchPokepasteSideSummariesAction,
    ) {}

    public function adminPageProps(int $leagueId, int $userId): array
    {
        $league = League::query()->findOrFail($leagueId);
        $user = User::query()->findOrFail($userId);
        abort_unless($user->can('admin', $league), 403);

        $data = ($this->leagueDetailLayoutDataAction)($league);

        $playoff = $league->playoff()->firstOrCreate(
            ['league_id' => $league->id],
            [
                'format' => PlayoffFormat::SingleElimination,
                'bracket_size' => 4,
                'status' => PlayoffStatus::Draft,
                'seed_order' => null,
            ]
        );

        $currentTeamIds = $data['teams']->pluck('id')->all();
        $existingSeedOrder = $playoff->seed_order ?? [];
        $needsReseed = $existingSeedOrder === []
            || ! empty(array_diff($existingSeedOrder, $currentTeamIds))
            || ! empty(array_diff($currentTeamIds, $existingSeedOrder));

        if ($playoff->status === PlayoffStatus::Draft && $needsReseed) {
            $playoff->seed_order = $this->playoffBracketService->suggestedSeedTeams($league)->pluck('id')->all();
            $playoff->save();
        }

        $playoff->load(['matches.team1', 'matches.team2']);

        $league->loadMissing('matchConfig');

        return [
            ...$data,
            'playoff' => $this->buildPlayoffPayloadWithPokepaste($playoff, $league, $user),
            'allowedBracketSizes' => PlayoffBracketService::allowedBracketSizes(),
            'doubleEliminationSupported' => false,
        ];
    }

    public function updateConfig(int $leagueId, array $validated): array
    {
        $league = League::query()->findOrFail($leagueId);
        $playoff = $league->playoff;

        if ($playoff === null || $playoff->status !== PlayoffStatus::Draft) {
            return ['errors' => ['playoff' => 'Playoff settings can only be changed while the bracket is in draft.']];
        }

        $playoff->format = $validated['format'];
        $playoff->bracket_size = (int) $validated['bracket_size'];
        $playoff->seed_order = array_values(array_map('intval', $validated['seed_order']));
        $playoff->save();

        return [];
    }

    public function generateBracket(int $leagueId): array
    {
        $league = League::query()->findOrFail($leagueId);

        if ($league->status !== LeagueStatus::Playoffs) {
            return ['errors' => ['playoff' => 'The playoff bracket can only be generated when the league is in the Playoffs phase.']];
        }

        $playoff = $league->playoff;
        if ($playoff === null || $playoff->status !== PlayoffStatus::Draft) {
            return ['errors' => ['playoff' => 'The bracket can only be generated from draft.']];
        }

        if ($playoff->matches()->exists()) {
            return ['errors' => ['playoff' => 'Reset the bracket before generating again.']];
        }

        if ($playoff->format === PlayoffFormat::DoubleElimination) {
            return ['errors' => ['format' => 'Double elimination is not available yet.']];
        }

        try {
            $this->playoffBracketService->generateSingleElimination($playoff);
        } catch (InvalidArgumentException $e) {
            return ['errors' => ['playoff' => $e->getMessage()]];
        }

        return [];
    }

    public function recordResult(int $leagueId, int $playoffMatchId, int $team1Score, int $team2Score): array
    {
        $league = League::query()->findOrFail($leagueId);

        if ($league->status !== LeagueStatus::Playoffs) {
            return ['errors' => ['playoff' => 'Match results can only be recorded when the league is in the Playoffs phase.']];
        }

        $playoff = $league->playoff;
        if ($playoff === null || $playoff->status !== PlayoffStatus::Active) {
            return ['errors' => ['playoff' => 'Playoffs are not active.']];
        }

        $match = PlayoffMatch::query()->find($playoffMatchId);
        if ($match === null || $match->winner_team_id !== null) {
            return ['errors' => ['playoff_match_id' => 'This match cannot accept a new result.']];
        }

        try {
            $this->playoffBracketService->recordResult($match, $team1Score, $team2Score);
        } catch (InvalidArgumentException $e) {
            return ['errors' => ['playoff' => $e->getMessage()]];
        }

        return [];
    }

    public function rollbackResult(int $leagueId, int $playoffMatchId): array
    {
        $league = League::query()->findOrFail($leagueId);

        if ($league->status !== LeagueStatus::Playoffs) {
            return ['errors' => ['playoff' => 'Match results can only be rolled back when the league is in the Playoffs phase.']];
        }

        $playoff = $league->playoff;
        if ($playoff === null || $playoff->status !== PlayoffStatus::Active) {
            return ['errors' => ['playoff' => 'Playoffs are not active.']];
        }

        $match = PlayoffMatch::query()->find($playoffMatchId);
        if ($match === null || $match->winner_team_id === null) {
            return ['errors' => ['playoff_match_id' => 'This match has no result to roll back.']];
        }

        $this->playoffBracketService->rollbackMatch($match);

        return [];
    }

    public function closePlayoffs(int $leagueId): array
    {
        $league = League::query()->findOrFail($leagueId);
        $playoff = $league->playoff;

        if ($playoff === null) {
            return ['errors' => ['playoff' => 'No playoff found for this league.']];
        }

        try {
            $this->playoffBracketService->closePlayoffs($playoff);
        } catch (InvalidArgumentException $e) {
            return ['errors' => ['playoff' => $e->getMessage()]];
        }

        return [];
    }

    public function resetBracket(int $leagueId, int $userId): array
    {
        $league = League::query()->findOrFail($leagueId);
        $user = User::query()->findOrFail($userId);
        abort_unless($user->can('admin', $league), 403);

        $playoff = $league->playoff;

        if ($playoff === null) {
            return ['errors' => ['playoff' => 'No playoff found for this league.']];
        }

        $this->playoffBracketService->resetBracketAndReopenLeague($playoff);

        return [];
    }

    public function playoffPayloadWithPokepaste(int $playoffId, int $leagueId, ?int $userId): array
    {
        $playoff = Playoff::query()
            ->with(['matches.team1', 'matches.team2'])
            ->findOrFail($playoffId);
        $league = League::query()->findOrFail($leagueId);
        $user = $userId !== null ? User::query()->find($userId) : null;

        if ($user === null) {
            return $this->playoffPayload($playoff);
        }

        $league->loadMissing('matchConfig');

        return $this->buildPlayoffPayloadWithPokepaste($playoff, $league, $user);
    }

    /**
     * @return array<string, mixed>
     */
    private function playoffPayload(Playoff $playoff): array
    {
        return [
            'id' => $playoff->id,
            'format' => $playoff->format->value,
            'bracket_size' => $playoff->bracket_size,
            'status' => $playoff->status->value,
            'seed_order' => $playoff->seed_order ?? [],
            'matches' => $playoff->matches->map(fn (PlayoffMatch $m): array => [
                'id' => $m->id,
                'slot' => $m->slot,
                'round_index' => $m->round_index,
                'sort_order' => $m->sort_order,
                'is_bronze' => $m->is_bronze,
                'team1_id' => $m->team1_id,
                'team2_id' => $m->team2_id,
                'team1_name' => $m->team1?->name,
                'team2_name' => $m->team2?->name,
                'team1_score' => $m->team1_score,
                'team2_score' => $m->team2_score,
                'winner_team_id' => $m->winner_team_id,
                'completed_at' => $m->completed_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPlayoffPayloadWithPokepaste(Playoff $playoff, League $league, User $viewer): array
    {
        $payload = $this->playoffPayload($playoff);
        $isAdmin = $viewer->can('admin', $league);

        $matches = [];
        foreach ($payload['matches'] as $row) {
            $m = $playoff->matches->firstWhere('id', $row['id']);
            if ($m instanceof PlayoffMatch) {
                $row['pokepaste_sides'] = ($this->readPlayoffMatchPokepasteSideSummariesAction)($m, $viewer, $isAdmin);
            }
            $matches[] = $row;
        }
        $payload['matches'] = $matches;
        $payload['require_team_match_pokepaste_before_results'] = (bool) ($league->matchConfig?->require_team_match_pokepaste_before_results ?? false);

        return $payload;
    }
}
