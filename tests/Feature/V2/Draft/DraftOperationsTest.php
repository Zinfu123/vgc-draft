<?php

use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('redirects v2 draft detail to production route', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/v2/draft/42')
        ->assertRedirect('/draft/42');
});

it('redirects v2 draft wishlist toggle to production route', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/v2/draft/wishlist/toggle', ['league_id' => 1, 'league_pokemon_id' => 1])
        ->assertRedirect('/draft/wishlist/toggle');
});

it('redirects v2 draft create to production route', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/v2/draft/create', ['league_id' => 1])
        ->assertRedirect('/draft/create');
});

it('redirects v2 draft pick to production route', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/v2/draft', ['league_id' => 1])
        ->assertRedirect('/draft');
});

it('registers draft module auditor', function () {
    $this->artisan('module:audit Draft')
        ->expectsOutputToContain('Draft')
        ->assertSuccessful();
});

it('requires auth for v2 draft routes', function () {
    $this->get('/v2/draft/1')->assertRedirect(route('login'));
});
