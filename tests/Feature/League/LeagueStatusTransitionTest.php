<?php

use App\Enums\Playoffs\PlayoffFormat;
use App\Enums\Playoffs\PlayoffStatus;
use App\Models\User;
use App\Modules\League\Enums\LeagueStagingStatus;
use App\Modules\League\Enums\LeagueStatus;
use App\Modules\League\Models\League;
use App\Modules\Playoffs\Models\Playoff;
use App\Modules\Playoffs\Models\PlayoffMatch;
use App\Modules\Teams\Models\Team;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function makeAdminLeague(int $status, bool $playoffsEnabled = true): array
{
    $owner = User::factory()->create();
    $league = League::create([
        'name' => 'Test League',
        'league_owner' => $owner->id,
        'status' => $status,
        'playoffs_enabled' => $playoffsEnabled,
        'free_trade_window_hours' => 24,
    ]);
    Team::create([
        'name' => 'Owner Team',
        'league_id' => $league->id,
        'user_id' => $owner->id,
        'admin_flag' => 1,
        'pick_position' => 1,
        'draft_points' => 80,
        'victory_points' => 10,
        'set_wins' => 5,
        'set_losses' => 0,
        'game_wins' => 0,
        'game_losses' => 0,
    ]);

    return [$owner, $league];
}

// LeagueStatus enum values
it('LeagueStatus enum has correct integer values', function () {
    expect(LeagueStatus::Cancelled->value)->toBe(0)
        ->and(LeagueStatus::Completed->value)->toBe(1)
        ->and(LeagueStatus::Registration->value)->toBe(2)
        ->and(LeagueStatus::Staging->value)->toBe(3)
        ->and(LeagueStatus::RegularSeason->value)->toBe(4)
        ->and(LeagueStatus::Playoffs->value)->toBe(5);
});

it('LeagueStatus isActive returns true for active statuses', function () {
    expect(LeagueStatus::Registration->isActive())->toBeTrue()
        ->and(LeagueStatus::Staging->isActive())->toBeTrue()
        ->and(LeagueStatus::RegularSeason->isActive())->toBeTrue()
        ->and(LeagueStatus::Playoffs->isActive())->toBeTrue()
        ->and(LeagueStatus::Completed->isActive())->toBeFalse()
        ->and(LeagueStatus::Cancelled->isActive())->toBeFalse();
});

it('LeagueStatus isVisible returns false only for Cancelled', function () {
    expect(LeagueStatus::Cancelled->isVisible())->toBeFalse()
        ->and(LeagueStatus::Completed->isVisible())->toBeTrue()
        ->and(LeagueStatus::Registration->isVisible())->toBeTrue();
});

it('League model casts status to LeagueStatus enum', function () {
    [$owner, $league] = makeAdminLeague(LeagueStatus::RegularSeason->value);

    $league->refresh();
    expect($league->status)->toBe(LeagueStatus::RegularSeason)
        ->and($league->status->label())->toBe('Regular Season');
});

// Cancel league
it('commissioner can cancel a league', function () {
    [$owner, $league] = makeAdminLeague(LeagueStatus::RegularSeason->value);

    $this->actingAs($owner)->post(route('leagues.cancel', $league))
        ->assertRedirect(route('leagues.index'));

    expect($league->fresh()->status)->toBe(LeagueStatus::Cancelled);
});

it('cannot cancel a completed league', function () {
    [$owner, $league] = makeAdminLeague(LeagueStatus::Completed->value);

    $this->actingAs($owner)->post(route('leagues.cancel', $league))
        ->assertRedirect()
        ->assertSessionHasErrors(['league']);
});

it('non-admin cannot cancel a league', function () {
    [$owner, $league] = makeAdminLeague(LeagueStatus::RegularSeason->value);
    $outsider = User::factory()->create();

    $this->actingAs($outsider)->post(route('leagues.cancel', $league))
        ->assertForbidden();
});

