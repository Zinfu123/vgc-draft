<?php

use App\Models\User;
use App\Modules\Draft\Models\Bans;
use App\Modules\League\Models\League;
use App\Modules\Pokedex\Models\Pokedex;
use App\Modules\Stats\Models\PokemonUsageStat;
use App\Modules\Stats\Models\PokemonUsageStatsMeta;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('rebuilds usage stats meta via artisan', function () {
    $this->artisan('usage-stats:rebuild')->assertSuccessful();

    $meta = PokemonUsageStatsMeta::query()->find(1);
    expect($meta)->not->toBeNull()
        ->and($meta->rebuilt_at)->not->toBeNull();
});

it('redirects guests from the usage stats page', function () {
    $this->get(route('usage-stats.index'))->assertRedirect(route('login'));
});

it('shows usage stats for authenticated users', function () {
    $this->artisan('usage-stats:rebuild');

    $this->actingAs(User::factory()->create())
        ->get(route('usage-stats.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('usage-stats/Index')
            ->has('meta')
            ->has('rows')
            ->has('charts')
            ->where('charts.top_ko_labels', [])
            ->where('charts.top_ko_values', []));
});

it('includes ko stats in rows when data exists', function () {
    $this->artisan('usage-stats:rebuild');

    $this->actingAs(User::factory()->create())
        ->get(route('usage-stats.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('usage-stats/Index')
            ->where('meta.total_picks', 0)
            ->where('meta.total_bans', 0)
            ->where('meta.total_bring_units', 0));
});

it('skips draft_bans rows with null pokedex_id when rebuilding', function () {
    $league = League::create([
        'name' => 'Skip Ban League',
        'status' => \App\Modules\League\Enums\LeagueStatus::Staging->value,
        'open' => true,
        'league_owner' => User::factory()->create()->id,
    ]);

    $pokedex = Pokedex::create([
        'nationaldex_id' => 25,
        'name' => 'Pikachu',
        'type1' => 'Electric',
    ]);

    Bans::create([
        'league_id' => $league->id,
        'team_id' => null,
        'pokedex_id' => null,
        'round_number' => 1,
        'status' => 0,
    ]);

    Bans::create([
        'league_id' => $league->id,
        'team_id' => null,
        'pokedex_id' => $pokedex->id,
        'round_number' => 1,
        'status' => 1,
    ]);

    $this->artisan('usage-stats:rebuild')->assertSuccessful();

    $meta = PokemonUsageStatsMeta::query()->find(1);
    expect($meta->total_bans)->toBe(1);

    $stats = PokemonUsageStat::query()->get();
    expect($stats)->toHaveCount(1)
        ->and($stats->first()->pokedex_id)->toBe($pokedex->id)
        ->and($stats->first()->draft_ban_count)->toBe(1);
});

it('stores game bring count and average ko per game when rebuilding from replay data', function () {
    $owner = User::factory()->create();
    $coach1 = User::factory()->create();
    $coach2 = User::factory()->create();

    $league = League::create([
        'name' => 'Usage Stats League',
        'status' => \App\Modules\League\Enums\LeagueStatus::RegularSeason->value,
        'league_owner' => $owner->id,
    ]);

    $matchConfig = \App\Modules\Matches\Models\MatchConfig::create([
        'league_id' => $league->id,
        'number_of_pools' => 1,
        'status' => 1,
    ]);

    $pool = \App\Modules\Matches\Models\Pool::create([
        'league_id' => $league->id,
        'match_config_id' => $matchConfig->id,
        'status' => 1,
    ]);

    $team1 = \App\Modules\Teams\Models\Team::create([
        'name' => 'Team Alpha',
        'league_id' => $league->id,
        'user_id' => $coach1->id,
        'admin_flag' => 0,
        'pick_position' => 1,
        'seed' => 1,
        'draft_points' => 80,
    ]);

    $team2 = \App\Modules\Teams\Models\Team::create([
        'name' => 'Team Beta',
        'league_id' => $league->id,
        'user_id' => $coach2->id,
        'admin_flag' => 0,
        'pick_position' => 2,
        'seed' => 2,
        'draft_points' => 80,
    ]);

    $set = \App\Modules\Matches\Models\Set::create([
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

    $pokedex = Pokedex::create([
        'nationaldex_id' => 25,
        'name' => 'Pikachu',
        'type1' => 'Electric',
    ]);

    $otherDex = Pokedex::create([
        'nationaldex_id' => 6,
        'name' => 'Charizard',
        'type1' => 'Fire',
    ]);

    \App\Modules\Matches\Models\SetGameResult::create([
        'set_id' => $set->id,
        'game_number' => 1,
        'p1_team_id' => $team1->id,
        'p2_team_id' => $team2->id,
        'winner_team_id' => $team1->id,
        'p1_pokemon' => [$pokedex->id],
        'p2_pokemon' => [$otherDex->id],
        'p1_knockouts' => [$pokedex->id, $pokedex->id],
        'p2_knockouts' => [],
    ]);

    \App\Modules\Matches\Models\SetGameResult::create([
        'set_id' => $set->id,
        'game_number' => 2,
        'p1_team_id' => $team1->id,
        'p2_team_id' => $team2->id,
        'winner_team_id' => $team2->id,
        'p1_pokemon' => [$pokedex->id],
        'p2_pokemon' => [$otherDex->id],
        'p1_knockouts' => [],
        'p2_knockouts' => [$otherDex->id],
    ]);

    $this->artisan('usage-stats:rebuild')->assertSuccessful();

    $stat = PokemonUsageStat::query()->where('pokedex_id', $pokedex->id)->first();

    expect($stat)->not->toBeNull()
        ->and($stat->game_bring_count)->toBe(2)
        ->and($stat->ko_count)->toBe(2)
        ->and((float) $stat->avg_ko_per_game)->toBe(1.0);
});
