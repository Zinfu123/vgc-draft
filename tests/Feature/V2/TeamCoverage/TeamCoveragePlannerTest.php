<?php

use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('redirects v2 team coverage index to production route', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/v2/team-coverage')
        ->assertRedirect('/team-coverage');
});

it('redirects v2 team coverage pokedex search to production route', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/v2/team-coverage/pokedex-search?search=pika')
        ->assertRedirect('/team-coverage/pokedex-search?search=pika');
});

it('redirects v2 team coverage learnset to production route', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/v2/team-coverage/pokedex/25/learnset?game=scarlet-violet')
        ->assertRedirect('/team-coverage/pokedex/25/learnset?game=scarlet-violet');
});

it('redirects v2 team coverage roster to production route', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/v2/team-coverage/teams/1/roster')
        ->assertRedirect('/team-coverage/teams/1/roster');
});
