<?php

use App\Models\User;
use App\Modules\Draft\Models\DraftConfig;
use App\Modules\League\Models\League;
use App\Modules\Matches\Models\MatchConfig;
use App\Modules\Matches\Models\Pool;
use App\Modules\Teams\Models\Team;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function createLeagueWithPool(): array
{
    $league = League::create([
        'name' => 'Test League',
        'status' => 1,
        'draft_points' => 80,
        'league_owner' => 1,
        'maximum_teams' => 10,
    ]);

    DraftConfig::create([
        'league_id' => $league->id,
        'draft_date' => '2026-04-01',
        'draft_points' => 80,
        'ban_enabled' => false,
    ]);

    $matchConfig = MatchConfig::create([
        'league_id' => $league->id,
        'enforce_round_count' => false,
    ]);

    $pool = Pool::create([
        'league_id' => $league->id,
        'match_config_id' => $matchConfig->id,
    ]);

    return [$league, $pool];
}

it('assigns a new team to the default pool', function () {
    $user = User::factory()->create(['showdown_username' => 'PoolCoach']);
    [$league, $pool] = createLeagueWithPool();

    $this->actingAs($user)->post('/teams', [
        'name' => 'My Team',
        'league_id' => $league->id,
        'user_id' => $user->id,
        'pick_position' => 1,
    ])->assertRedirect();

    $team = Team::where('league_id', $league->id)->where('user_id', $user->id)->first();
    expect($team)->not->toBeNull();
    expect($team->pool_id)->toBe($pool->id);
    expect($team->showdown_username)->toBeNull();
});

it('does not fail team creation when no pool exists for the league', function () {
    $user = User::factory()->create(['showdown_username' => 'NoPoolCoach']);

    $league = League::create([
        'name' => 'No Pool League',
        'status' => 1,
        'draft_points' => 80,
        'league_owner' => $user->id,
        'maximum_teams' => 10,
    ]);

    DraftConfig::create([
        'league_id' => $league->id,
        'draft_date' => '2026-04-01',
        'draft_points' => 80,
        'ban_enabled' => false,
    ]);

    $this->actingAs($user)->post('/teams', [
        'name' => 'My Team',
        'league_id' => $league->id,
        'user_id' => $user->id,
        'pick_position' => 1,
    ])->assertRedirect();

    $team = Team::where('league_id', $league->id)->where('user_id', $user->id)->first();
    expect($team)->not->toBeNull();
    expect($team->pool_id)->toBeNull();
});

it('rejects team creation when coach has no showdown username and none is sent for the team', function () {
    $user = User::factory()->create(['showdown_username' => null]);
    [$league] = createLeagueWithPool();

    $this->actingAs($user)->post('/teams', [
        'name' => 'No Showdown Team',
        'league_id' => $league->id,
        'user_id' => $user->id,
        'pick_position' => 1,
        'showdown_username' => '',
    ])->assertSessionHasErrors('showdown_username');
});

it('allows team creation with only a team-level showdown username', function () {
    $user = User::factory()->create(['showdown_username' => null]);
    [$league] = createLeagueWithPool();

    $this->actingAs($user)->post('/teams', [
        'name' => 'Solo Showdown',
        'league_id' => $league->id,
        'user_id' => $user->id,
        'pick_position' => 1,
        'showdown_username' => 'TeamOnlySD',
    ])->assertRedirect();

    $team = Team::where('league_id', $league->id)->where('user_id', $user->id)->first();
    expect($team)->not->toBeNull();
    expect($team->showdown_username)->toBe('TeamOnlySD');
});

it('stores a team showdown override on edit when different from profile', function () {
    $user = User::factory()->create(['showdown_username' => 'ProfileSD']);
    [$league] = createLeagueWithPool();
    $team = Team::create([
        'name' => 'Alpha',
        'league_id' => $league->id,
        'user_id' => $user->id,
        'pick_position' => 1,
        'draft_points' => 80,
    ]);

    $this->actingAs($user)->post(route('teams.edit', ['team_id' => $team->id]), [
        'name' => 'Alpha II',
        'showdown_username' => 'OverrideSD',
    ])->assertRedirect();

    expect($team->fresh()->name)->toBe('Alpha II');
    expect($team->fresh()->showdown_username)->toBe('OverrideSD');
});
