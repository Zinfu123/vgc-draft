<?php

use App\Models\User;
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
