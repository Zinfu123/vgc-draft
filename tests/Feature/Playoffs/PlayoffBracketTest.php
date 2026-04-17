<?php

use App\Enums\Playoffs\PlayoffStatus;
use App\Models\User;
use App\Modules\Draft\Models\DraftConfig;
use App\Modules\League\Enums\LeagueStatus;
use App\Modules\League\Models\League;
use App\Modules\Matches\Models\MatchConfig;
use App\Modules\Matches\Models\Pool;
use App\Modules\Playoffs\Models\Playoff;
use App\Modules\Playoffs\Models\PlayoffMatch;
use App\Modules\Teams\Models\Team;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * @return array{0: League, 1: list<Team>, 2: list<User>}
 */
function createLeagueWithFourTeams(): array
{
    $owner = User::factory()->create();

    $league = League::create([
        'name' => 'Playoff Test League',
        'status' => LeagueStatus::Playoffs->value,
        'draft_points' => 80,
        'league_owner' => $owner->id,
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
        'number_of_pools' => 1,
        'enforce_round_count' => false,
    ]);

    $pool = Pool::create([
        'league_id' => $league->id,
        'match_config_id' => $matchConfig->id,
    ]);

    $users = User::factory()->count(4)->create();
    $teams = [];
    foreach ($users as $i => $user) {
        $teams[] = Team::create([
            'league_id' => $league->id,
            'user_id' => $user->id,
            'name' => 'Team '.($i + 1),
            'pick_position' => $i + 1,
            'draft_points' => 80,
            'seed' => $i,
            'admin_flag' => $i === 0 ? 1 : 0,
            'pool_id' => $pool->id,
        ]);
    }

    return [$league, $teams, $users->all()];
}

/**
 * @return array{0: League, 1: list<Team>}
 */
function createLeagueWithSixTeams(): array
{
    $owner = User::factory()->create();

    $league = League::create([
        'name' => 'Six Team Playoff League',
        'status' => LeagueStatus::Playoffs->value,
        'draft_points' => 80,
        'league_owner' => $owner->id,
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
        'number_of_pools' => 1,
        'enforce_round_count' => false,
    ]);

    $pool = Pool::create([
        'league_id' => $league->id,
        'match_config_id' => $matchConfig->id,
    ]);

    $users = User::factory()->count(6)->create();
    $teams = [];
    foreach ($users as $i => $user) {
        $teams[] = Team::create([
            'league_id' => $league->id,
            'user_id' => $user->id,
            'name' => 'Team '.($i + 1),
            'pick_position' => $i + 1,
            'draft_points' => 80,
            'seed' => $i,
            'admin_flag' => $i === 0 ? 1 : 0,
            'pool_id' => $pool->id,
        ]);
    }

    return [$league, $teams];
}

it('redirects guests from the playoffs admin page', function () {
    [$league] = createLeagueWithFourTeams();

    $this->get(route('leagues.admin.playoffs', $league))->assertRedirect('/login');
});

it('forbids non-admin users from the playoffs admin page', function () {
    [$league, $teams] = createLeagueWithFourTeams();

    $this->actingAs($teams[1]->user)->get(route('leagues.admin.playoffs', $league))->assertForbidden();
});

it('allows league admins to view playoffs and creates a draft playoff with seeds', function () {
    [$league, $teams] = createLeagueWithFourTeams();

    $response = $this->actingAs($teams[0]->user)->get(route('leagues.admin.playoffs', $league));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('league/admin/Playoffs')
        ->where('playoff.status', 'draft')
        ->has('playoff.seed_order', 4));

    $playoff = Playoff::query()->where('league_id', $league->id)->first();
    expect($playoff)->not->toBeNull()
        ->and($playoff->seed_order)->toHaveCount(4);
});

