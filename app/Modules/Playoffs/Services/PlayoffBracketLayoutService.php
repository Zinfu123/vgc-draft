<?php

namespace App\Modules\Playoffs\Services;

use App\Modules\Playoffs\Models\Playoff;
use App\Modules\Playoffs\Models\PlayoffMatch;
use Illuminate\Support\Collection;

class PlayoffBracketLayoutService
{
    /**
     * @param  Collection<int, \stdClass|object>  $teamsById  keyed by team id (id, name, coach)
     * @return array{mode: string, rounds: list<array<string, mixed>>}
     */
    public function build(Playoff $playoff, Collection $teamsById): array
    {
        $playoff->loadMissing(['matches.team1', 'matches.team2']);

        if ($playoff->matches->isEmpty()) {
            return [
                'mode' => 'draft',
                'rounds' => $this->buildDraftRounds($playoff, $teamsById),
            ];
        }

        return [
            'mode' => 'live',
            'rounds' => $this->buildLiveRounds($playoff, $teamsById),
        ];
    }

    /**
     * @param  Collection<int, \stdClass|object>  $teamsById
     * @return list<array<string, mixed>>
     */
    private function buildLiveRounds(Playoff $playoff, Collection $teamsById): array
    {
        $bronze = $playoff->matches->firstWhere('is_bronze', true);
        $main = $playoff->matches
            ->where('is_bronze', false)
            ->sortBy([
                ['round_index', 'asc'],
                ['sort_order', 'asc'],
            ])
            ->values();

        $byRound = $main->groupBy('round_index');
        $rounds = [];

        $seedByTeamId = $this->bracketSeedNumbersByTeamId($playoff);

        foreach ($byRound as $roundIndex => $matches) {
            $rounds[] = [
                'key' => 'main-'.$roundIndex,
                'round_index' => (int) $roundIndex,
                'is_bronze_round' => false,
                'matches' => $matches->map(fn (PlayoffMatch $m) => $this->liveMatchRow($m, $teamsById, $seedByTeamId))->values()->all(),
            ];
        }

        if ($bronze instanceof PlayoffMatch) {
            $rounds[] = [
                'key' => 'bronze',
                'round_index' => $bronze->round_index,
                'is_bronze_round' => true,
                'matches' => [$this->liveMatchRow($bronze, $teamsById, $seedByTeamId)],
            ];
        }

        return $rounds;
    }

    /**
     * @param  Collection<int, \stdClass|object>  $teamsById
     * @return array<string, mixed>
     */
    /**
     * @param  array<int, int>  $seedByTeamId
     */
    private function liveMatchRow(PlayoffMatch $m, Collection $teamsById, array $seedByTeamId): array
    {
        return [
            'slot' => $m->slot,
            'id' => $m->id,
            'is_bronze' => $m->is_bronze,
            'complete' => $m->isComplete(),
            'winner_team_id' => $m->winner_team_id,
            'team1_score' => $m->team1_score,
            'team2_score' => $m->team2_score,
            'top' => $this->liveCell($m->team1_id, $teamsById, $seedByTeamId),
            'bottom' => $this->liveCell($m->team2_id, $teamsById, $seedByTeamId),
        ];
    }

    /**
     * Original bracket seed (1-based) from the first `bracket_size` team ids in `seed_order`.
     *
     * @return array<int, int> team_id => seed_number
     */
    private function bracketSeedNumbersByTeamId(Playoff $playoff): array
    {
        $order = array_values(array_map('intval', $playoff->seed_order ?? []));
        $slice = array_slice($order, 0, $playoff->bracket_size);
        $map = [];
        foreach ($slice as $index => $teamId) {
            if ($teamId > 0) {
                $map[$teamId] = $index + 1;
            }
        }

        return $map;
    }

    /**
     * @param  Collection<int, \stdClass|object>  $teamsById
     * @return array<string, mixed>
     */
    /**
     * @param  array<int, int>  $seedByTeamId
     */
    private function liveCell(?int $teamId, Collection $teamsById, array $seedByTeamId): array
    {
        if ($teamId === null) {
            return [
                'kind' => 'pending',
                'pending_label' => 'TBD',
                'team_id' => null,
                'name' => null,
                'coach' => null,
                'seed_index' => null,
                'seed_number' => null,
            ];
        }

        $row = $teamsById->get($teamId);

        return [
            'kind' => 'team',
            'team_id' => $teamId,
            'name' => $row->name ?? 'Team',
            'coach' => $row->coach ?? null,
            'seed_index' => null,
            'seed_number' => $seedByTeamId[$teamId] ?? null,
            'pending_label' => null,
        ];
    }

    /**
     * @param  Collection<int, \stdClass|object>  $teamsById
     * @return list<array<string, mixed>>
     */
    private function buildDraftRounds(Playoff $playoff, Collection $teamsById): array
    {
        $n = $playoff->bracket_size;
        /** @var list<int> $seeds */
        $seeds = array_values(array_map('intval', $playoff->seed_order ?? []));
        while (count($seeds) < $n) {
            $seeds[] = 0;
        }
        $seeds = array_slice($seeds, 0, $n);

        if ($n === 6) {
            return $this->draftRoundsSix($seeds, $teamsById);
        }

        if (($n & ($n - 1)) === 0 && $n >= 2) {
            return $this->draftRoundsPowerOfTwo($n, $seeds, $teamsById);
        }

        return [];
    }

