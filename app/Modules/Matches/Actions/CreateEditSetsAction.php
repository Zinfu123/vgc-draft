<?php

namespace App\Modules\Matches\Actions;

use App\Events\SetUpdatedEvent;
use App\Modules\League\Models\League;
use App\Modules\Matches\Models\Pool;
use App\Modules\Matches\Models\Set;
use App\Modules\Teams\Models\Team;
use App\Notifications\MatchReplaysNotification;
use App\Notifications\MatchResultNotification;
use Illuminate\Support\Facades\Auth;

class CreateEditSetsAction
{
    public function __invoke($data)
    {
        if ($data['command'] == 'create') {
            $league = League::with('matchConfig')->find($data['league_id']);
            $roundCount = $league?->matchConfig?->round_count;
            $pools = Pool::where('league_id', $data['league_id'])->where('status', 1)->get();
            foreach ($pools as $pool) {
                $teams = Team::where('pool_id', $pool->id)->orderBy('seed', 'asc')->get();
                $oddCheck = $teams->count() % 2 !== 0;
                if ($oddCheck === true) {
                    $teams->push(null);
                }
                $schedule = $this->scheduleSets($teams, $roundCount ?? null);
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
                $this->applyPokepasteUrlsFromSubmitter($data, $set);
                $set->winner_id = $winner;
                $set->status = 0;
                $set->save();

                $team1 = Team::where('id', $set->team1_id)->first();
                $team1->victory_points += $team1points;
                $team2 = Team::where('id', $set->team2_id)->first();
                $team2->victory_points += $team2points;

                // Update set wins/losses
                if ($winner == $set->team1_id) {
                    $team1->set_wins += 1;
                    $team2->set_losses += 1;
                } else {
                    $team1->set_losses += 1;
                    $team2->set_wins += 1;
                }

                // Update game wins/losses
                $team1->game_wins += $data['team1_score'];
                $team1->game_losses += $data['team2_score'];
                $team2->game_wins += $data['team2_score'];
                $team2->game_losses += $data['team1_score'];

                $team1->save();
                $team2->save();
                SetUpdatedEvent::dispatch(['set_id' => $set->id, 'status' => $set->status]);

                $league = League::find($set->league_id);
                $set->load(['team1', 'team2']);
                $league->notify(new MatchResultNotification($set));

                return true;
            }
        } elseif ($data['command'] == 'reopen') {
            $set = Set::where('id', $data['set_id'])->first();
            if (! $set) {
                return false;
            }
            if ($set->status !== 0) {
                return false;
            }

            $team1Score = (int) $set->team1_score;
            $team2Score = (int) $set->team2_score;

            $team1 = Team::where('id', $set->team1_id)->first();
            $team2 = Team::where('id', $set->team2_id)->first();
            if (! $team1 || ! $team2) {
                return false;
            }

            $team1points = $this->calculatePoints($team1Score, $team2Score);
            $team2points = $this->calculatePoints($team2Score, $team1Score);
            $team1->victory_points -= $team1points;
            $team2->victory_points -= $team2points;

            $winner = $set->winner_id;
            if ($winner === $set->team1_id) {
                $team1->set_wins -= 1;
                $team2->set_losses -= 1;
            } elseif ($winner === $set->team2_id) {
                $team1->set_losses -= 1;
                $team2->set_wins -= 1;
            }

            $team1->game_wins -= $team1Score;
            $team1->game_losses -= $team2Score;
            $team2->game_wins -= $team2Score;
            $team2->game_losses -= $team1Score;

            $set->status = 1;
            $set->winner_id = null;
            $set->team1_score = null;
            $set->team2_score = null;
            $set->save();
            $team1->save();
            $team2->save();
            SetUpdatedEvent::dispatch(['set_id' => $set->id, 'status' => $set->status]);

            return true;
        } elseif ($data['command'] == 'updatePokepaste') {
            $set = Set::where('id', $data['set_id'])->first();
            if (! $set) {
                return false;
            }
            $this->applyPokepasteUrlsFromSubmitter($data, $set);
            $set->save();

            return true;
        } elseif ($data['command'] == 'updateReplays') {
            $set = Set::where('id', $data['set_id'])->first();
            if (! $set) {
                return false;
            }

            $set->replay1 = ! empty($data['replay1']) ? $data['replay1'] : null;
            $set->replay2 = ! empty($data['replay2']) ? $data['replay2'] : null;
            $set->replay3 = ! empty($data['replay3']) ? $data['replay3'] : null;
            $set->save();

            $hasReplays = $set->replay1 || $set->replay2 || $set->replay3;
            if ($hasReplays) {
                $league = League::find($set->league_id);
                $set->load(['team1', 'team2']);
                $league->notify(new MatchReplaysNotification($set));
            }

            return true;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function applyPokepasteUrlsFromSubmitter(array $data, Set $set): void
    {
        $userId = Auth::id();
        if ($userId === null) {
            return;
        }

        $submitter = Team::query()
            ->where('user_id', $userId)
            ->where('league_id', $set->league_id)
            ->first();

        if ($submitter === null) {
            return;
        }

        if ($submitter->id === $set->team1_id && array_key_exists('team1_pokepaste', $data)) {
            $set->team1_pokepaste = ! empty($data['team1_pokepaste']) ? $data['team1_pokepaste'] : null;
        }

        if ($submitter->id === $set->team2_id && array_key_exists('team2_pokepaste', $data)) {
            $set->team2_pokepaste = ! empty($data['team2_pokepaste']) ? $data['team2_pokepaste'] : null;
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

    protected function scheduleSets($teams, $roundCount)
    {
        $teams = collect($teams);
        $teamsCount = $teams->count();
        $rounds = ($roundCount !== null && $roundCount !== 0) ? $roundCount : $teamsCount - 1;
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