// Start regular season
it('commissioner can start the regular season from Staging', function () {
    [$owner, $league] = makeAdminLeague(LeagueStatus::Staging->value);
    $league->staging_sub_status = LeagueStagingStatus::FreeTradeWindow;
    $league->save();

    $this->actingAs($owner)->post(route('leagues.start-regular-season', $league))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $league->refresh();
    expect($league->status)->toBe(LeagueStatus::RegularSeason)
        ->and($league->staging_sub_status)->toBeNull();
});

it('cannot start regular season from non-Staging status', function () {
    [$owner, $league] = makeAdminLeague(LeagueStatus::Registration->value);

    $this->actingAs($owner)->post(route('leagues.start-regular-season', $league))
        ->assertRedirect()
        ->assertSessionHasErrors(['league']);
});

// Finalize regular season (no playoffs)
it('commissioner can finalize a no-playoff league from standings', function () {
    [$owner, $league] = makeAdminLeague(LeagueStatus::RegularSeason->value, playoffsEnabled: false);

    $this->actingAs($owner)->post(route('leagues.finalize', $league))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $league->refresh();
    expect($league->status)->toBe(LeagueStatus::Completed)
        ->and($league->winner)->toBe($owner->id);
});

it('cannot finalize when playoffs are enabled', function () {
    [$owner, $league] = makeAdminLeague(LeagueStatus::RegularSeason->value, playoffsEnabled: true);

    $this->actingAs($owner)->post(route('leagues.finalize', $league))
        ->assertRedirect()
        ->assertSessionHasErrors(['league']);
});

it('cannot finalize when not in regular season', function () {
    [$owner, $league] = makeAdminLeague(LeagueStatus::Staging->value, playoffsEnabled: false);

    $this->actingAs($owner)->post(route('leagues.finalize', $league))
        ->assertRedirect()
        ->assertSessionHasErrors(['league']);
});

// Start playoffs
it('commissioner can start playoffs when bracket is ready', function () {
    [$owner, $league] = makeAdminLeague(LeagueStatus::RegularSeason->value, playoffsEnabled: true);

    $playoff = Playoff::create([
        'league_id' => $league->id,
        'format' => PlayoffFormat::SingleElimination,
        'bracket_size' => 2,
        'status' => PlayoffStatus::Draft,
        'seed_order' => null,
    ]);

    $otherUser = User::factory()->create();
    $otherTeam = Team::create([
        'name' => 'Other Team',
        'league_id' => $league->id,
        'user_id' => $otherUser->id,
        'admin_flag' => 0,
        'pick_position' => 2,
        'draft_points' => 80,
    ]);

    $ownerTeam = Team::where('league_id', $league->id)->where('user_id', $owner->id)->first();

    PlayoffMatch::create([
        'playoff_id' => $playoff->id,
        'slot' => 'r0-0',
        'round_index' => 0,
        'sort_order' => 0,
        'is_bronze' => false,
        'team1_id' => $ownerTeam->id,
        'team2_id' => $otherTeam->id,
    ]);

    $this->actingAs($owner)->post(route('leagues.start-playoffs', $league))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $league->refresh();
    expect($league->status)->toBe(LeagueStatus::Playoffs);
    expect($playoff->fresh()->status)->toBe(PlayoffStatus::Active);
});

it('cannot start playoffs when playoffs are disabled', function () {
    [$owner, $league] = makeAdminLeague(LeagueStatus::RegularSeason->value, playoffsEnabled: false);

    $this->actingAs($owner)->post(route('leagues.start-playoffs', $league))
        ->assertRedirect()
        ->assertSessionHasErrors(['league']);
});

it('cannot start playoffs when not in regular season', function () {
    [$owner, $league] = makeAdminLeague(LeagueStatus::Staging->value, playoffsEnabled: true);

    $this->actingAs($owner)->post(route('leagues.start-playoffs', $league))
        ->assertRedirect()
        ->assertSessionHasErrors(['league']);
});

it('requires authentication for all status transitions', function (string $route) {
    [$owner, $league] = makeAdminLeague(LeagueStatus::RegularSeason->value);

    $this->post(route($route, $league))
        ->assertRedirect('/login');
})->with([
    'leagues.cancel',
    'leagues.start-regular-season',
    'leagues.start-playoffs',
    'leagues.finalize',
]);
