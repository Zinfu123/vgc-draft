<?php

use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('redirects v2 teams index to production route', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/v2/teams?league_id=1')
        ->assertRedirect('/teams?league_id=1');
});

it('redirects v2 team detail to production route', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/v2/teams/42')
        ->assertRedirect('/teams/42');
});

it('redirects v2 team create to production route', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/v2/teams', ['name' => 'Test'])
        ->assertRedirect('/teams');
});

it('redirects v2 team edit to production route', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/v2/teams/42', ['name' => 'Test'])
        ->assertRedirect('/teams/42');
});

it('registers teams module auditor', function () {
    $this->artisan('module:audit Teams')
        ->expectsOutputToContain('Teams')
        ->assertSuccessful();
});

it('requires auth for v2 teams routes', function () {
    $this->get('/v2/teams?league_id=1')->assertRedirect(route('login'));
});