    /**
     * @param  list<int>  $seeds
     * @param  Collection<int, \stdClass|object>  $teamsById
     * @return list<array<string, mixed>>
     */
    private function draftRoundsPowerOfTwo(int $n, array $seeds, Collection $teamsById): array
    {
        $numRounds = (int) log($n, 2);
        $rounds = [];

        for ($r = 0; $r < $numRounds; $r++) {
            $matchesInRound = intdiv($n, 2 ** ($r + 1));
            $matches = [];
            for ($i = 0; $i < $matchesInRound; $i++) {
                $slot = "r{$r}-{$i}";
                if ($r === 0) {
                    $matches[] = [
                        'slot' => $slot,
                        'id' => null,
                        'is_bronze' => false,
                        'complete' => false,
                        'winner_team_id' => null,
                        'team1_score' => null,
                        'team2_score' => null,
                        'top' => $this->draftSeedCell($seeds, $i, $teamsById),
                        'bottom' => $this->draftSeedCell($seeds, $n - 1 - $i, $teamsById),
                    ];
                } else {
                    $matches[] = [
                        'slot' => $slot,
                        'id' => null,
                        'is_bronze' => false,
                        'complete' => false,
                        'winner_team_id' => null,
                        'team1_score' => null,
                        'team2_score' => null,
                        'top' => $this->draftPendingCell('r'.($r - 1).'-'.(2 * $i)),
                        'bottom' => $this->draftPendingCell('r'.($r - 1).'-'.(2 * $i + 1)),
                    ];
                }
            }
            $rounds[] = [
                'key' => 'draft-main-'.$r,
                'round_index' => $r,
                'is_bronze_round' => false,
                'matches' => $matches,
            ];
        }

        return $rounds;
    }

    /**
     * @param  list<int>  $seeds
     * @param  Collection<int, \stdClass|object>  $teamsById
     * @return list<array<string, mixed>>
     */
    private function draftRoundsSix(array $seeds, Collection $teamsById): array
    {
        return [
            [
                'key' => 'draft-main-0',
                'round_index' => 0,
                'is_bronze_round' => false,
                'matches' => [
                    [
                        'slot' => 'r0-0',
                        'id' => null,
                        'is_bronze' => false,
                        'complete' => false,
                        'winner_team_id' => null,
                        'team1_score' => null,
                        'team2_score' => null,
                        'top' => $this->draftSeedCell($seeds, 3, $teamsById),
                        'bottom' => $this->draftSeedCell($seeds, 4, $teamsById),
                    ],
                    [
                        'slot' => 'r0-1',
                        'id' => null,
                        'is_bronze' => false,
                        'complete' => false,
                        'winner_team_id' => null,
                        'team1_score' => null,
                        'team2_score' => null,
                        'top' => $this->draftSeedCell($seeds, 2, $teamsById),
                        'bottom' => $this->draftSeedCell($seeds, 5, $teamsById),
                    ],
                ],
            ],
            [
                'key' => 'draft-main-1',
                'round_index' => 1,
                'is_bronze_round' => false,
                'matches' => [
                    [
                        'slot' => 'r1-0',
                        'id' => null,
                        'is_bronze' => false,
                        'complete' => false,
                        'winner_team_id' => null,
                        'team1_score' => null,
                        'team2_score' => null,
                        'top' => $this->draftSeedCell($seeds, 0, $teamsById),
                        'bottom' => $this->draftPendingCell('r0-0'),
                    ],
                    [
                        'slot' => 'r1-1',
                        'id' => null,
                        'is_bronze' => false,
                        'complete' => false,
                        'winner_team_id' => null,
                        'team1_score' => null,
                        'team2_score' => null,
                        'top' => $this->draftSeedCell($seeds, 1, $teamsById),
                        'bottom' => $this->draftPendingCell('r0-1'),
                    ],
                ],
            ],
            [
                'key' => 'draft-main-2',
                'round_index' => 2,
                'is_bronze_round' => false,
                'matches' => [
                    [
                        'slot' => 'r2-0',
                        'id' => null,
                        'is_bronze' => false,
                        'complete' => false,
                        'winner_team_id' => null,
                        'team1_score' => null,
                        'team2_score' => null,
                        'top' => $this->draftPendingCell('r1-0'),
                        'bottom' => $this->draftPendingCell('r1-1'),
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  list<int>  $seeds
     * @param  Collection<int, \stdClass|object>  $teamsById
     * @return array<string, mixed>
     */
    private function draftSeedCell(array $seeds, int $index, Collection $teamsById): array
    {
        $tid = $seeds[$index] ?? null;
        $tid = $tid > 0 ? $tid : null;
        $row = $tid !== null ? $teamsById->get($tid) : null;

        return [
            'kind' => 'seed',
            'seed_index' => $index,
            'seed_number' => $index + 1,
            'team_id' => $tid,
            'name' => $row->name ?? null,
            'coach' => $row->coach ?? null,
            'pending_label' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function draftPendingCell(string $fromSlot): array
    {
        return [
            'kind' => 'pending',
            'seed_index' => null,
            'seed_number' => null,
            'team_id' => null,
            'name' => null,
            'coach' => null,
            'pending_label' => 'Awaiting '.$fromSlot,
        ];
    }
}
