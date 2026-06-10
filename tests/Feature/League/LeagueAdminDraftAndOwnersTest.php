<?php

use App\Models\User;
use App\Modules\Draft\Models\Draft;
use App\Modules\Draft\Models\DraftConfig;
use App\Modules\League\Models\League;
use App\Modules\Matches\Models\MatchConfig;
use App\Modules\Matches\Models\Pool;
use App\Modules\Teams\Models\Team;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * @return array{0: League, 1: Team, 2: Team, 3: User, 4: User, 5: User}
 */
function createLeagueWithOwnerAndTwoTeams(): array
{
    $owner = User::factory()->create();
    $coach1 = User::factory()->create();
    $coach2 = User::factory()->create();

    $league = League::create([
        'name' => 'Admin Draft League',
        'status' => \App\Modules\League\Enums\LeagueStatus::RegularSeason->value,
        'league_owner' => $owner->id,
        'maximum_teams' => 10,
    ]);

    DraftConfig::create([
        'league_id' => $league->id,
        'draft_date' => '2026-04-01',
        'draft_points' => 80,
        'minimum_drafts' => 1,
        'ban_enabled' => false,
    ]);

    $matchConfig = MatchConfig::create([
        'league_id' => $league->id,
        'number_of_pools' => 1,
        'enforce_round_count' => false,
    ]);

    $pool = Pool::create([
        'league_id' => $league->id,
        'match_config_id' => $matchConfig->id,
    ]);

    $team1 = Team::create([
        'league_id' => $league->id,
        'user_id' => $coach1->id,
        'name' => 'Alpha',
        'pick_position' => 1,
        'draft_points' => 80,
        'seed' => 0,
        'pool_id' => $pool->id,
        'admin_flag' => 0,
    ]);

    $team2 = Team::create([
        'league_id' => $league->id,
        'user_id' => $coach2->id,
        'name' => 'Beta',
        'pick_position' => 2,
        'draft_points' => 80,
        'seed' => 1,
        'pool_id' => $pool->id,
        'admin_flag' => 0,
    ]);

    return [$league, $team1, $team2, $owner, $coach1, $coach2];
}

it('allows the league owner to open draft admin without a team admin flag', function () {
    [$league, , , $owner] = createLeagueWithOwnerAndTwoTeams();

    $this->actingAs($owner)
        ->get(route('leagues.admin.draft', ['league' => $league->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('league/admin/DraftSettings')
            ->where('canReorderPicks', true));
});

it('forbids a non-member from draft admin', function () {
    [$league] = createLeagueWithOwnerAndTwoTeams();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get(route('leagues.admin.draft', ['league' => $league->id]))
        ->assertForbidden();
});

it('allows a co-admin team to open draft admin', function () {
    [$league, $team1, , , $coach1] = createLeagueWithOwnerAndTwoTeams();
    $team1->admin_flag = 1;
    $team1->save();

    $this->actingAs($coach1)
        ->get(route('leagues.admin.draft', ['league' => $league->id]))
        ->assertOk();
});

it('updates draft config for a league admin', function () {
    [$league, , , $owner] = createLeagueWithOwnerAndTwoTeams();

    $this->actingAs($owner)
        ->patch(route('leagues.admin.draft-config.update', ['league' => $league->id]), [
            'draft_date' => '2026-05-15',
            'draft_points' => 100,
            'minimum_drafts' => 2,
            'ban_enabled' => false,
        ])
        ->assertRedirect();

    $config = DraftConfig::where('league_id', $league->id)->first();
    expect($config)->not->toBeNull()
        ->and($config->draft_points)->toBe(100)
        ->and($config->minimum_drafts)->toBe(2);
});

it('validates draft config', function () {
    [$league, , , $owner] = createLeagueWithOwnerAndTwoTeams();

    $this->actingAs($owner)
        ->patch(route('leagues.admin.draft-config.update', ['league' => $league->id]), [
            'draft_date' => null,
            'draft_points' => 0,
            'minimum_drafts' => 0,
            'ban_enabled' => false,
        ])
        ->assertSessionHasErrors('draft_points');
});

it('updates pick order when no draft exists', function () {
    [$league, $team1, $team2, $owner] = createLeagueWithOwnerAndTwoTeams();

    $this->actingAs($owner)
        ->patch(route('leagues.admin.draft-pick-order.update', ['league' => $league->id]), [
            'team_ids' => [$team2->id, $team1->id],
        ])
        ->assertRedirect();

    expect(Team::find($team2->id)->pick_position)->toBe(1)
        ->and(Team::find($team1->id)->pick_position)->toBe(2);
});

it('updates pick order after a team has been dropped', function () {
    [$league, $team1, $team2, $owner] = createLeagueWithOwnerAndTwoTeams();

    $team2->dropped_at = now();
    $team2->user_id = null;
    $team2->save();

    $this->actingAs($owner)
        ->patch(route('leagues.admin.draft-pick-order.update', ['league' => $league->id]), [
            'team_ids' => [$team1->id],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(Team::find($team1->id)->pick_position)->toBe(1);
});

it('rejects pick order updates that include a dropped team id', function () {
    [$league, $team1, $team2, $owner] = createLeagueWithOwnerAndTwoTeams();

    $team2->dropped_at = now();
    $team2->user_id = null;
    $team2->save();

    $this->actingAs($owner)
        ->patch(route('leagues.admin.draft-pick-order.update', ['league' => $league->id]), [
            'team_ids' => [$team2->id, $team1->id],
        ])
        ->assertSessionHasErrors('team_ids.0');

    expect(Team::find($team1->id)->pick_position)->toBe(1);
});

it('rejects pick order updates when a draft row exists', function () {
    [$league, $team1, $team2, $owner] = createLeagueWithOwnerAndTwoTeams();

    Draft::create([
        'league_id' => $league->id,
        'status' => 1,
        'round_number' => 1,
        'pick_number' => 1,
    ]);

    $this->actingAs($owner)
        ->patch(route('leagues.admin.draft-pick-order.update', ['league' => $league->id]), [
            'team_ids' => [$team2->id, $team1->id],
        ])
        ->assertSessionHasErrors('team_ids');

    expect(Team::find($team1->id)->pick_position)->toBe(1)
        ->and(Team::find($team2->id)->pick_position)->toBe(2);
});

it('lets the owner toggle co-admin on a team', function () {
    [$league, $team1, , $owner] = createLeagueWithOwnerAndTwoTeams();

    $this->actingAs($owner)
        ->patch(route('leagues.admin.team-admin.update', ['league' => $league->id]), [
            'team_id' => $team1->id,
            'admin_flag' => true,
        ])
        ->assertRedirect();

    expect(Team::find($team1->id)->admin_flag)->toBe(1);
});

it('forbids a co-admin from toggling team admin flags', function () {
    [$league, $team1, , , $coach1] = createLeagueWithOwnerAndTwoTeams();
    $team1->admin_flag = 1;
    $team1->save();

    $this->actingAs($coach1)
        ->patch(route('leagues.admin.team-admin.update', ['league' => $league->id]), [
            'team_id' => $team1->id,
            'admin_flag' => false,
        ])
        ->assertForbidden();
});

it('shows league admins page to admins and hides toggles from non-owners', function () {
    [$league, $team1, , , $coach1] = createLeagueWithOwnerAndTwoTeams();
    $team1->admin_flag = 1;
    $team1->save();

    $this->actingAs($coach1)
        ->get(route('leagues.admin.league-admins', ['league' => $league->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('league/admin/LeagueAdmins')
            ->where('isLeagueOwner', false)
            ->where('isLeagueAdmin', true));
});
