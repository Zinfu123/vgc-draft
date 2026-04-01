<?php

namespace App\Modules\Pokepaste\Services;

use App\Modules\Matches\Models\Set;

class ReplaySetOutcomeAggregator
{
    public function __construct(
        private ShowdownReplayLogFetcher $logFetcher,
        private ShowdownReplayWinnerParser $winnerParser,
    ) {}

    /**
     * Counts game wins from each saved replay (in slot order) using |win| vs each coach's Showdown name.
     * Returns finalized BO3 scores when one side reaches 2 wins; otherwise null.
     *
     * @return array{team1_score: int, team2_score: int}|null
     */
    public function aggregateScoresFromSetReplays(Set $set): ?array
    {
        $set->loadMissing(['team1.user', 'team2.user']);

        $u1 = ShowdownUsernameNormalizer::normalize($set->team1?->user?->showdown_username);
        $u2 = ShowdownUsernameNormalizer::normalize($set->team2?->user?->showdown_username);
        if ($u1 === null || $u2 === null) {
            return null;
        }

        $team1Wins = 0;
        $team2Wins = 0;

        foreach ([$set->replay1, $set->replay2, $set->replay3] as $url) {
            if (! is_string($url) || trim($url) === '') {
                continue;
            }

            try {
                $logUrl = ShowdownReplayLogUrl::resolveLogDownloadUrl($url);
                $logText = $this->logFetcher->fetch($logUrl);
            } catch (\Throwable) {
                return null;
            }

            $parsed = $this->winnerParser->parse($logText);
            if ($parsed['errors'] !== [] || $parsed['is_tie']) {
                return null;
            }

            $w = ShowdownUsernameNormalizer::normalize($parsed['winner']);
            if ($w === null) {
                return null;
            }

            if ($w === $u1) {
                $team1Wins++;
            } elseif ($w === $u2) {
                $team2Wins++;
            } else {
                return null;
            }

            if ($team1Wins >= 2) {
                return ['team1_score' => 2, 'team2_score' => min($team2Wins, 1)];
            }
            if ($team2Wins >= 2) {
                return ['team1_score' => min($team1Wins, 1), 'team2_score' => 2];
            }
        }

        return null;
    }
}
