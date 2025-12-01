<?php

namespace App\Modules\Matches\Actions;

/* Define Models */
use App\Modules\Matches\Models\Set;
use App\Modules\Matches\Models\Pool;
use App\Modules\Teams\Models\Team;
/* End Define Models */

/* Define Dependencies */
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;
/* End Define Dependencies */

class CreateEditSetsAction
{
    public function __invoke($data)
    {
        if ($data['command'] == 'create') {
            $pools = Pool::where('league_id', $data['league_id'])->where('status', 1)->get();
            foreach ($pools as $pool) {
                $teams = Team::where('pool_id', $pool->id)->orderBy('seed', 'asc')->get();
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
        }
    }


    protected function scheduleSets($teams)
    {
        $teams = collect($teams);
        $teamsCount = $teams->count();
        $rounds = $teamsCount - 1;
        $matchesPerRound = floor($teamsCount / 2);
        for ($i = 1; $i <= $rounds; $i++) {
        $schedule[$i] = collect();
        $teams->each(function ($team, $index) use ($matchesPerRound, $i, $teams, $schedule) {
            $team1 = $team;
            $team2 = $teams->get($index + $matchesPerRound);
            $matchup = $i % 2 === 0 ? collect(['round' => $i, 'team1' => $team1, 'team2' => $team2]) : collect(['round' => $i, 'team1' => $team2, 'team2' => $team1]);
            $schedule[$i]->push($matchup);
        });
        $teams = $this->rotate($teams);
        }
        return $schedule;
    }

    protected function cleanSchedule($schedule)
    {
        $schedule = collect($schedule)->transform(function ($rounds, $key) {
            return $rounds->filter(function ($round) {
                return !is_null($round->get('team1')) && !is_null($round->get('team2'));
            })->values();
        })->values();
        return $schedule;
    }

    protected function rotate($teams)
    {
        $teamsCount = $teams->count();
        if ($teamsCount < 3) {
            return $teams;
        }
        $lastIndex = $teamsCount - 1;
        $factor = (int) ($teamsCount % 2 === 0 ? $teamsCount/2 : ($teamsCount/2) + 1);
        $topRightIndex = $factor - 1;
        $topRightItem = $teams[$topRightIndex];
        $bottomLeftIndex = $factor;
        $bottomLeftItem = $teams[$bottomLeftIndex];
        for ($i = $topRightIndex; $i > 0; $i--) {
            $teams[$i] = $teams[$i - 1];
        }
        for ($i = $bottomLeftIndex; $i < $lastIndex; $i++) {
            $teams[$i] = $teams[$i + 1];
        }
        $teams[1] = $topRightItem;
        $teams[$lastIndex] = $bottomLeftItem;
        return $teams;
    }
}