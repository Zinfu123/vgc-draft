<?php

use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('redirects v2 pokedex index to production pokedex', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/v2/pokedex?ability=overgrow')
        ->assertRedirect('/pokedex?ability=overgrow');
});

it('redirects v2 pokedex show to production pokedex', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/v2/pokedex/25?game=scarlet-violet')
        ->assertRedirect('/pokedex/25?game=scarlet-violet');
});

it('redirects v2 pokedex ability show to production route', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/v2/pokedex/abilities/65')
        ->assertRedirect('/pokedex/abilities/65');
});

it('redirects v2 pokedex item show to production route', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/v2/pokedex/items/211')
        ->assertRedirect('/pokedex/items/211');
});