it('generates a single elimination bracket for six teams with byes for the top two seeds', function () {
    [$league, $teams] = createLeagueWithSixTeams();
    $admin = $teams[0]->user;

    $this->actingAs($admin)->get(route('leagues.admin.playoffs', $league));

    $this->actingAs($admin)->patch(route('leagues.admin.playoffs.update', $league), [
        'format' => 'single_elimination',
        'bracket_size' => 6,
        'seed_order' => array_map(fn (Team $t) => $t->id, $teams),
    ]);

    $response = $this->actingAs($admin)->post(route('leagues.admin.playoffs.generate', $league));

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $playoff = Playoff::query()->where('league_id', $league->id)->first();
    expect($playoff->status->value)->toBe('active')
        ->and(PlayoffMatch::query()->where('playoff_id', $playoff->id)->count())->toBe(6);

    $r10 = PlayoffMatch::query()->where('playoff_id', $playoff->id)->where('slot', 'r1-0')->first();
    $r00 = PlayoffMatch::query()->where('playoff_id', $playoff->id)->where('slot', 'r0-0')->first();
    expect((int) $r10->team1_id)->toBe($teams[0]->id)
        ->and($r10->team2_id)->toBeNull()
        ->and((int) $r00->team1_id)->toBe($teams[3]->id)
        ->and((int) $r00->team2_id)->toBe($teams[4]->id);
});

it('generates a single elimination bracket with bronze for four teams', function () {
    [$league, $teams] = createLeagueWithFourTeams();
    $admin = $teams[0]->user;

    $this->actingAs($admin)->get(route('leagues.admin.playoffs', $league));

    $response = $this->actingAs($admin)->post(route('leagues.admin.playoffs.generate', $league));

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $playoff = Playoff::query()->where('league_id', $league->id)->first();
    expect($playoff->status->value)->toBe('active')
        ->and(PlayoffMatch::query()->where('playoff_id', $playoff->id)->count())->toBe(4);
});

it('rejects closing playoffs before bronze is complete', function () {
    [$league, $teams] = createLeagueWithFourTeams();
    $admin = $teams[0]->user;

    $this->actingAs($admin)->get(route('leagues.admin.playoffs', $league));
    $this->actingAs($admin)->post(route('leagues.admin.playoffs.generate', $league));

    $playoff = Playoff::query()->where('league_id', $league->id)->first();
    $finals = PlayoffMatch::query()->where('playoff_id', $playoff->id)->where('slot', 'r1-0')->first();

    $this->actingAs($admin)->post(route('leagues.admin.playoffs.record', $league), [
        'playoff_match_id' => PlayoffMatch::query()->where('slot', 'r0-0')->where('playoff_id', $playoff->id)->value('id'),
        'team1_score' => 2,
        'team2_score' => 0,
    ]);
    $this->actingAs($admin)->post(route('leagues.admin.playoffs.record', $league), [
        'playoff_match_id' => PlayoffMatch::query()->where('slot', 'r0-1')->where('playoff_id', $playoff->id)->value('id'),
        'team1_score' => 2,
        'team2_score' => 0,
    ]);

    $finals->refresh();
    $this->actingAs($admin)->post(route('leagues.admin.playoffs.record', $league), [
        'playoff_match_id' => $finals->id,
        'team1_score' => 2,
        'team2_score' => 1,
    ]);

    $close = $this->actingAs($admin)->post(route('leagues.admin.playoffs.close', $league));

    $close->assertSessionHasErrors('playoff');
});

