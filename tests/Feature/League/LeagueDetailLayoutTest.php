<?php

use App\Models\User;
use App\Modules\League\Models\League;
use App\Modules\League\Models\LeaguePokemon;
use App\Modules\Pokedex\Models\Pokedex;
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
        ->has('freeAgencyPool')
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

it('renders the pokemon tab with pokedex ids for links', function () {
    $user = User::factory()->create();
    $league = createLeagueAndTeamForUser($user);
    $dex = Pokedex::query()->create([
        'nationaldex_id' => 561,
        'name' => 'Sigilyph',
        'type1' => 'Psychic',
        'type2' => 'Flying',
        'sprite_url' => null,
    ]);
    LeaguePokemon::query()->create([
        'league_id' => $league->id,
        'pokedex_id' => $dex->id,
        'name' => $dex->name,
        'cost' => 12,
    ]);

    $response = $this->actingAs($user)->get("/leagues/{$league->id}/pokemon");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('league/LeaguePokemonPage')
        ->has('pokemon', 1)
        ->where('pokemon.0.pokedex_id', $dex->id)
        ->where('section', 'pokemon')
    );
});

it('requires authentication on all detail tabs', function (string $tab) {
    $owner = User::factory()->create();
    $league = createLeagueAndTeamForUser($owner);

    $this->get("/leagues/{$league->id}/{$tab}")->assertRedirect('/login');
})->with(['teams', 'matches', 'standings', 'trades', 'draft', 'pokemon']);

it('redirects the admin page to match-config', function () {
    $user = User::factory()->create();
    $league = createLeagueAndTeamForUser($user, adminFlag: 1);

    $response = $this->actingAs($user)->get("/leagues/{$league->id}/admin");

    $response->assertRedirect("/leagues/{$league->id}/admin/match-config");
});

it('renders the admin match-config page', function () {
    $user = User::factory()->create();
    $league = createLeagueAndTeamForUser($user, adminFlag: 1);

    $response = $this->actingAs($user)->get("/leagues/{$league->id}/admin/match-config");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('league/admin/MatchConfig')
        ->has('league')
        ->has('matchConfig')
    );
});

it('renders the admin discord page', function () {
    $user = User::factory()->create();
    $league = createLeagueAndTeamForUser($user, adminFlag: 1);

    $response = $this->actingAs($user)->get("/leagues/{$league->id}/admin/discord");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('league/admin/Discord')
        ->has('league')
    );
});

it('renders the admin trades page', function () {
    $user = User::factory()->create();
    $league = createLeagueAndTeamForUser($user, adminFlag: 1);

    $response = $this->actingAs($user)->get("/leagues/{$league->id}/admin/trades");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('league/admin/Trades')
        ->has('league')
        ->has('teams')
    );
});

it('renders the admin winner page', function () {
    $user = User::factory()->create();
    $league = createLeagueAndTeamForUser($user, adminFlag: 1);

    $response = $this->actingAs($user)->get("/leagues/{$league->id}/admin/winner");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('league/admin/Winner')
        ->has('league')
        ->has('teams')
    );
});

it('renders the admin reopen match page', function () {
    $user = User::factory()->create();
    $league = createLeagueAndTeamForUser($user, adminFlag: 1);

    $response = $this->actingAs($user)->get("/leagues/{$league->id}/admin/reopen-match");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('league/admin/ReopenMatch')
        ->has('league')
    );
});

it('requires authentication on the admin pages', function (string $path) {
    $owner = User::factory()->create();
    $league = createLeagueAndTeamForUser($owner);

    $this->get("/leagues/{$league->id}/{$path}")->assertRedirect('/login');
})->with(['admin', 'admin/match-config', 'admin/discord', 'admin/trades', 'admin/reopen-match', 'admin/winner']);

it('forbids non-admin users from accessing admin pages', function (string $path) {
    $leagueOwner = User::factory()->create();
    $league = createLeagueAndTeamForUser($leagueOwner, adminFlag: 0);

    $coach = User::factory()->create();
    Team::create([
        'name' => 'Guest Team',
        'league_id' => $league->id,
        'user_id' => $coach->id,
        'admin_flag' => 0,
        'pick_position' => 2,
        'draft_points' => 100,
        'victory_points' => 0,
        'set_wins' => 0,
        'set_losses' => 0,
        'game_wins' => 0,
        'game_losses' => 0,
    ]);

    $this->actingAs($coach)->get("/leagues/{$league->id}/{$path}")->assertForbidden();
})->with(['admin', 'admin/match-config', 'admin/discord', 'admin/trades', 'admin/reopen-match', 'admin/winner']);

it('forbids users not in the league from accessing admin pages', function (string $path) {
    $owner = User::factory()->create();
    $league = createLeagueAndTeamForUser($owner, adminFlag: 1);

    $outsider = User::factory()->create();

    $this->actingAs($outsider)->get("/leagues/{$league->id}/{$path}")->assertForbidden();
})->with(['admin', 'admin/match-config', 'admin/discord', 'admin/trades', 'admin/reopen-match', 'admin/winner']);
