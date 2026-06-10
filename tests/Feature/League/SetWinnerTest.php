<?php

use App\Models\User;
use App\Modules\League\Enums\LeagueStatus;
use App\Modules\League\Models\League;
use App\Modules\Teams\Models\Team;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function createRegularSeasonLeagueWithTeam(): array
{
    $owner = User::factory()->create();

    $league = League::create([
        'name' => 'Test League',
        'status' => LeagueStatus::RegularSeason->value,
        'draft_points' => 100,
        'league_owner' => $owner->id,
        'playoffs_enabled' => false,
    ]);

    $teamUser = User::factory()->create();

    Team::create([
        'name' => 'Team A',
        'league_id' => $league->id,
        'user_id' => $teamUser->id,
        'admin_flag' => 1,
        'pick_position' => 1,
        'draft_points' => 100,
        'victory_points' => 10,
        'set_wins' => 5,
        'set_losses' => 0,
        'game_wins' => 0,
        'game_losses' => 0,
    ]);

    return [$owner, $league, $teamUser];
}

it('finalizes the regular season and auto-sets the winner from standings', function () {
    [, $league, $teamUser] = createRegularSeasonLeagueWithTeam();

    $response = $this->actingAs($teamUser)->post(route('leagues.finalize', $league));

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $league->refresh();
    expect($league->winner)->toBe($teamUser->id)
        ->and($league->status)->toBe(LeagueStatus::Completed);
});

it('forbids finalizing when playoffs are enabled', function () {
    $owner = User::factory()->create();
    $league = League::create([
        'name' => 'Playoffs League',
        'status' => LeagueStatus::RegularSeason->value,
        'draft_points' => 100,
        'league_owner' => $owner->id,
        'playoffs_enabled' => true,
    ]);
    Team::create([
        'name' => 'Team A',
        'league_id' => $league->id,
        'user_id' => $owner->id,
        'admin_flag' => 1,
        'pick_position' => 1,
        'draft_points' => 100,
        'victory_points' => 0,
        'set_wins' => 0,
        'set_losses' => 0,
        'game_wins' => 0,
        'game_losses' => 0,
    ]);

    $response = $this->actingAs($owner)->post(route('leagues.finalize', $league));

    $response->assertRedirect();
    $response->assertSessionHasErrors(['league']);
});

it('forbids finalizing when not in regular season', function () {
    [, $league, $teamUser] = createRegularSeasonLeagueWithTeam();
    $league->status = LeagueStatus::Registration;
    $league->save();

    $response = $this->actingAs($teamUser)->post(route('leagues.finalize', $league));

    $response->assertRedirect();
    $response->assertSessionHasErrors(['league']);
});

it('forbids non-admin league members from finalizing', function () {
    [, $league, $teamUser] = createRegularSeasonLeagueWithTeam();

    $plainCoach = User::factory()->create();
    Team::create([
        'name' => 'Team B',
        'league_id' => $league->id,
        'user_id' => $plainCoach->id,
        'admin_flag' => 0,
        'pick_position' => 2,
        'draft_points' => 100,
        'victory_points' => 0,
        'set_wins' => 0,
        'set_losses' => 0,
        'game_wins' => 0,
        'game_losses' => 0,
    ]);

    $response = $this->actingAs($plainCoach)->post(route('leagues.finalize', $league));

    $response->assertForbidden();
});

it('requires authentication to finalize a league', function () {
    [, $league] = createRegularSeasonLeagueWithTeam();

    $response = $this->post(route('leagues.finalize', $league));

    $response->assertRedirect('/login');
});
