<?php

use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('redirects v2 match set detail to production route', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/v2/match/set/42')
        ->assertRedirect('/match/set/42');
});

it('redirects v2 match message store to production route', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/v2/match/set/42/messages', ['body' => 'Hello'])
        ->assertRedirect('/match/set/42/messages');
});

it('redirects v2 match update to production route', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put('/v2/match', ['set_id' => 1])
        ->assertRedirect('/match');
});

it('redirects v2 pools create to production route', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/v2/pools/create', ['league_id' => 1])
        ->assertRedirect('/pools/create');
});

it('registers matches module auditor', function () {
    $this->artisan('module:audit Matches')
        ->expectsOutputToContain('Matches')
        ->assertSuccessful();
});

it('requires auth for v2 match routes', function () {
    $this->get('/v2/match/set/1')->assertRedirect(route('login'));
});
