<?php

use App\Models\User;
use App\Modules\League\Models\League;
use App\Modules\Matches\Models\MatchConfig;
use App\Modules\Matches\Models\Pool;
use App\Modules\Matches\Models\Set;
use App\Modules\Matches\Models\SetGameResult;
use App\Modules\Pokedex\Models\Pokedex;
use App\Modules\Stats\Models\PokemonUsageStat;
use App\Modules\Teams\Models\Team;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('redirects guests from the pokemon detail page', function () {
    $pokedex = Pokedex::create(['nationaldex_id' => 25, 'name' => 'Pikachu', 'type1' => 'Electric']);

    $this->get(route('usage-stats.show', ['pokedex_id' => $pokedex->id]))
        ->assertRedirect(route('login'));
});

it('returns 404 for a non-existent pokedex id', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('usage-stats.show', ['pokedex_id' => 99999]))
        ->assertNotFound();
});

it('renders the pokemon detail page with correct props for authenticated user', function () {
    $pokedex = Pokedex::create(['nationaldex_id' => 6, 'name' => 'Charizard', 'type1' => 'Fire', 'type2' => 'Flying']);

    PokemonUsageStat::query()->create([
        'pokedex_id' => $pokedex->id,
        'draft_pick_count' => 3,
        'draft_ban_count' => 1,
        'match_bring_count' => 5,
        'game_bring_count' => 4,
        'game_wins' => 4,
        'game_losses' => 1,
        'ko_count' => 6,
        'avg_ko_per_game' => 1.5,
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('usage-stats.show', ['pokedex_id' => $pokedex->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('usage-stats/Show')
            ->where('pokemon.name', 'Charizard')
            ->where('pokemon.type1', 'Fire')
            ->where('stat.draft_pick_count', 3)
            ->where('stat.game_wins', 4)
            ->where('stat.ko_count', 6)
            ->where('stat.game_bring_count', 4)
            ->where('stat.avg_ko_per_game', 1.5)
            ->has('games'));
});

it('returns empty games list when no replay data exists for the pokemon', function () {
    $pokedex = Pokedex::create(['nationaldex_id' => 143, 'name' => 'Snorlax', 'type1' => 'Normal']);

    $this->actingAs(User::factory()->create())
        ->get(route('usage-stats.show', ['pokedex_id' => $pokedex->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('usage-stats/Show')
            ->where('games', [])
            ->where('stat', null));
});

it('includes game entries where the pokemon was brought', function () {
    $pokedex = Pokedex::create(['nationaldex_id' => 149, 'name' => 'Dragonite', 'type1' => 'Dragon', 'type2' => 'Flying']);

    $owner = User::factory()->create();
    $user2 = User::factory()->create();

    $league = League::create([
        'name' => 'Detail Test League',
        'status' => \App\Modules\League\Enums\LeagueStatus::RegularSeason->value,
        'draft_points' => 100,
        'league_owner' => $owner->id,
    ]);

    $matchConfig = MatchConfig::create(['league_id' => $league->id, 'number_of_pools' => 1, 'status' => 1]);
    $pool = Pool::create(['league_id' => $league->id, 'match_config_id' => $matchConfig->id, 'status' => 1]);

    $team1 = Team::create([
        'name' => 'Red Team',
        'league_id' => $league->id,
        'user_id' => $owner->id,
        'pick_position' => 1,
        'seed' => 1,
        'pool_id' => $pool->id,
        'draft_points' => 100,
    ]);

    $team2 = Team::create([
        'name' => 'Blue Team',
        'league_id' => $league->id,
        'user_id' => $user2->id,
        'pick_position' => 2,
        'seed' => 2,
        'pool_id' => $pool->id,
        'draft_points' => 100,
    ]);

    $set = Set::create([
        'league_id' => $league->id,
        'pool_id' => $pool->id,
        'round' => 1,
        'team1_id' => $team1->id,
        'team2_id' => $team2->id,
        'status' => 0,
        'team1_score' => 2,
        'team2_score' => 1,
        'winner_id' => $team1->id,
        'replay1' => 'https://replay.pokemonshowdown.com/example-1',
    ]);

    SetGameResult::create([
        'set_id' => $set->id,
        'game_number' => 1,
        'p1_team_id' => $team1->id,
        'p2_team_id' => $team2->id,
        'winner_team_id' => $team1->id,
        'p1_pokemon' => [$pokedex->id, 1, 2, 3],
        'p2_pokemon' => [4, 5, 6, 7],
        'p1_knockouts' => [$pokedex->id],
        'p2_knockouts' => [],
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('usage-stats.show', ['pokedex_id' => $pokedex->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('usage-stats/Show')
            ->has('games', 1)
            ->where('games.0.set_id', $set->id)
            ->where('games.0.game_number', 1)
            ->where('games.0.round', 1)
            ->where('games.0.team1_name', 'Red Team')
            ->where('games.0.team2_name', 'Blue Team')
            ->where('games.0.won_game', true)
            ->where('games.0.ko_count', 1)
            ->where('games.0.replay_url', 'https://replay.pokemonshowdown.com/example-1'));
});
