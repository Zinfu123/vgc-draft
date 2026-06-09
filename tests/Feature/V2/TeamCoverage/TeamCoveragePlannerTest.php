<?php

use App\Models\User;
use App\Modules\Draft\Models\DraftConfig;
use App\Modules\League\Models\League;
use App\Modules\League\Models\LeaguePokemon;
use App\Modules\Matches\Models\MatchConfig;
use App\Modules\Matches\Models\Pool;
use App\Modules\Pokedex\Models\Pokedex;
use App\Modules\Teams\Models\Team;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('redirects guests from the v2 planner page', function () {
    $this->get(route('v2.team-coverage.index'))->assertRedirect(route('login'));
});

it('shows the v2 planner for authenticated users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('v2.team-coverage.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('v2/team-coverage/TeamCoveragePlanner')
            ->has('versionGroups')
            ->has('defaultVersionSlug')
            ->has('typeOrder')
            ->has('myTeams')
        );
});

it('returns v2 pokedex search json for authenticated users', function () {
    Pokedex::query()->create([
        'nationaldex_id' => 99901,
        'name' => 'CoverageSearchMon',
        'type1' => 'Fire',
    ]);

    $user = User::factory()->create();
    $response = $this->actingAs($user)->getJson(route('v2.team-coverage.pokedex-search', ['search' => 'Coverage']));
    $response->assertOk()->assertJsonStructure(['data', 'current_page']);
});

it('returns v2 learnset json for authenticated users', function () {
    $dex = Pokedex::query()->create([
        'nationaldex_id' => 99902,
        'name' => 'CoverageLearnMon',
        'type1' => 'Water',
    ]);

    $user = User::factory()->create();
    $response = $this->actingAs($user)->getJson(route('v2.team-coverage.learnset', ['pokedex' => $dex->id]));
    $response->assertOk()->assertJsonStructure(['pokemon', 'game', 'abilities', 'learnset']);
});

function createV2TeamCoverageLeagueWithCoachTeam(): array
{
    $owner = User::factory()->create();
    $coach = User::factory()->create();

    $league = League::query()->create([
        'name' => 'Coverage League',
        'status' => 1,
        'league_owner' => $owner->id,
    ]);

    DraftConfig::query()->create([
        'league_id' => $league->id,
        'draft_date' => now()->addDay(),
        'draft_points' => 80,
        'ban_enabled' => false,
        'minimum_drafts' => 2,
    ]);

    $matchConfig = MatchConfig::query()->create([
        'league_id' => $league->id,
        'number_of_pools' => 1,
        'status' => 1,
    ]);

    $pool = Pool::query()->create([
        'league_id' => $league->id,
        'match_config_id' => $matchConfig->id,
        'status' => 1,
    ]);

    $team = Team::query()->create([
        'name' => 'Coverage Squad',
        'league_id' => $league->id,
        'user_id' => $coach->id,
        'admin_flag' => 0,
        'pick_position' => 1,
        'seed' => 1,
        'pool_id' => $pool->id,
        'draft_points' => 80,
        'victory_points' => 0,
        'set_wins' => 0,
        'set_losses' => 0,
        'game_wins' => 0,
        'game_losses' => 0,
        'trades' => 3,
    ]);

    return [$coach, $team];
}

it('returns v2 roster json for the owning user', function () {
    [$coach, $team] = createV2TeamCoverageLeagueWithCoachTeam();

    $dex = Pokedex::query()->create([
        'nationaldex_id' => 99903,
        'name' => 'RosterMon',
        'type1' => 'Grass',
    ]);

    LeaguePokemon::query()->create([
        'league_id' => $team->league_id,
        'pokedex_id' => $dex->id,
        'name' => $dex->name,
        'cost' => 10,
        'is_drafted' => true,
        'drafted_by' => $team->id,
        'banned' => false,
    ]);

    $response = $this->actingAs($coach)->getJson(route('v2.team-coverage.roster', ['team' => $team->id]));
    $response->assertOk()
        ->assertJsonPath('team_id', $team->id)
        ->assertJsonStructure(['version_group_slug', 'slots']);
});

it('forbids v2 roster fetch for another users team', function () {
    [$coach, $team] = createV2TeamCoverageLeagueWithCoachTeam();
    $intruder = User::factory()->create();

    $this->actingAs($intruder)->getJson(route('v2.team-coverage.roster', ['team' => $team->id]))->assertForbidden();
});

it('runs team coverage module audit command', function () {
    $this->artisan('module:audit', ['module' => 'TeamCoverage'])
        ->expectsOutput('Module audit: TeamCoverage')
        ->assertSuccessful();
});
