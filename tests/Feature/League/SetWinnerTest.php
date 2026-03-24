<?php

use App\Models\User;
use App\Modules\League\Models\League;
use App\Modules\Teams\Models\Team;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function createLeagueWithOwnerAndTeam(): array
{
    $owner = User::factory()->create();

    $league = League::create([
        'name' => 'Test League',
        'status' => 1,
        'draft_points' => 100,
        'league_owner' => $owner->id,
    ]);

    $teamUser = User::factory()->create();

    Team::create([
        'name' => 'Team A',
        'league_id' => $league->id,
        'user_id' => $teamUser->id,
        'admin_flag' => 1,
        'pick_position' => 1,
        'draft_points' => 100,
        'victory_points' => 0,
        'set_wins' => 0,
        'set_losses' => 0,
        'game_wins' => 0,
        'game_losses' => 0,
    ]);

    return [$owner, $league, $teamUser];
}

it('sets the winner and marks the league as completed', function () {
    [, $league, $teamUser] = createLeagueWithOwnerAndTeam();

    $response = $this->actingAs($teamUser)->post("/leagues/{$league->id}/set-winner", [
        'winner_user_id' => $teamUser->id,
    ]);

    $response->assertRedirect();

    $league->refresh();
    expect($league->winner)->toBe($teamUser->id)
        ->and($league->status)->toBe(0);
});

it('fails validation when winner_user_id is missing', function () {
    [, $league, $teamUser] = createLeagueWithOwnerAndTeam();

    $response = $this->actingAs($teamUser)->post("/leagues/{$league->id}/set-winner", []);

    $response->assertSessionHasErrors('winner_user_id');
});

it('fails validation when winner_user_id does not exist', function () {
    [, $league, $teamUser] = createLeagueWithOwnerAndTeam();

    $response = $this->actingAs($teamUser)->post("/leagues/{$league->id}/set-winner", [
        'winner_user_id' => 99999,
    ]);

    $response->assertSessionHasErrors('winner_user_id');
});

it('forbids non-admin league members from setting a winner', function () {
    [$owner, $league, $teamUser] = createLeagueWithOwnerAndTeam();

    $response = $this->actingAs($owner)->post("/leagues/{$league->id}/set-winner", [
        'winner_user_id' => $teamUser->id,
    ]);

    $response->assertForbidden();
});

it('requires authentication to set a winner', function () {
    [, $league, $teamUser] = createLeagueWithOwnerAndTeam();

    $response = $this->post("/leagues/{$league->id}/set-winner", [
        'winner_user_id' => $teamUser->id,
    ]);

    $response->assertRedirect('/login');
});
