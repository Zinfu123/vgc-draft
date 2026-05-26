<?php

use App\Models\User;
use App\Modules\Draft\Models\Draft;
use App\Modules\Draft\Models\DraftConfig;
use App\Modules\League\Enums\LeagueStagingStatus;
use App\Modules\League\Enums\LeagueStatus;
use App\Modules\League\Models\League;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * @return array{0: League, 1: User}
 */
function makeStagingLeagueForSeasonStart(string $setStartDate, ?LeagueStagingStatus $stagingSubStatus = LeagueStagingStatus::FreeTradeWindow): array
{
    $owner = User::factory()->create();

    $league = League::create([
        'name' => 'Season Start League',
        'status' => LeagueStatus::Staging->value,
        'staging_sub_status' => $stagingSubStatus->value,
        'league_owner' => $owner->id,
        'maximum_teams' => 10,
        'set_start_date' => $setStartDate,
        'pokemon_generation' => 9,
        'pokemon_game' => 'scarlet_violet',
    ]);

    DraftConfig::create([
        'league_id' => $league->id,
        'draft_date' => now()->subWeek()->toDateString(),
        'draft_points' => 80,
        'minimum_drafts' => 1,
        'ban_enabled' => false,
    ]);

    Draft::create([
        'league_id' => $league->id,
        'status' => 0,
        'round_number' => 1,
        'pick_number' => 1,
    ]);

    return [$league, $owner];
}

it('is scheduled to run every five minutes', function () {
    $schedule = app(Schedule::class);
    $descriptions = collect($schedule->events())->pluck('description');

    expect($descriptions)->toContain('leagues-start-scheduled-regular-season');
});

it('starts the regular season when set_start_date has arrived and the draft is complete', function () {
    Carbon::setTestNow('2026-05-26 12:00:00');

    [$league] = makeStagingLeagueForSeasonStart('2026-05-25');

    $this->artisan('leagues:start-scheduled-regular-season')->assertSuccessful();

    $league->refresh();

    expect($league->status)->toBe(LeagueStatus::RegularSeason)
        ->and($league->staging_sub_status)->toBeNull();

    Carbon::setTestNow();
});

it('starts the regular season on the season start date itself', function () {
    Carbon::setTestNow('2026-05-25 08:00:00');

    [$league] = makeStagingLeagueForSeasonStart('2026-05-25');

    $this->artisan('leagues:start-scheduled-regular-season')->assertSuccessful();

    expect($league->fresh()->status)->toBe(LeagueStatus::RegularSeason);

    Carbon::setTestNow();
});

it('does not start the regular season when set_start_date is in the future', function () {
    Carbon::setTestNow('2026-05-24 12:00:00');

    [$league] = makeStagingLeagueForSeasonStart('2026-05-25');

    $this->artisan('leagues:start-scheduled-regular-season')->assertSuccessful();

    expect($league->fresh()->status)->toBe(LeagueStatus::Staging);

    Carbon::setTestNow();
});

it('skips leagues whose draft is still in progress', function () {
    Carbon::setTestNow('2026-05-26 12:00:00');

    [$league] = makeStagingLeagueForSeasonStart('2026-05-25', LeagueStagingStatus::DraftInProgress);

    Draft::query()->where('league_id', $league->id)->update(['status' => 1]);

    $this->artisan('leagues:start-scheduled-regular-season')->assertSuccessful();

    expect($league->fresh()->status)->toBe(LeagueStatus::Staging);

    Carbon::setTestNow();
});

it('skips leagues that are not in staging', function () {
    Carbon::setTestNow('2026-05-26 12:00:00');

    [$league] = makeStagingLeagueForSeasonStart('2026-05-25');
    $league->update(['status' => LeagueStatus::RegularSeason->value]);

    $this->artisan('leagues:start-scheduled-regular-season')->assertSuccessful();

    expect($league->fresh()->status)->toBe(LeagueStatus::RegularSeason);

    Carbon::setTestNow();
});

it('reports an error when starting regular season manually before the draft is complete', function () {
    [$league, $owner] = makeStagingLeagueForSeasonStart('2026-05-25', LeagueStagingStatus::DraftInProgress);

    Draft::query()->where('league_id', $league->id)->update(['status' => 1]);

    $this->actingAs($owner)->post(route('leagues.start-regular-season', $league))
        ->assertRedirect()
        ->assertSessionHasErrors(['league']);

    expect($league->fresh()->status)->toBe(LeagueStatus::Staging);
});