it('closes playoffs and sets league winner and medal placements', function () {
    [$league, $teams] = createLeagueWithFourTeams();
    $admin = $teams[0]->user;

    $this->actingAs($admin)->get(route('leagues.admin.playoffs', $league));
    $this->actingAs($admin)->post(route('leagues.admin.playoffs.generate', $league));

    $playoff = Playoff::query()->where('league_id', $league->id)->first();

    foreach (['r0-0', 'r0-1'] as $slot) {
        $m = PlayoffMatch::query()->where('playoff_id', $playoff->id)->where('slot', $slot)->first();
        $this->actingAs($admin)->post(route('leagues.admin.playoffs.record', $league), [
            'playoff_match_id' => $m->id,
            'team1_score' => 2,
            'team2_score' => 0,
        ]);
    }

    $bronze = PlayoffMatch::query()->where('playoff_id', $playoff->id)->where('slot', 'bronze')->first();
    $this->actingAs($admin)->post(route('leagues.admin.playoffs.record', $league), [
        'playoff_match_id' => $bronze->id,
        'team1_score' => 2,
        'team2_score' => 0,
    ]);

    $finals = PlayoffMatch::query()->where('playoff_id', $playoff->id)->where('slot', 'r1-0')->first();
    $this->actingAs($admin)->post(route('leagues.admin.playoffs.record', $league), [
        'playoff_match_id' => $finals->id,
        'team1_score' => 2,
        'team2_score' => 0,
    ]);

    $close = $this->actingAs($admin)->post(route('leagues.admin.playoffs.close', $league));
    $close->assertRedirect();
    $close->assertSessionHasNoErrors();

    $league->refresh();
    $winnerTeamId = (int) $finals->fresh()->winner_team_id;
    $winnerUserId = Team::query()->find($winnerTeamId)?->user_id;

    expect($league->status)->toBe(LeagueStatus::Completed)
        ->and((int) $league->winner)->toBe((int) $winnerUserId);

    expect(Team::query()->where('league_id', $league->id)->where('medal_placement', 1)->count())->toBe(1)
        ->and(Team::query()->where('league_id', $league->id)->where('medal_placement', 2)->count())->toBe(1)
        ->and(Team::query()->where('league_id', $league->id)->where('medal_placement', 3)->count())->toBe(1);
});

it('resets completed playoffs and clears league winner medals and matches', function () {
    [$league, $teams] = createLeagueWithFourTeams();
    $admin = $teams[0]->user;

    $this->actingAs($admin)->get(route('leagues.admin.playoffs', $league));
    $this->actingAs($admin)->post(route('leagues.admin.playoffs.generate', $league));

    $playoff = Playoff::query()->where('league_id', $league->id)->first();

    foreach (['r0-0', 'r0-1'] as $slot) {
        $m = PlayoffMatch::query()->where('playoff_id', $playoff->id)->where('slot', $slot)->first();
        $this->actingAs($admin)->post(route('leagues.admin.playoffs.record', $league), [
            'playoff_match_id' => $m->id,
            'team1_score' => 2,
            'team2_score' => 0,
        ]);
    }

    $bronze = PlayoffMatch::query()->where('playoff_id', $playoff->id)->where('slot', 'bronze')->first();
    $this->actingAs($admin)->post(route('leagues.admin.playoffs.record', $league), [
        'playoff_match_id' => $bronze->id,
        'team1_score' => 2,
        'team2_score' => 0,
    ]);

    $finals = PlayoffMatch::query()->where('playoff_id', $playoff->id)->where('slot', 'r1-0')->first();
    $this->actingAs($admin)->post(route('leagues.admin.playoffs.record', $league), [
        'playoff_match_id' => $finals->id,
        'team1_score' => 2,
        'team2_score' => 0,
    ]);

    $this->actingAs($admin)->post(route('leagues.admin.playoffs.close', $league));

    $league->refresh();
    expect($league->winner)->not->toBeNull()
        ->and($league->status)->toBe(LeagueStatus::Completed);

    $reset = $this->actingAs($admin)->post(route('leagues.admin.playoffs.reset', $league));
    $reset->assertRedirect();
    $reset->assertSessionHasNoErrors();

    $league->refresh();
    $playoff->refresh();

    expect($league->winner)->toBeNull()
        ->and($league->status)->toBe(LeagueStatus::Playoffs)
        ->and($playoff->status)->toBe(PlayoffStatus::Draft)
        ->and(PlayoffMatch::query()->where('playoff_id', $playoff->id)->count())->toBe(0)
        ->and(Team::query()->where('league_id', $league->id)->where('medal_placement', '>', 0)->count())->toBe(0);
});

