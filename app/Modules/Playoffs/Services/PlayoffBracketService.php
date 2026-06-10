<?php

namespace App\Modules\Playoffs\Services;

use App\Enums\Playoffs\PlayoffFormat;
use App\Enums\Playoffs\PlayoffStatus;
use App\Modules\League\Enums\LeagueStatus;
use App\Modules\League\Models\League;
use App\Modules\Playoffs\Models\Playoff;
use App\Modules\Playoffs\Models\PlayoffMatch;
use App\Modules\Pokepaste\Services\EnforceTeamMatchPokepasteChecker;
use App\Modules\Teams\Models\Team;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PlayoffBracketService
{
    /** @var list<int> */
    public static function allowedBracketSizes(): array
    {
        return [2, 4, 6, 8, 16, 32];
    }

    public static function isValidBracketSize(int $n): bool
    {
        if ($n === 6) {
            return true;
        }

        return $n >= 2 && $n <= 32 && ($n & ($n - 1)) === 0;
    }

    /**
     * @return Collection<int, Team>
     */
    public function suggestedSeedTeams(League $league): Collection
    {
        return Team::query()
            ->where('league_id', $league->id)
            ->notDropped()
            ->orderByDesc('victory_points')
            ->orderByDesc('set_wins')
            ->orderBy('set_losses')
            ->orderBy('id')
            ->get();
    }

    public function generateSingleElimination(Playoff $playoff): void
    {
        if ($playoff->format !== PlayoffFormat::SingleElimination) {
            throw new InvalidArgumentException('Double elimination is not supported yet.');
        }

        $n = $playoff->bracket_size;
        if (! self::isValidBracketSize($n)) {
            throw new InvalidArgumentException('Bracket size must be 6 or a power of 2 from 2 to 32.');
        }

        /** @var list<int>|null $seeds */
        $seeds = $playoff->seed_order;
        if ($seeds === null || count($seeds) < $n) {
            throw new InvalidArgumentException('Seed order must include at least as many teams as the bracket size.');
        }

        $teamIds = array_slice(array_map('intval', $seeds), 0, $n);
        $valid = Team::query()
            ->where('league_id', $playoff->league_id)
            ->whereIn('id', $teamIds)
            ->count();

        if ($valid !== $n) {
            throw new InvalidArgumentException('Each seeded team must belong to this league.');
        }

        DB::transaction(function () use ($playoff, $n, $teamIds): void {
            $playoff->matches()->delete();

            $sort = 0;
            $numRounds = $n === 6
                ? 3
                : (int) log($n, 2);

            if ($n === 6) {
                $sort = $this->createSixTeamMainBracket($playoff, $teamIds, $sort);
            } else {
                $sort = $this->createPowerOfTwoMainBracket($playoff, $n, $teamIds, $sort);
            }

            if ($numRounds >= 2) {
                $semiRound = $numRounds - 2;
                PlayoffMatch::query()->create([
                    'playoff_id' => $playoff->id,
                    'slot' => 'bronze',
                    'round_index' => $numRounds,
                    'sort_order' => $sort++,
                    'is_bronze' => true,
                    'team1_id' => null,
                    'team2_id' => null,
                    'feeds' => [
                        'team1' => ['kind' => 'loser_of', 'slot' => "r{$semiRound}-0"],
                        'team2' => ['kind' => 'loser_of', 'slot' => "r{$semiRound}-1"],
                    ],
                ]);
            }

            $playoff->status = PlayoffStatus::Active;
            $playoff->save();
        });

        $this->recomputeParticipants($playoff->fresh(['matches']));
    }

    public function recomputeParticipants(Playoff $playoff): void
    {
        /** @var list<int>|null $seeds */
        $seeds = $playoff->seed_order;
        if ($seeds === null) {
            return;
        }

        $seedIds = array_map('intval', $seeds);

        $matches = PlayoffMatch::query()
            ->where('playoff_id', $playoff->id)
            ->orderBy('round_index')
            ->orderBy('sort_order')
            ->get();

        $bySlot = $matches->keyBy('slot');

        foreach ($matches as $match) {
            $t1 = $this->resolveFeed($bySlot, $seedIds, $match->feeds['team1'] ?? []);
            $t2 = $this->resolveFeed($bySlot, $seedIds, $match->feeds['team2'] ?? []);

            if ($match->winner_team_id !== null) {
                if ($t1 === null || $t2 === null) {
                    $this->clearMatchResult($match);
                } elseif (! in_array((int) $match->winner_team_id, [$t1, $t2], true)) {
                    $this->clearMatchResult($match);
                }
            }

            $match->team1_id = $t1;
            $match->team2_id = $t2;
            $match->save();

            $bySlot->put($match->slot, $match->fresh());
        }
    }

    public function recordResult(PlayoffMatch $match, int $team1Score, int $team2Score): void
    {
        if ($match->team1_id === null || $match->team2_id === null) {
            throw new InvalidArgumentException('Both teams must be assigned before recording a result.');
        }

        $league = $match->playoff?->league;
        $league?->loadMissing('matchConfig');
        if ($league?->matchConfig?->require_team_match_pokepaste_before_results === true) {
            if (! app(EnforceTeamMatchPokepasteChecker::class)->playoffMatchBothSidesHaveData($match)) {
                throw new InvalidArgumentException('Both teams must submit their match teamsheet (Pokepaste) before a playoff result can be recorded.');
            }
        }

        $winnerId = $this->calculateWinnerTeamId($match->team1_id, $match->team2_id, $team1Score, $team2Score);

        DB::transaction(function () use ($match, $team1Score, $team2Score, $winnerId): void {
            $match->team1_score = $team1Score;
            $match->team2_score = $team2Score;
            $match->winner_team_id = $winnerId;
            $match->completed_at = now();
            $match->save();

            $playoff = $match->playoff()->with(['matches' => fn ($q) => $q->orderBy('round_index')->orderBy('sort_order')])->first();
            if ($playoff !== null) {
                $this->recomputeParticipants($playoff);
            }
        });
    }

    public function rollbackMatch(PlayoffMatch $match): void
    {
        DB::transaction(function () use ($match): void {
            $this->clearMatchResult($match);
            $match->save();

            $playoff = $match->playoff()->with(['matches' => fn ($q) => $q->orderBy('round_index')->orderBy('sort_order')])->first();
            if ($playoff !== null) {
                $this->recomputeParticipants($playoff);
            }
        });
    }

    public function clearMatchResult(PlayoffMatch $match): void
    {
        $match->team1_score = null;
        $match->team2_score = null;
        $match->winner_team_id = null;
        $match->completed_at = null;
    }

    /**
     * @param  array<string, mixed>  $feed
     */
    public function resolveFeed(Collection $bySlot, array $seedIds, array $feed): ?int
    {
        $kind = $feed['kind'] ?? null;
        if ($kind === 'seed') {
            $index = (int) ($feed['index'] ?? -1);

            return $seedIds[$index] ?? null;
        }

        if ($kind === 'winner_of' || $kind === 'loser_of') {
            $slot = $feed['slot'] ?? '';
            $parent = $bySlot->get($slot);
            if (! $parent instanceof PlayoffMatch || ! $parent->isComplete()) {
                return null;
            }

            if ($kind === 'winner_of') {
                return $parent->winner_team_id !== null ? (int) $parent->winner_team_id : null;
            }

            $w = (int) $parent->winner_team_id;
            $a = (int) $parent->team1_id;
            $b = (int) $parent->team2_id;

            return $w === $a ? $b : $a;
        }

        return null;
    }

    public function finalsMatch(Playoff $playoff): ?PlayoffMatch
    {
        $playoff->loadMissing('matches');
        $main = $playoff->matches->where('is_bronze', false)->sortBy([
            ['round_index', 'desc'],
            ['sort_order', 'asc'],
        ])->first();

        return $main instanceof PlayoffMatch ? $main : null;
    }

    public function bronzeMatch(Playoff $playoff): ?PlayoffMatch
    {
        $playoff->loadMissing('matches');

        return $playoff->matches->firstWhere('is_bronze', true);
    }

    public function canClosePlayoffs(Playoff $playoff): bool
    {
        if ($playoff->status !== PlayoffStatus::Active) {
            return false;
        }

        $finals = $this->finalsMatch($playoff);
        if ($finals === null || ! $finals->isComplete()) {
            return false;
        }

        $bronze = $this->bronzeMatch($playoff);
        if ($bronze !== null && ! $bronze->isComplete()) {
            return false;
        }

        return true;
    }

    public function closePlayoffs(Playoff $playoff): void
    {
        if (! $this->canClosePlayoffs($playoff)) {
            throw new InvalidArgumentException('Finals and bronze (if applicable) must be complete before closing playoffs.');
        }

        $finals = $this->finalsMatch($playoff);
        $bronze = $this->bronzeMatch($playoff);

        if ($finals === null) {
            throw new InvalidArgumentException('Finals match not found.');
        }

        $firstTeamId = (int) $finals->winner_team_id;
        $secondTeamId = (int) ($finals->winner_team_id === $finals->team1_id ? $finals->team2_id : $finals->team1_id);

        $thirdTeamId = null;
        if ($bronze !== null && $bronze->isComplete()) {
            $thirdTeamId = (int) $bronze->winner_team_id;
        }

        $firstTeam = Team::query()->where('league_id', $playoff->league_id)->find($firstTeamId);
        $secondTeam = Team::query()->where('league_id', $playoff->league_id)->find($secondTeamId);

        if ($firstTeam === null || $secondTeam === null) {
            throw new InvalidArgumentException('Could not resolve finals teams.');
        }

        DB::transaction(function () use ($playoff, $firstTeam, $secondTeam, $thirdTeamId): void {
            Team::query()->where('league_id', $playoff->league_id)->update(['medal_placement' => 0]);

            $firstTeam->medal_placement = 1;
            $firstTeam->save();
            $secondTeam->medal_placement = 2;
            $secondTeam->save();

            if ($thirdTeamId !== null) {
                $third = Team::query()->where('league_id', $playoff->league_id)->find($thirdTeamId);
                if ($third !== null) {
                    $third->medal_placement = 3;
                    $third->save();
                }
            }

            $league = League::query()->find($playoff->league_id);
            if ($league !== null) {
                $league->winner = $firstTeam->user_id;
                $league->status = LeagueStatus::Completed;
                $league->save();
            }

            $playoff->status = PlayoffStatus::Completed;
            $playoff->save();
        });
    }

    public function resetBracketAndReopenLeague(Playoff $playoff): void
    {
        DB::transaction(function () use ($playoff): void {
            PlayoffMatch::query()->where('playoff_id', $playoff->id)->delete();

            $playoff->status = PlayoffStatus::Draft;
            $playoff->save();

            Team::query()->where('league_id', $playoff->league_id)->update(['medal_placement' => 0]);

            $league = League::query()->find($playoff->league_id);
            if ($league !== null) {
                $league->winner = null;
                $league->status = LeagueStatus::Playoffs;
                $league->save();
            }
        });
    }

    private function calculateWinnerTeamId(int $team1Id, int $team2Id, int $team1Score, int $team2Score): int
    {
        if ($team1Score > $team2Score) {
            return $team1Id;
        }

        if ($team2Score > $team1Score) {
            return $team2Id;
        }

        throw new InvalidArgumentException('Scores cannot be tied.');
    }

    /**
     * Six teams: seeds 1–2 get byes; opening round 4v5 and 3v6; then semis 1 vs W(4v5), 2 vs W(3v6); finals.
     *
     * @param  list<int>  $teamIds  Six team ids in seed order (index 0 = top seed).
     */
    private function createSixTeamMainBracket(Playoff $playoff, array $teamIds, int $sort): int
    {
        PlayoffMatch::query()->create([
            'playoff_id' => $playoff->id,
            'slot' => 'r0-0',
            'round_index' => 0,
            'sort_order' => $sort++,
            'is_bronze' => false,
            'team1_id' => $teamIds[3],
            'team2_id' => $teamIds[4],
            'feeds' => [
                'team1' => ['kind' => 'seed', 'index' => 3],
                'team2' => ['kind' => 'seed', 'index' => 4],
            ],
        ]);

        PlayoffMatch::query()->create([
            'playoff_id' => $playoff->id,
            'slot' => 'r0-1',
            'round_index' => 0,
            'sort_order' => $sort++,
            'is_bronze' => false,
            'team1_id' => $teamIds[2],
            'team2_id' => $teamIds[5],
            'feeds' => [
                'team1' => ['kind' => 'seed', 'index' => 2],
                'team2' => ['kind' => 'seed', 'index' => 5],
            ],
        ]);

        PlayoffMatch::query()->create([
            'playoff_id' => $playoff->id,
            'slot' => 'r1-0',
            'round_index' => 1,
            'sort_order' => $sort++,
            'is_bronze' => false,
            'team1_id' => $teamIds[0],
            'team2_id' => null,
            'feeds' => [
                'team1' => ['kind' => 'seed', 'index' => 0],
                'team2' => ['kind' => 'winner_of', 'slot' => 'r0-0'],
            ],
        ]);

        PlayoffMatch::query()->create([
            'playoff_id' => $playoff->id,
            'slot' => 'r1-1',
            'round_index' => 1,
            'sort_order' => $sort++,
            'is_bronze' => false,
            'team1_id' => $teamIds[1],
            'team2_id' => null,
            'feeds' => [
                'team1' => ['kind' => 'seed', 'index' => 1],
                'team2' => ['kind' => 'winner_of', 'slot' => 'r0-1'],
            ],
        ]);

        PlayoffMatch::query()->create([
            'playoff_id' => $playoff->id,
            'slot' => 'r2-0',
            'round_index' => 2,
            'sort_order' => $sort++,
            'is_bronze' => false,
            'team1_id' => null,
            'team2_id' => null,
            'feeds' => [
                'team1' => ['kind' => 'winner_of', 'slot' => 'r1-0'],
                'team2' => ['kind' => 'winner_of', 'slot' => 'r1-1'],
            ],
        ]);

        return $sort;
    }

    /**
     * @param  list<int>  $teamIds
     */
    private function createPowerOfTwoMainBracket(Playoff $playoff, int $n, array $teamIds, int $sort): int
    {
        $numRounds = (int) log($n, 2);

        for ($r = 0; $r < $numRounds; $r++) {
            $matchesInRound = intdiv($n, 2 ** ($r + 1));
            for ($i = 0; $i < $matchesInRound; $i++) {
                $slot = "r{$r}-{$i}";
                if ($r === 0) {
                    $idx1 = $i;
                    $idx2 = $n - 1 - $i;
                    $feeds = [
                        'team1' => ['kind' => 'seed', 'index' => $idx1],
                        'team2' => ['kind' => 'seed', 'index' => $idx2],
                    ];
                    PlayoffMatch::query()->create([
                        'playoff_id' => $playoff->id,
                        'slot' => $slot,
                        'round_index' => $r,
                        'sort_order' => $sort++,
                        'is_bronze' => false,
                        'team1_id' => $teamIds[$idx1],
                        'team2_id' => $teamIds[$idx2],
                        'feeds' => $feeds,
                    ]);
                } else {
                    $feeds = [
                        'team1' => ['kind' => 'winner_of', 'slot' => 'r'.($r - 1).'-'.(2 * $i)],
                        'team2' => ['kind' => 'winner_of', 'slot' => 'r'.($r - 1).'-'.(2 * $i + 1)],
                    ];
                    PlayoffMatch::query()->create([
                        'playoff_id' => $playoff->id,
                        'slot' => $slot,
                        'round_index' => $r,
                        'sort_order' => $sort++,
                        'is_bronze' => false,
                        'team1_id' => null,
                        'team2_id' => null,
                        'feeds' => $feeds,
                    ]);
                }
            }
        }

        return $sort;
    }
}
