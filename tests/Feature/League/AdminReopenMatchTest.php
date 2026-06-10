<?php

use App\Models\User;
use App\Modules\League\Models\League;
use App\Modules\Matches\Models\MatchConfig;
use App\Modules\Matches\Models\Pool;
use App\Modules\Matches\Models\Set;
use App\Modules\Teams\Models\Team;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function createLeagueWithAdminTeamAndPool(User $admin): array
{
    $league = League::create([
        'name' => 'Test League',
        'status' => \App\Modules\League\Enums\LeagueStatus::RegularSeason->value,
        'draft_points' => 100,
        'league_owner' => $admin->id,
    ]);

    $matchConfig = MatchConfig::create([
        'league_id' => $league->id,
        'number_of_pools' => 1,
        'status' => 1,
    ]);

    $pool = Pool::create([
        'league_id' => $league->id,
        'match_config_id' => $matchConfig->id,
        'status' => 1,
    ]);

    Team::create([
        'name' => 'Admin Team',
        'league_id' => $league->id,
        'user_id' => $admin->id,
        'pick_position' => 1,
        'seed' => 1,
        'pool_id' => $pool->id,
        'draft_points' => 100,
        'victory_points' => 0,
        'set_wins' => 0,
        'set_losses' => 0,
        'game_wins' => 0,
        'game_losses' => 0,
        'admin_flag' => 1,
    ]);

    return [$league, $pool];
}

it('renders the admin reopen match page for league admins', function () {
    $admin = User::factory()->create();
    [$league] = createLeagueWithAdminTeamAndPool($admin);

    $this->actingAs($admin)
        ->get(route('leagues.admin.reopen-match', ['league' => $league->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('league/admin/ReopenMatch')
            ->has('league'));
});

it('reopens a completed set from a pasted URL', function () {
    $admin = User::factory()->create();
    $player = User::factory()->create();
    [$league, $pool] = createLeagueWithAdminTeamAndPool($admin);

    $team1 = Team::create([
        'name' => 'Team 1',
        'league_id' => $league->id,
        'user_id' => $player->id,
        'pick_position' => 2,
        'seed' => 2,
        'pool_id' => $pool->id,
        'draft_points' => 100,
        'victory_points' => 3,
        'set_wins' => 1,
        'set_losses' => 0,
        'game_wins' => 2,
        'game_losses' => 0,
    ]);

    $team2 = Team::create([
        'name' => 'Team 2',
        'league_id' => $league->id,
        'user_id' => User::factory()->create()->id,
        'pick_position' => 3,
        'seed' => 3,
        'pool_id' => $pool->id,
        'draft_points' => 100,
        'victory_points' => 0,
        'set_wins' => 0,
        'set_losses' => 1,
        'game_wins' => 0,
        'game_losses' => 2,
    ]);

    $set = Set::create([
        'league_id' => $league->id,
        'pool_id' => $pool->id,
        'round' => 1,
        'team1_id' => $team1->id,
        'team2_id' => $team2->id,
        'team1_score' => 2,
        'team2_score' => 0,
        'winner_id' => $team1->id,
        'status' => 0,
    ]);

    $url = url('/match/set/'.$set->id);

    $this->actingAs($admin)
        ->post(route('leagues.admin.reopen-match.store', ['league' => $league->id]), [
            'match_reference' => $url,
        ])
        ->assertRedirect(route('leagues.admin.reopen-match', ['league' => $league->id]));

    $set->refresh();
    expect($set->status)->toBe(1)->and($set->winner_id)->toBeNull();

    $team1->refresh();
    $team2->refresh();
    expect($team1->victory_points)->toBe(0)->and($team1->set_wins)->toBe(0);
});

it('rejects reopen when the set belongs to another league', function () {
    $admin = User::factory()->create();
    $otherAdmin = User::factory()->create();
    [$league] = createLeagueWithAdminTeamAndPool($admin);
    [$otherLeague, $otherPool] = createLeagueWithAdminTeamAndPool($otherAdmin);

    $t1 = Team::create([
        'name' => 'T1',
        'league_id' => $otherLeague->id,
        'user_id' => User::factory()->create()->id,
        'pick_position' => 2,
        'seed' => 2,
        'pool_id' => $otherPool->id,
        'draft_points' => 100,
        'victory_points' => 0,
        'set_wins' => 0,
        'set_losses' => 0,
        'game_wins' => 0,
        'game_losses' => 0,
    ]);
    $t2 = Team::create([
        'name' => 'T2',
        'league_id' => $otherLeague->id,
        'user_id' => User::factory()->create()->id,
        'pick_position' => 3,
        'seed' => 3,
        'pool_id' => $otherPool->id,
        'draft_points' => 100,
        'victory_points' => 0,
        'set_wins' => 0,
        'set_losses' => 0,
        'game_wins' => 0,
        'game_losses' => 0,
    ]);

    $foreignSet = Set::create([
        'league_id' => $otherLeague->id,
        'pool_id' => $otherPool->id,
        'round' => 1,
        'team1_id' => $t1->id,
        'team2_id' => $t2->id,
        'team1_score' => 2,
        'team2_score' => 0,
        'winner_id' => $t1->id,
        'status' => 0,
    ]);

    $this->actingAs($admin)
        ->post(route('leagues.admin.reopen-match.store', ['league' => $league->id]), [
            'match_reference' => (string) $foreignSet->id,
        ])
        ->assertSessionHasErrors('set_id');
});

it('forbids non-admins from posting reopen', function () {
    $admin = User::factory()->create();
    $player = User::factory()->create();
    [$league, $pool] = createLeagueWithAdminTeamAndPool($admin);

    $team1 = Team::create([
        'name' => 'Team 1',
        'league_id' => $league->id,
        'user_id' => $player->id,
        'pick_position' => 2,
        'seed' => 2,
        'pool_id' => $pool->id,
        'draft_points' => 100,
        'victory_points' => 0,
        'set_wins' => 0,
        'set_losses' => 0,
        'game_wins' => 0,
        'game_losses' => 0,
    ]);

    $team2 = Team::create([
        'name' => 'Team 2',
        'league_id' => $league->id,
        'user_id' => User::factory()->create()->id,
        'pick_position' => 3,
        'seed' => 3,
        'pool_id' => $pool->id,
        'draft_points' => 100,
        'victory_points' => 0,
        'set_wins' => 0,
        'set_losses' => 0,
        'game_wins' => 0,
        'game_losses' => 0,
    ]);

    $set = Set::create([
        'league_id' => $league->id,
        'pool_id' => $pool->id,
        'round' => 1,
        'team1_id' => $team1->id,
        'team2_id' => $team2->id,
        'team1_score' => 2,
        'team2_score' => 0,
        'winner_id' => $team1->id,
        'status' => 0,
    ]);

    $this->actingAs($player)
        ->post(route('leagues.admin.reopen-match.store', ['league' => $league->id]), [
            'match_reference' => (string) $set->id,
        ])
        ->assertForbidden();
});