it('rejects generating double elimination brackets', function () {
    [$league, $teams] = createLeagueWithFourTeams();
    $admin = $teams[0]->user;

    $this->actingAs($admin)->get(route('leagues.admin.playoffs', $league));

    $this->actingAs($admin)->patch(route('leagues.admin.playoffs.update', $league), [
        'format' => 'double_elimination',
        'bracket_size' => 4,
        'seed_order' => array_map(fn (Team $t) => $t->id, $teams),
    ]);

    $response = $this->actingAs($admin)->post(route('leagues.admin.playoffs.generate', $league));

    $response->assertSessionHasErrors('format');
});

it('includes podium and playoff stats on the dashboard after playoffs', function () {
    [$league, $teams] = createLeagueWithFourTeams();
    $admin = $teams[0]->user;

    $this->actingAs($admin)->get(route('leagues.admin.playoffs', $league));
    $this->actingAs($admin)->post(route('leagues.admin.playoffs.generate', $league));

    $playoff = Playoff::query()->where('league_id', $league->id)->first();

    foreach (['r0-0', 'r0-1'] as $slot) {
        $m = PlayoffMatch::query()->where('playoff_id', $playoff->id)->where('slot', $slot)->first();
        $this->actingAs($admin)->post(route('leagues.admin.playoffs.record', $league), [
            'playoff_match_id' => $m->id,
            'team1_score' => 2,
            'team2_score' => 0,
        ]);
    }

    $bronze = PlayoffMatch::query()->where('playoff_id', $playoff->id)->where('slot', 'bronze')->first();
    $this->actingAs($admin)->post(route('leagues.admin.playoffs.record', $league), [
        'playoff_match_id' => $bronze->id,
        'team1_score' => 2,
        'team2_score' => 1,
    ]);

    $finals = PlayoffMatch::query()->where('playoff_id', $playoff->id)->where('slot', 'r1-0')->first();
    $this->actingAs($admin)->post(route('leagues.admin.playoffs.record', $league), [
        'playoff_match_id' => $finals->id,
        'team1_score' => 2,
        'team2_score' => 0,
    ]);

    $this->actingAs($admin)->post(route('leagues.admin.playoffs.close', $league));

    $championUser = Team::query()->find((int) $finals->fresh()->winner_team_id)?->user;
    expect($championUser)->not->toBeNull();

    $response = $this->actingAs($championUser)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('userStats.playoff_set_wins', 2)
        ->where('userStats.playoff_set_losses', 0)
        ->where('userStats.playoff_game_wins', 4)
        ->where('userStats.playoff_game_losses', 0)
        ->has('usersPastLeagues', 1)
        ->where('usersPastLeagues.0.podium.first', $championUser->name)
    );
});

it('redirects guests from the public league playoffs page', function () {
    [$league] = createLeagueWithFourTeams();

    $this->get(route('leagues.playoffs', $league))->assertRedirect('/login');
});

