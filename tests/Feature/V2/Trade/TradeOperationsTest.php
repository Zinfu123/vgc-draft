<?php

use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('redirects v2 league trades index to production route', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/v2/leagues/42/trades')
        ->assertRedirect('/leagues/42/trades');
});

it('redirects v2 league trades create to production route', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/v2/leagues/42/trades', ['offered' => [1], 'requested' => [2]])
        ->assertRedirect('/leagues/42/trades');
});

it('redirects v2 league trades free-agency to production route', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/v2/leagues/42/trades/free-agency', ['offered' => [1], 'requested' => [2]])
        ->assertRedirect('/leagues/42/trades/free-agency');
});

it('redirects v2 league trades respond to production route', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put('/v2/leagues/42/trades/7', ['action' => 'accept'])
        ->assertRedirect('/leagues/42/trades/7');
});

it('redirects v2 league set-team-trades to production route', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/v2/leagues/42/trades/set-team-trades', ['trades' => 3])
        ->assertRedirect('/leagues/42/trades/set-team-trades');
});

it('registers trade module auditor', function () {
    $this->artisan('module:audit Trade')
        ->expectsOutputToContain('Trade')
        ->assertSuccessful();
});

it('requires auth for v2 trade routes', function () {
    $this->get('/v2/leagues/1/trades')->assertRedirect(route('login'));
});
