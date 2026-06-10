<?php

namespace App\Console\Commands;

use App\Modules\League\Enums\LeagueStatus;
use App\Modules\League\Models\League;
use App\Modules\Matches\Models\Set;
use App\Notifications\MatchUnplayedReminderNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;

class NotifyUnplayedMatchesCommand extends Command
{
    protected $signature = 'matches:notify-unplayed';

    protected $description = 'Notify Discord about unplayed matches from the past week\'s rounds.';

    public function handle(): int
    {
        $leagues = League::query()
            ->where('status', LeagueStatus::RegularSeason->value)
            ->whereNotNull('set_start_date')
            ->whereNotNull('discord_webhook_url')
            ->with('matchConfig')
            ->get();

        if ($leagues->isEmpty()) {
            $this->info('No active leagues with a Discord webhook found.');

            return self::SUCCESS;
        }

        $timezone = 'America/New_York';
        $now = Carbon::now($timezone);
        $oneWeekAgo = $now->copy()->subWeek();

        foreach ($leagues as $league) {
            $startDate = Carbon::parse($league->set_start_date, $timezone)->startOfDay();
            $matchConfig = $league->matchConfig;

            $frequencyType = (int) ($matchConfig?->frequency_type ?? 2);
            $frequencyValue = (int) ($matchConfig?->frequency_value ?? 1);

            $rounds = Set::query()
                ->where('league_id', $league->id)
                ->select('round')
                ->distinct()
                ->orderBy('round')
                ->pluck('round');

            foreach ($rounds as $roundNumber) {
                $roundStart = $this->calculateRoundDate($startDate, (int) $roundNumber, $frequencyType, $frequencyValue);
                $roundEnd = $this->calculateRoundDate($startDate, (int) $roundNumber + 1, $frequencyType, $frequencyValue)->subDay();

                // Only remind about rounds whose window ended within the past 7 days
                if ($roundEnd->gt($now) || $roundEnd->lt($oneWeekAgo)) {
                    continue;
                }

                $unplayedSets = Set::query()
                    ->where('league_id', $league->id)
                    ->where('round', $roundNumber)
                    ->where('is_bye', false)
                    ->whereNull('winner_id')
                    ->with(['team1.user', 'team2.user'])
                    ->get();

                if ($unplayedSets->isEmpty()) {
                    continue;
                }

                $roundLabel = "Round {$roundNumber}";

                $league->notify(new MatchUnplayedReminderNotification($unplayedSets, $roundLabel));

                $this->info("League {$league->id} ({$league->name}): sent reminder for {$roundLabel} ({$unplayedSets->count()} unplayed matches).");
            }
        }

        return self::SUCCESS;
    }

    private function calculateRoundDate(Carbon $startDate, int $roundNumber, int $frequencyType, int $frequencyValue): Carbon
    {
        $offset = $roundNumber - 1;

        return match ($frequencyType) {
            1 => $startDate->copy()->addDays($offset),
            2 => $startDate->copy()->addWeeks($offset),
            3 => $startDate->copy(),
            4 => $startDate->copy()->addDays($offset * max(1, $frequencyValue)),
            default => $startDate->copy()->addWeeks($offset),
        };
    }
}