it('renders the league playoffs page for authenticated users', function () {
    [$league, $teams] = createLeagueWithFourTeams();

    $this->actingAs($teams[1]->user)
        ->get(route('leagues.schedule', ['league' => $league, 'view' => 'playoffs']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('league/LeagueDetailSchedule')
            ->has('playoff')
            ->has('bracketLayout')
            ->where('canAdjustPlayoff', false)
            ->where('canRecordPlayoffResults', false));
});

it('allows league admins to adjust seeds on the league playoffs page while the playoff is in draft', function () {
    [$league, $teams] = createLeagueWithFourTeams();

    $this->actingAs($teams[0]->user)
        ->get(route('leagues.schedule', ['league' => $league, 'view' => 'playoffs']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canAdjustPlayoff', true)
            ->where('canRecordPlayoffResults', false));
});

it('does not allow seed adjustments on the league playoffs page after the bracket is generated', function () {
    [$league, $teams] = createLeagueWithFourTeams();
    $admin = $teams[0]->user;

    $this->actingAs($admin)->get(route('leagues.admin.playoffs', $league));
    $this->actingAs($admin)->post(route('leagues.admin.playoffs.generate', $league));

    $this->actingAs($admin)
        ->get(route('leagues.schedule', ['league' => $league, 'view' => 'playoffs']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canAdjustPlayoff', false)
            ->where('canRecordPlayoffResults', true)
            ->where('bracketLayout.mode', 'live'));
});

it('does not allow recording playoff results on the league page for non-admins when playoffs are active', function () {
    [$league, $teams] = createLeagueWithFourTeams();
    $admin = $teams[0]->user;

    $this->actingAs($admin)->get(route('leagues.admin.playoffs', $league));
    $this->actingAs($admin)->post(route('leagues.admin.playoffs.generate', $league));

    $this->actingAs($teams[1]->user)
        ->get(route('leagues.schedule', ['league' => $league, 'view' => 'playoffs']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canRecordPlayoffResults', false)
            ->where('bracketLayout.mode', 'live'));
});

it('includes original bracket seeds on live layout cells', function () {
    [$league, $teams] = createLeagueWithFourTeams();
    $admin = $teams[0]->user;

    $this->actingAs($admin)->get(route('leagues.admin.playoffs', $league));
    $this->actingAs($admin)->post(route('leagues.admin.playoffs.generate', $league));

    $this->actingAs($admin)
        ->get(route('leagues.schedule', ['league' => $league, 'view' => 'playoffs']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('bracketLayout.rounds.0.matches.0.top.seed_number', 1)
            ->where('bracketLayout.rounds.0.matches.0.bottom.seed_number', 4));
});

it('re-seeds when a team is added to the league after the initial seed order was saved', function () {
    [$league, $teams] = createLeagueWithFourTeams();
    $admin = $teams[0]->user;

    $playoff = Playoff::query()->firstOrCreate(
        ['league_id' => $league->id],
        [
            'format' => \App\Enums\Playoffs\PlayoffFormat::SingleElimination,
            'bracket_size' => 4,
            'status' => PlayoffStatus::Draft,
            'seed_order' => null,
        ]
    );

    $existingThreeIds = array_slice(array_map(fn (Team $t) => $t->id, $teams), 0, 3);
    $playoff->seed_order = $existingThreeIds;
    $playoff->save();

    $this->actingAs($admin)
        ->get(route('leagues.admin.playoffs', $league))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('playoff.seed_order', 4));

    $playoff->refresh();
    $currentTeamIds = array_map(fn (Team $t) => $t->id, $teams);

    expect($playoff->seed_order)->toHaveCount(4)
        ->and(array_diff($currentTeamIds, $playoff->seed_order))->toBeEmpty();
});

it('re-seeds when seed_order contains stale team ids that no longer exist in the league', function () {
    [$league, $teams] = createLeagueWithFourTeams();
    $admin = $teams[0]->user;

    $playoff = Playoff::query()->firstOrCreate(
        ['league_id' => $league->id],
        [
            'format' => \App\Enums\Playoffs\PlayoffFormat::SingleElimination,
            'bracket_size' => 4,
            'status' => PlayoffStatus::Draft,
            'seed_order' => [99999, 99998, 99997, 99996],
        ]
    );
    $playoff->seed_order = [99999, 99998, 99997, 99996];
    $playoff->save();

    $this->actingAs($admin)
        ->get(route('leagues.admin.playoffs', $league))
        ->assertOk();

    $playoff->refresh();
    $currentTeamIds = array_map(fn (Team $t) => $t->id, $teams);

    expect($playoff->seed_order)->not->toContain(99999)
        ->and(array_diff($playoff->seed_order, $currentTeamIds))->toBeEmpty();
});
