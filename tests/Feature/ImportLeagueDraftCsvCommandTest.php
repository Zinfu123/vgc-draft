<?php

use App\Models\User;
use App\Modules\League\Models\League;
use App\Modules\Matches\Models\MatchConfig;
use App\Modules\Matches\Models\Pool;
use App\Modules\Teams\Models\Team;
use Illuminate\Support\Facades\DB;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('fails when the directory does not exist', function () {
    $this->artisan('league:import-draft-csv', ['path' => '/nonexistent/league-csv-'.uniqid()])
        ->assertExitCode(1);
});

it('dry-run validates CSV bundles without writing', function () {
    $ctx = createLeagueDraftCsvFixtureContext();
    writeMinimalLeagueDraftCsvBundle($ctx);

    $this->artisan('league:import-draft-csv', [
        'path' => $ctx['dir'],
        '--dry-run' => true,
    ])
        ->expectsOutputToContain('Dry run OK')
        ->assertSuccessful();

    expect(DB::table('league_pokemon')->count())->toBe(0);
});

it('imports CSVs with preserved ids and advances the next autoincrement', function () {
    $ctx = createLeagueDraftCsvFixtureContext();
    writeMinimalLeagueDraftCsvBundle($ctx);

    $this->artisan('league:import-draft-csv', ['path' => $ctx['dir']])
        ->assertSuccessful();

    expect(DB::table('league_pokemon')->where('id', 100)->exists())->toBeTrue()
        ->and(DB::table('draft_config')->where('id', 50)->exists())->toBeTrue()
        ->and(DB::table('drafts')->where('id', 200)->exists())->toBeTrue()
        ->and(DB::table('draft_order')->where('id', 300)->exists())->toBeTrue()
        ->and(DB::table('draft_picks')->where('id', 400)->exists())->toBeTrue()
        ->and(DB::table('sets')->where('id', 500)->exists())->toBeTrue();

    $nextLeaguePokemonId = DB::table('league_pokemon')->insertGetId([
        'league_id' => $ctx['league_id'],
        'pokedex_id' => $ctx['pokedex_id'],
        'name' => 'post-import',
        'cost' => 1,
        'is_drafted' => 0,
        'drafted_by' => null,
        'kos' => 0,
        'banned' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($nextLeaguePokemonId)->toBe(101);
});

it('rejects draft_picks that reference a missing draft id', function () {
    $ctx = createLeagueDraftCsvFixtureContext();
    writeMinimalLeagueDraftCsvBundle($ctx, draftPickDraftIdOverride: 99999);

    $this->artisan('league:import-draft-csv', [
        'path' => $ctx['dir'],
        '--dry-run' => true,
    ])->assertExitCode(1);
});

it('replace clears prior league rows before importing', function () {
    $ctx = createLeagueDraftCsvFixtureContext();
    DB::table('league_pokemon')->insert([
        'id' => 5,
        'league_id' => $ctx['league_id'],
        'pokedex_id' => $ctx['pokedex_id'],
        'name' => 'stale',
        'cost' => 1,
        'is_drafted' => 0,
        'drafted_by' => null,
        'kos' => 0,
        'banned' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    writeMinimalLeagueDraftCsvBundle($ctx);

    $this->artisan('league:import-draft-csv', [
        'path' => $ctx['dir'],
        '--replace' => true,
    ])->assertSuccessful();

    expect(DB::table('league_pokemon')->where('id', 5)->exists())->toBeFalse()
        ->and(DB::table('league_pokemon')->where('id', 100)->exists())->toBeTrue();
});

/**
 * @return array{
 *     dir: string,
 *     league_id: int,
 *     pokedex_id: int,
 *     user_id: int,
 *     team1_id: int,
 *     team2_id: int,
 *     pool_id: int,
 * }
 */
function createLeagueDraftCsvFixtureContext(): array
{
    $owner = User::factory()->create();
    $league = League::create([
        'name' => 'CSV Import League',
        'status' => 1,
        'league_owner' => $owner->id,
    ]);

    $matchConfig = MatchConfig::create([
        'league_id' => $league->id,
        'enforce_round_count' => false,
    ]);

    $pool = Pool::create([
        'match_config_id' => $matchConfig->id,
        'league_id' => $league->id,
        'status' => 1,
    ]);

    $pickUser = User::factory()->create();
    $teamUser1 = User::factory()->create();
    $teamUser2 = User::factory()->create();

    $team1 = Team::create([
        'name' => 'Alpha',
        'league_id' => $league->id,
        'user_id' => $teamUser1->id,
        'pick_position' => 1,
        'seed' => 1,
        'draft_points' => 80,
        'victory_points' => 0,
        'set_wins' => 0,
        'set_losses' => 0,
        'game_wins' => 0,
        'game_losses' => 0,
        'admin_flag' => 1,
        'pool_id' => $pool->id,
    ]);

    $team2 = Team::create([
        'name' => 'Beta',
        'league_id' => $league->id,
        'user_id' => $teamUser2->id,
        'pick_position' => 2,
        'seed' => 2,
        'draft_points' => 80,
        'victory_points' => 0,
        'set_wins' => 0,
        'set_losses' => 0,
        'game_wins' => 0,
        'game_losses' => 0,
        'admin_flag' => 0,
        'pool_id' => $pool->id,
    ]);

    $pokedexId = DB::table('pokedex')->insertGetId([
        'nationaldex_id' => 1,
        'name' => 'bulbasaur',
        'type1' => 'Grass',
        'type2' => 'Poison',
        'sprite_url' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'league-draft-csv-'.uniqid('', true);
    if (! mkdir($dir, 0777, true) && ! is_dir($dir)) {
        throw new RuntimeException("Could not create temp directory: {$dir}");
    }

    return [
        'dir' => $dir,
        'league_id' => $league->id,
        'pokedex_id' => $pokedexId,
        'user_id' => $pickUser->id,
        'team1_id' => $team1->id,
        'team2_id' => $team2->id,
        'pool_id' => $pool->id,
    ];
}

/**
 * @param  array{
 *     dir: string,
 *     league_id: int,
 *     pokedex_id: int,
 *     user_id: int,
 *     team1_id: int,
 *     team2_id: int,
 *     pool_id: int,
 * }  $ctx
 */
function writeMinimalLeagueDraftCsvBundle(array $ctx, int $draftPickDraftIdOverride = 200): void
{
    $L = $ctx['league_id'];
    $dex = $ctx['pokedex_id'];
    $u = $ctx['user_id'];
    $t1 = $ctx['team1_id'];
    $t2 = $ctx['team2_id'];
    $p = $ctx['pool_id'];

    $ts = '2025-01-01 00:00:00';

    file_put_contents($ctx['dir'].'/league_pokemon.csv', <<<CSV
id,league_id,pokedex_id,name,cost,is_drafted,drafted_by,kos,created_at,updated_at,banned
100,{$L},{$dex},bulbasaur,1,false,,0,{$ts},{$ts},false
CSV);

    file_put_contents($ctx['dir'].'/draft_config.csv', <<<CSV
id,league_id,draft_date,draft_points,minimum_drafts,ban_enabled,created_at,updated_at,bans_per_user,minimum_cost_to_ban
50,{$L},2025-12-06,110,0,false,{$ts},{$ts},1,0
CSV);

    file_put_contents($ctx['dir'].'/drafts.csv', <<<CSV
id,league_id,round_number,status,pick_number,created_at,updated_at
200,{$L},1,0,1,{$ts},{$ts}
CSV);

    file_put_contents($ctx['dir'].'/draft_order.csv', <<<CSV
id,league_id,user_id,pick_number,status,is_last_pick,team_name,team_id,round_number,created_at,updated_at
300,{$L},{$u},1,0,1,Alpha,{$t1},1,{$ts},{$ts}
CSV);

    file_put_contents($ctx['dir'].'/draft_picks.csv', <<<CSV
id,draft_id,team_id,league_pokemon_id,round_number,pick_number,league_id,created_at,updated_at
400,{$draftPickDraftIdOverride},{$t1},100,1,1,{$L},{$ts},{$ts}
CSV);

    file_put_contents($ctx['dir'].'/sets.csv', <<<CSV
id,league_id,pool_id,round,team1_id,team2_id,team1_score,team2_score,team1_pokepaste,team2_pokepaste,winner_id,status,created_at,updated_at,team1_points,team2_points,replay1,replay2,replay3
500,{$L},{$p},1,{$t1},{$t2},,,,,,1,{$ts},{$ts},0,0,,,
CSV);
}
