<?php

use App\Models\User;
use App\Modules\Draft\Models\DraftConfig;
use App\Modules\League\Models\League;
use App\Modules\Matches\Models\MatchConfig;
use App\Modules\Matches\Models\Pool;
use App\Modules\Teams\Models\Team;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function createTeamsPreviewLeague(): array
{
    $league = League::create([
        'name' => 'V2 Teams League',
        'status' => \App\Modules\League\Enums\LeagueStatus::Registration->value,
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

it('renders v2 teams index page', function () {
    $user = User::factory()->create();
    [$league] = createTeamsPreviewLeague();

    $this->actingAs($user)
        ->get("/v2/teams?league_id={$league->id}")
        ->assertSuccessful();
});

it('creates a team via v2 preview route', function () {
    $user = User::factory()->create(['showdown_username' => 'V2Coach']);
    [$league] = createTeamsPreviewLeague();

    $this->actingAs($user)->post('/v2/teams', [
        'name' => 'V2 Team',
        'league_id' => $league->id,
        'user_id' => $user->id,
        'pick_position' => 1,
    ])->assertRedirect(route('leagues.dashboard', ['league' => $league->id]));

    expect(Team::query()->where('league_id', $league->id)->where('user_id', $user->id)->exists())->toBeTrue();
});

it('redirects v2 team detail to league dashboard', function () {
    $user = User::factory()->create();
    [$league] = createTeamsPreviewLeague();

    $team = Team::create([
        'name' => 'Preview Squad',
        'league_id' => $league->id,
        'user_id' => $user->id,
        'pick_position' => 1,
        'draft_points' => 80,
    ]);

    $this->actingAs($user)
        ->get("/v2/teams/{$team->id}")
        ->assertRedirect(route('leagues.dashboard', ['league' => $league->id, 'team' => $team->id]));
});

it('edits a team via v2 preview route', function () {
    $user = User::factory()->create(['showdown_username' => 'EditCoach']);
    [$league] = createTeamsPreviewLeague();

    $team = Team::create([
        'name' => 'Before',
        'league_id' => $league->id,
        'user_id' => $user->id,
        'pick_position' => 1,
        'draft_points' => 80,
    ]);

    $this->actingAs($user)->post("/v2/teams/{$team->id}", [
        'name' => 'After',
        'showdown_username' => '',
    ])->assertRedirect();

    expect($team->fresh()->name)->toBe('After');
});

it('registers teams module auditor', function () {
    $this->artisan('module:audit Teams')
        ->expectsOutputToContain('Teams')
        ->assertSuccessful();
});

it('requires auth for v2 teams routes', function () {
    $this->get('/v2/teams?league_id=1')->assertRedirect(route('login'));
});
