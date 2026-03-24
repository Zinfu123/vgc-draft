<?php

use App\Models\User;
use App\Modules\Draft\Models\DraftConfig;
use App\Modules\League\Models\League;
use App\Modules\Matches\Models\MatchConfig;
use App\Modules\Matches\Models\Pool;
use App\Modules\Matches\Models\Set;
use App\Modules\Playoffs\Models\Playoff;
use App\Modules\Playoffs\Models\PlayoffMatch;
use App\Modules\Teams\Models\Team;
use Illuminate\Support\Facades\Event;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * @return array{0: League, 1: list<Team>}
 */
function enforcePokepasteLeagueWithFourTeams(): array
{
    $owner = User::factory()->create();

    $league = League::create([
        'name' => 'Enforce Pokepaste League',
        'status' => 1,
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
        'require_team_match_pokepaste_before_results' => true,
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

    return [$league, $teams];
}

it('rejects pool set completion when team pokepaste is required and pastes are missing', function () {
    Event::fake();

    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $league = League::create([
        'name' => 'Pool enforce league',
        'status' => 1,
        'draft_points' => 100,
        'league_owner' => $user1->id,
    ]);

    $matchConfig = MatchConfig::create([
        'league_id' => $league->id,
        'number_of_pools' => 1,
        'status' => 1,
        'require_team_match_pokepaste_before_results' => true,
    ]);

    $pool = Pool::create([
        'league_id' => $league->id,
        'match_config_id' => $matchConfig->id,
        'status' => 1,
    ]);

    $team1 = Team::create([
        'name' => 'Team 1',
        'league_id' => $league->id,
        'user_id' => $user1->id,
        'pick_position' => 1,
        'seed' => 1,
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
        'user_id' => $user2->id,
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

    $set = Set::create([
        'league_id' => $league->id,
        'pool_id' => $pool->id,
        'round' => 1,
        'team1_id' => $team1->id,
        'team2_id' => $team2->id,
        'status' => 1,
    ]);

    $this->actingAs($user1)->put('/match', [
        'set_id' => $set->id,
        'team1_id' => $team1->id,
        'team2_id' => $team2->id,
        'team1_score' => 2,
        'team2_score' => 0,
        'command' => 'update',
    ])->assertSessionHasErrors('set_result');

    $set->refresh();
    expect($set->status)->toBe(1);

    Event::assertNotDispatched(\App\Events\SetUpdatedEvent::class);
});

it('rejects playoff result recording when team pokepaste is required and playoff pastes are missing', function () {
    [$league, $teams] = enforcePokepasteLeagueWithFourTeams();
    $admin = $teams[0]->user;

    $this->actingAs($admin)->get(route('leagues.admin.playoffs', $league));
    $this->actingAs($admin)->post(route('leagues.admin.playoffs.generate', $league))->assertSessionHasNoErrors();

    $playoff = Playoff::query()->where('league_id', $league->id)->first();
    expect($playoff)->not->toBeNull();

    $matchId = PlayoffMatch::query()
        ->where('playoff_id', $playoff->id)
        ->where('slot', 'r0-0')
        ->value('id');

    expect($matchId)->not->toBeNull();

    $this->actingAs($admin)->post(route('leagues.admin.playoffs.record', $league), [
        'playoff_match_id' => $matchId,
        'team1_score' => 2,
        'team2_score' => 0,
    ])->assertSessionHasErrors('playoff_result');
});
