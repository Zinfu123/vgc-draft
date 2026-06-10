<?php

use App\Models\User;
use App\Modules\League\Enums\LeagueStatus;
use App\Modules\League\Models\League;
use App\Modules\Matches\Models\MatchConfig;
use App\Modules\Matches\Models\Pool;
use App\Modules\Matches\Models\Set;
use App\Modules\Teams\Models\Team;
use App\Notifications\MatchUnplayedReminderNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * @return array{league: League, pool: Pool, teams: array{team1: Team, team2: Team}}
 */
function makeLeagueForUnplayedMatchNotifications(): array
{
    $owner = User::factory()->create();

    $league = League::create([
        'name' => 'Notify League',
        'status' => LeagueStatus::RegularSeason->value,
        'league_owner' => $owner->id,
        'set_start_date' => '2026-05-25',
        'discord_webhook_url' => 'https://discord.com/api/webhooks/test/token',
    ]);

    $matchConfig = MatchConfig::create([
        'league_id' => $league->id,
        'number_of_pools' => 1,
        'frequency_type' => 2,
        'frequency_value' => 1,
        'status' => 1,
    ]);

    $pool = Pool::create([
        'league_id' => $league->id,
        'match_config_id' => $matchConfig->id,
        'status' => 1,
    ]);

    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $team1 = Team::create([
        'name' => 'Team Alpha',
        'league_id' => $league->id,
        'user_id' => $user1->id,
        'pool_id' => $pool->id,
        'pick_position' => 1,
        'seed' => 1,
        'draft_points' => 100,
        'victory_points' => 0,
        'set_wins' => 0,
        'set_losses' => 0,
        'game_wins' => 0,
        'game_losses' => 0,
    ]);

    $team2 = Team::create([
        'name' => 'Team Beta',
        'league_id' => $league->id,
        'user_id' => $user2->id,
        'pool_id' => $pool->id,
        'pick_position' => 2,
        'seed' => 2,
        'draft_points' => 100,
        'victory_points' => 0,
        'set_wins' => 0,
        'set_losses' => 0,
        'game_wins' => 0,
        'game_losses' => 0,
    ]);

    return [
        'league' => $league,
        'pool' => $pool,
        'teams' => ['team1' => $team1, 'team2' => $team2],
    ];
}

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::parse('2026-06-10 12:00:00', 'America/New_York'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('does not notify when round two sets are completed with status zero', function (): void {
    Notification::fake();

    $fixture = makeLeagueForUnplayedMatchNotifications();

    Set::create([
        'league_id' => $fixture['league']->id,
        'pool_id' => $fixture['pool']->id,
        'round' => 2,
        'team1_id' => $fixture['teams']['team1']->id,
        'team2_id' => $fixture['teams']['team2']->id,
        'team1_score' => 0,
        'team2_score' => 2,
        'winner_id' => $fixture['teams']['team2']->id,
        'status' => 0,
    ]);

    $this->artisan('matches:notify-unplayed')->assertSuccessful();

    Notification::assertNothingSent();
});

it('notifies when round two sets are genuinely incomplete', function (): void {
    Notification::fake();

    $fixture = makeLeagueForUnplayedMatchNotifications();

    Set::create([
        'league_id' => $fixture['league']->id,
        'pool_id' => $fixture['pool']->id,
        'round' => 2,
        'team1_id' => $fixture['teams']['team1']->id,
        'team2_id' => $fixture['teams']['team2']->id,
        'status' => 1,
    ]);

    $this->artisan('matches:notify-unplayed')->assertSuccessful();

    Notification::assertSentTo(
        $fixture['league'],
        MatchUnplayedReminderNotification::class,
        fn (MatchUnplayedReminderNotification $notification): bool => $notification->roundLabel === 'Round 2'
            && $notification->sets->count() === 1,
    );
});

it('does not notify when winner is recorded even if status is still open', function (): void {
    Notification::fake();

    $fixture = makeLeagueForUnplayedMatchNotifications();

    Set::create([
        'league_id' => $fixture['league']->id,
        'pool_id' => $fixture['pool']->id,
        'round' => 2,
        'team1_id' => $fixture['teams']['team1']->id,
        'team2_id' => $fixture['teams']['team2']->id,
        'team1_score' => 2,
        'team2_score' => 1,
        'winner_id' => $fixture['teams']['team1']->id,
        'status' => 1,
    ]);

    $this->artisan('matches:notify-unplayed')->assertSuccessful();

    Notification::assertNothingSent();
});
