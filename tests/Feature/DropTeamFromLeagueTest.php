<?php

use App\Models\User;
use App\Modules\Draft\Models\DraftConfig;
use App\Modules\League\Models\League;
use App\Modules\League\Models\LeaguePokemon;
use App\Modules\Matches\Models\MatchConfig;
use App\Modules\Matches\Models\Pool;
use App\Modules\Matches\Models\Set;
use App\Modules\Pokedex\Models\Pokedex;
use App\Modules\Teams\Models\Team;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('admin can drop a team and converts sets to byes with standings adjusted', function () {
    $owner = User::factory()->create();
    $league = League::create([
        'name' => 'Drop Test',
        'status' => 1,
        'league_owner' => $owner->id,
    ]);

    DraftConfig::create([
        'league_id' => $league->id,
        'draft_date' => now()->addDay(),
        'draft_points' => 80,
        'ban_enabled' => false,
        'minimum_drafts' => 2,
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

    $uA = User::factory()->create();
    $uB = User::factory()->create();
    $uDrop = User::factory()->create();

    $teamA = Team::create([
        'name' => 'Alpha',
        'league_id' => $league->id,
        'user_id' => $uA->id,
        'pick_position' => 1,
        'seed' => 1,
        'pool_id' => $pool->id,
        'draft_points' => 80,
        'victory_points' => 0,
        'set_wins' => 0,
        'set_losses' => 0,
        'game_wins' => 0,
        'game_losses' => 0,
        'trades' => 4,
        'admin_flag' => 1,
    ]);

    $teamB = Team::create([
        'name' => 'Beta',
        'league_id' => $league->id,
        'user_id' => $uB->id,
        'pick_position' => 2,
        'seed' => 2,
        'pool_id' => $pool->id,
        'draft_points' => 80,
        'victory_points' => 0,
        'set_wins' => 0,
        'set_losses' => 0,
        'game_wins' => 0,
        'game_losses' => 0,
        'trades' => 4,
    ]);

    $teamDrop = Team::create([
        'name' => 'Gone',
        'league_id' => $league->id,
        'user_id' => $uDrop->id,
        'pick_position' => 3,
        'seed' => 3,
        'pool_id' => $pool->id,
        'draft_points' => 80,
        'victory_points' => 0,
        'set_wins' => 0,
        'set_losses' => 0,
        'game_wins' => 0,
        'game_losses' => 0,
        'trades' => 4,
    ]);

    $pd1 = Pokedex::create(['nationaldex_id' => 1, 'name' => 'Bulba', 'type1' => 'Grass']);
    $pd2 = Pokedex::create(['nationaldex_id' => 4, 'name' => 'Charm', 'type1' => 'Fire']);

    $lpDrop = LeaguePokemon::create([
        'league_id' => $league->id,
        'pokedex_id' => $pd1->id,
        'name' => 'Bulba',
        'cost' => 10,
        'drafted_by' => $teamDrop->id,
        'is_drafted' => true,
    ]);

    LeaguePokemon::create([
        'league_id' => $league->id,
        'pokedex_id' => $pd2->id,
        'name' => 'Charm',
        'cost' => 15,
        'drafted_by' => $teamA->id,
        'is_drafted' => true,
    ]);

    $setComplete = Set::create([
        'league_id' => $league->id,
        'pool_id' => $pool->id,
        'round' => 1,
        'team1_id' => $teamA->id,
        'team2_id' => $teamDrop->id,
        'team1_score' => 2,
        'team2_score' => 0,
        'winner_id' => $teamA->id,
        'status' => 0,
        'is_bye' => false,
    ]);

    $teamA->update([
        'victory_points' => 3,
        'set_wins' => 1,
        'game_wins' => 2,
        'game_losses' => 0,
    ]);
    $teamDrop->update([
        'set_losses' => 1,
        'game_wins' => 0,
        'game_losses' => 2,
    ]);

    $setOpen = Set::create([
        'league_id' => $league->id,
        'pool_id' => $pool->id,
        'round' => 2,
        'team1_id' => $teamDrop->id,
        'team2_id' => $teamB->id,
        'status' => 1,
        'is_bye' => false,
    ]);

    $response = $this->actingAs($owner)->post(route('leagues.admin.drop-team', ['league' => $league->id]), [
        'team_id' => $teamDrop->id,
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $teamDrop->refresh();
    expect($teamDrop->dropped_at)->not->toBeNull()
        ->and($teamDrop->user_id)->toBeNull();

    $lpDrop->refresh();
    expect($lpDrop->drafted_by)->toBeNull()
        ->and($lpDrop->is_drafted)->toBeFalse();

    $setComplete->refresh();
    expect($setComplete->team2_id)->toBeNull()
        ->and($setComplete->is_bye)->toBeTrue()
        ->and((int) $setComplete->team1_score)->toBe(2)
        ->and((int) $setComplete->team2_score)->toBe(0);

    $setOpen->refresh();
    expect($setOpen->team2_id)->toBeNull()
        ->and($setOpen->is_bye)->toBeTrue();

    $teamA->refresh();
    $teamB->refresh();

    expect($teamB->victory_points)->toBe(3)
        ->and($teamB->set_wins)->toBe(1);
});
