<?php

use App\Models\User;
use App\Modules\League\Models\League;
use App\Modules\Teams\Models\Team;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('redirects the league detail root to dashboard', function () {
    $user = User::factory()->create();
    $league = League::create([
        'name' => 'Test League',
        'league_owner' => $user->id,
        'status' => \App\Modules\League\Enums\LeagueStatus::RegularSeason->value,
        'set_frequency' => 3,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('leagues.detail', ['league' => $league->id]));

    $response->assertRedirect(route('leagues.dashboard', ['league' => $league->id]));
});

it('redirects the old teams route to rosters', function () {
    $user = User::factory()->create();
    $league = League::create([
        'name' => 'Test League',
        'league_owner' => $user->id,
        'status' => \App\Modules\League\Enums\LeagueStatus::RegularSeason->value,
        'set_frequency' => 3,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('leagues.teams', ['league' => $league->id]));

    $response->assertRedirect(route('leagues.rosters', ['league' => $league->id]));
});

it('redirects the old matches route to schedule', function () {
    $user = User::factory()->create();
    $league = League::create([
        'name' => 'Test League',
        'league_owner' => $user->id,
        'status' => \App\Modules\League\Enums\LeagueStatus::RegularSeason->value,
        'set_frequency' => 3,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('leagues.matches', ['league' => $league->id]));

    $response->assertRedirect(route('leagues.schedule', ['league' => $league->id, 'view' => 'matches']));
});

it('redirects the old matches route to schedule preserving team filter', function () {
    $user = User::factory()->create();
    $league = League::create([
        'name' => 'Test League',
        'league_owner' => $user->id,
        'status' => \App\Modules\League\Enums\LeagueStatus::RegularSeason->value,
        'set_frequency' => 3,
    ]);
    $team = Team::create([
        'name' => 'Test Team',
        'league_id' => $league->id,
        'user_id' => $user->id,
        'pick_position' => 1,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('leagues.matches', ['league' => $league->id, 'team' => $team->id]));

    $response->assertRedirect(route('leagues.schedule', [
        'league' => $league->id,
        'view' => 'matches',
        'team' => $team->id,
    ]));
});

it('redirects the old standings route to schedule with standings view', function () {
    $user = User::factory()->create();
    $league = League::create([
        'name' => 'Test League',
        'league_owner' => $user->id,
        'status' => \App\Modules\League\Enums\LeagueStatus::RegularSeason->value,
        'set_frequency' => 3,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('leagues.standings', ['league' => $league->id]));

    $response->assertRedirect(route('leagues.schedule', ['league' => $league->id, 'view' => 'standings']));
});

it('redirects the old playoffs route to schedule with playoffs view', function () {
    $user = User::factory()->create();
    $league = League::create([
        'name' => 'Test League',
        'league_owner' => $user->id,
        'status' => \App\Modules\League\Enums\LeagueStatus::RegularSeason->value,
        'set_frequency' => 3,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('leagues.playoffs', ['league' => $league->id]));

    $response->assertRedirect(route('leagues.schedule', ['league' => $league->id, 'view' => 'playoffs']));
});

it('unauthenticated users are redirected to login for league routes', function () {
    $this->get('/leagues/1/dashboard')->assertRedirect('/login');
    $this->get('/leagues/1/rosters')->assertRedirect('/login');
    $this->get('/leagues/1/schedule')->assertRedirect('/login');
});
