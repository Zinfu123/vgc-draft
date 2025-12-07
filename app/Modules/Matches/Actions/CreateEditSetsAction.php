<?php

namespace App\Modules\Matches\Actions;

/* Define Models */
use App\Events\SetUpdatedEvent;
use App\Modules\Matches\Models\Pool;
use App\Modules\Matches\Models\Set;
use App\Modules\Teams\Models\Team;

/* End Define Models */

/* Define Dependencies */
/* End Define Dependencies */

class CreateEditSetsAction
{
    public function __invoke($data)
    {
        if ($data['command'] == 'create') {
            $pools = Pool::where('league_id', $data['league_id'])->where('status', 1)->get();
            foreach ($pools as $pool) {
                $teams = Team::where('pool_id', $pool->id)->orderBy('seed', 'asc')->get();
                $oddCheck = $teams->count() % 2 !== 0;
                if ($oddCheck === true) {
                    $teams->push(null);
                }
                $schedule = $this->scheduleSets($teams);
                $schedule = $this->cleanSchedule($schedule);
                foreach ($schedule as $round => $matchups) {
                    foreach ($matchups as $matchup) {
                        $set = Set::create([
                            'league_id' => $data['league_id'],
                            'pool_id' => $pool->id,
                            'round' => $round + 1,
                            'team1_id' => $matchup['team1']->id,
                            'team2_id' => $matchup['team2']->id,
                            'status' => 1,
                        ]);
                        $set->save();
                    }
                }
            }
        } elseif ($data['command'] == 'update') {
            $setid = $data['set_id'];
            $set = Set::where('id', $setid)->first();
            if (! $set) {
                return;
            }
            if ($set->status == 0) {
                return;
            } else {
                $winner = $this->CalculateWinner($data);
                $team1points = $this->calculatePoints($data['team1_score'], $data['team2_score']);
                $team2points = $this->calculatePoints($data['team2_score'], $data['team1_score']);
                $set->team1_score = $data['team1_score'];
                $set->team2_score = $data['team2_score'];
                $set->team1_pokepaste = $data['team1_pokepaste'] || null;
                $set->team2_pokepaste = $data['team2_pokepaste'] || null;
                $set->winner_id = $winner;
                $set->status = 0;
                $set->save();

                $team1 = Team::where('id', $set->team1_id)->first();
                $team1->victory_points += $team1points;
                $team1->save();
                $team2 = Team::where('id', $set->team2_id)->first();
                $team2->victory_points += $team2points;
                $team2->save();
                SetUpdatedEvent::dispatch(['set_id' => $set->id, 'status' => $set->status]);

                return true;
            }
        }
    }

    protected function CalculateWinner($data)
    {
        if ($data['team1_score'] > $data['team2_score']) {
            return $data['team1_id'];
        } else {
            return $data['team2_id'];
        }
    }

    protected function calculatePoints($playerscore, $opponentscore)
    {
        if ($playerscore == 2 && $opponentscore == 0) {
            return 3;
        } elseif ($playerscore == 2 && $opponentscore == 1) {
            return 2;
        } elseif ($playerscore == 1 && $opponentscore == 2) {
            return 1;
        } else {
            return 0;
        }
    }

    protected function scheduleSets($teams)
    {
        $teams = collect($teams);
        $teamsCount = $teams->count();
        $rounds = $teamsCount - 1;
        $matchesPerRound = floor($teamsCount / 2);
        for ($round = 1; $round <= $rounds; $round += 1) {
            $schedule[$round] = collect();
            $teams->each(function ($team, $index) use ($matchesPerRound, $round, $teams, $schedule) {
                if ($index >= $matchesPerRound) {
                    return;
                }
                $team1 = $team;
                $team2 = $teams[$index + $matchesPerRound];
                $matchup = $round % 2 === 0 ? collect(['round' => $round, 'team1' => $team1, 'team2' => $team2]) : collect(['round' => $round, 'team1' => $team2, 'team2' => $team1]);
                $schedule[$round]->push($matchup);
            });
            $teams = $this->rotate($teams);
        }

        return $schedule;
    }

    protected function cleanSchedule($schedule)
    {
        $schedule = collect($schedule)->transform(function ($rounds, $key) {
            return $rounds->filter(function ($round) {
                return ! is_null($round->get('team1')) && ! is_null($round->get('team2'));
            })->values();
        })->values();

        return $schedule;
    }

    protected function rotate($teams)
    {
        $teamsCount = $teams->count();
        $lastIndex = $teamsCount - 1;
        $factor = (int) ($teamsCount % 2 === 0 ? $teamsCount / 2 : ceil($teamsCount / 2));
        $topRightIndex = $factor - 1;
        $topRightItem = $teams[$topRightIndex];
        $bottomLeftIndex = $factor;
        $bottomLeftItem = $teams[$bottomLeftIndex];
        for ($i = $topRightIndex; $i > 0; $i -= 1) {
            $teams[$i] = $teams[$i - 1];
        }
        for ($i = $bottomLeftIndex; $i < $lastIndex; $i += 1) {
            $teams[$i] = $teams[$i + 1];
        }
        $teams[1] = $bottomLeftItem;
        $teams[$lastIndex] = $topRightItem;

        return $teams;
    }
}
