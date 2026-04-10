<?php

use App\Models\User;
use App\Modules\Draft\Models\Draft;
use App\Modules\Draft\Models\DraftConfig;
use App\Modules\League\Models\League;
use App\Modules\Teams\Models\Team;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * @return array{0: League, 1: DraftConfig, 2: User}
 */
function makeScheduledLeague(?Carbon $draftStartAt): array
{
    $user = User::factory()->create(['showdown_username' => 'trainer1']);
    $league = League::create([
        'name' => 'Scheduled League',
        'status' => 1,
        'league_owner' => $user->id,
        'maximum_teams' => 10,
        'pokemon_generation' => 9,
        'pokemon_game' => 'scarlet_violet',
    ]);
    $config = DraftConfig::create([
        'league_id' => $league->id,
        'draft_date' => now()->toDateString(),
        'draft_start_at' => $draftStartAt,
        'draft_points' => 80,
        'minimum_drafts' => 1,
        'ban_enabled' => false,
    ]);
    Team::create([
        'name' => 'Team 1',
        'league_id' => $league->id,
        'user_id' => $user->id,
        'admin_flag' => 1,
        'pick_position' => 1,
        'draft_points' => 80,
        'victory_points' => 0,
        'set_wins' => 0,
        'set_losses' => 0,
        'game_wins' => 0,
        'game_losses' => 0,
    ]);

    return [$league, $config, $user];
}

it('is scheduled to run every minute', function () {
    $schedule = app(Schedule::class);
    $descriptions = collect($schedule->events())->pluck('description');

    expect($descriptions)->toContain('draft-start-scheduled');
});

it('starts a draft when draft_start_at has passed', function () {
    Notification::fake();

    [$league] = makeScheduledLeague(Carbon::now()->subMinute());

    $this->artisan('draft:start-scheduled')->assertSuccessful();

    expect(Draft::where('league_id', $league->id)->exists())->toBeTrue();
});

it('does not start a draft when draft_start_at is in the future', function () {
    Notification::fake();

    [$league] = makeScheduledLeague(Carbon::now()->addHour());

    $this->artisan('draft:start-scheduled')->assertSuccessful();

    expect(Draft::where('league_id', $league->id)->exists())->toBeFalse();
});

it('skips leagues with no draft_start_at', function () {
    Notification::fake();

    [$league] = makeScheduledLeague(null);

    $this->artisan('draft:start-scheduled')->assertSuccessful();

    expect(Draft::where('league_id', $league->id)->exists())->toBeFalse();
});

it('skips a league that already has a draft started', function () {
    Notification::fake();

    [$league] = makeScheduledLeague(Carbon::now()->subMinute());

    Draft::create([
        'league_id' => $league->id,
        'status' => 1,
        'round_number' => 1,
        'pick_number' => 1,
    ]);

    $this->artisan('draft:start-scheduled')->assertSuccessful();

    expect(Draft::where('league_id', $league->id)->count())->toBe(1);
});
