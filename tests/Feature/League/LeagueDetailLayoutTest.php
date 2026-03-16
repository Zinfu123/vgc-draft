<?php

use App\Models\User;
use App\Modules\League\Models\League;
use App\Modules\Teams\Models\Team;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function createLeagueAndTeamForUser(User $user, int $adminFlag = 0): League
{
    $league = League::create([
        'name' => 'Test League',
        'status' => 1,
        'draft_points' => 100,
        'league_owner' => $user->id,
    ]);

    Team::create([
        'name' => 'My Team',
        'league_id' => $league->id,
        'user_id' => $user->id,
        'admin_flag' => $adminFlag,
        'pick_position' => 1,
        'draft_points' => 100,
        'victory_points' => 0,
        'set_wins' => 0,
        'set_losses' => 0,
        'game_wins' => 0,
        'game_losses' => 0,
    ]);

    return $league;
}

it('renders the teams tab', function () {
    $user = User::factory()->create();
    $league = createLeagueAndTeamForUser($user);

    $response = $this->actingAs($user)->get("/leagues/{$league->id}/teams");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('league/LeagueDetailTeams')
        ->has('league')
        ->has('teams')
        ->has('adminFlag')
        ->has('matchConfig')
        ->where('section', 'teams')
    );
});

it('renders the matches tab', function () {
    $user = User::factory()->create();
    $league = createLeagueAndTeamForUser($user);

    $response = $this->actingAs($user)->get("/leagues/{$league->id}/matches");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('league/LeagueDetailMatches')
        ->has('league')
        ->has('teams')
        ->has('adminFlag')
        ->has('matchConfig')
        ->where('section', 'matches')
    );
});

it('renders the standings tab', function () {
    $user = User::factory()->create();
    $league = createLeagueAndTeamForUser($user);

    $response = $this->actingAs($user)->get("/leagues/{$league->id}/standings");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('league/LeagueDetailStandings')
        ->has('league')
        ->has('teams')
        ->has('adminFlag')
        ->has('matchConfig')
        ->where('section', 'standings')
    );
});

it('renders the trades tab', function () {
    $user = User::factory()->create();
    $league = createLeagueAndTeamForUser($user);

    $response = $this->actingAs($user)->get("/leagues/{$league->id}/trades");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('league/LeagueDetailTrades')
        ->has('league')
        ->has('teams')
        ->has('adminFlag')
        ->has('matchConfig')
        ->where('section', 'trades')
    );
});

it('renders the draft tab', function () {
    $user = User::factory()->create();
    $league = createLeagueAndTeamForUser($user);

    $response = $this->actingAs($user)->get("/leagues/{$league->id}/draft");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('league/LeagueDetailDraft')
        ->has('league')
        ->has('teams')
        ->has('adminFlag')
        ->has('matchConfig')
        ->where('section', 'draft')
    );
});

it('requires authentication on all detail tabs', function (string $tab) {
    $owner = User::factory()->create();
    $league = createLeagueAndTeamForUser($owner);

    $this->get("/leagues/{$league->id}/{$tab}")->assertRedirect('/login');
})->with(['teams', 'matches', 'standings', 'trades', 'draft']);
