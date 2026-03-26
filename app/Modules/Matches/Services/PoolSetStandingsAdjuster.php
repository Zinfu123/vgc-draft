<?php

namespace App\Modules\Matches\Services;

use App\Modules\Matches\Models\Set;
use App\Modules\Teams\Models\Team;

class PoolSetStandingsAdjuster
{
    public function revertCompletedMatchStandings(Set $set): void
    {
        if ((int) $set->status !== 0 || $set->team1_id === null || $set->team2_id === null) {
            return;
        }

        $team1 = Team::query()->find($set->team1_id);
        $team2 = Team::query()->find($set->team2_id);
        if ($team1 === null || $team2 === null) {
            return;
        }

        $team1Score = (int) $set->team1_score;
        $team2Score = (int) $set->team2_score;

        $team1->victory_points -= $this->calculatePoints($team1Score, $team2Score);
        $team2->victory_points -= $this->calculatePoints($team2Score, $team1Score);

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

        $team1->save();
        $team2->save();
    }

    public function revertByeStandings(Set $set): void
    {
        if ((int) $set->status !== 0 || $set->team1_id === null || ! $set->is_bye) {
            return;
        }

        $team1 = Team::query()->find($set->team1_id);
        if ($team1 === null) {
            return;
        }

        $team1->victory_points -= 3;
        $team1->set_wins -= 1;
        $team1->game_wins -= 2;
        $team1->save();
    }

    public function applyByeWinToSurvivor(Team $survivor): void
    {
        $survivor->victory_points += 3;
        $survivor->set_wins += 1;
        $survivor->game_wins += 2;
        $survivor->save();
    }

    private function calculatePoints(int $playerScore, int $opponentScore): int
    {
        if ($playerScore === 2 && $opponentScore === 0) {
            return 3;
        }
        if ($playerScore === 2 && $opponentScore === 1) {
            return 2;
        }
        if ($playerScore === 1 && $opponentScore === 2) {
            return 1;
        }

        return 0;
    }
}
