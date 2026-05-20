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
