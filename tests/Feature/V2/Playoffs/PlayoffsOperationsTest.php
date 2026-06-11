<?php

use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('redirects v2 league admin playoffs to production route', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/v2/leagues/42/admin/playoffs')
        ->assertRedirect('/leagues/42/admin/playoffs');
});

it('redirects v2 league admin playoffs update to production route', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/v2/leagues/42/admin/playoffs', ['format' => 'single_elimination'])
        ->assertRedirect('/leagues/42/admin/playoffs');
});

it('redirects v2 league admin playoffs generate to production route', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/v2/leagues/42/admin/playoffs/generate')
        ->assertRedirect('/leagues/42/admin/playoffs/generate');
});

it('redirects v2 league admin playoffs record to production route', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/v2/leagues/42/admin/playoffs/record', ['playoff_match_id' => 1])
        ->assertRedirect('/leagues/42/admin/playoffs/record');
});

it('redirects v2 league admin playoffs reset to production route', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/v2/leagues/42/admin/playoffs/reset')
        ->assertRedirect('/leagues/42/admin/playoffs/reset');
});

it('registers playoffs module auditor', function () {
    $this->artisan('module:audit Playoffs')
        ->expectsOutputToContain('Playoffs')
        ->assertSuccessful();
});

it('requires auth for v2 playoffs routes', function () {
    $this->get('/v2/leagues/1/admin/playoffs')->assertRedirect(route('login'));
});
