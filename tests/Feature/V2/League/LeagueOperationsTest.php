<?php

use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('redirects v2 leagues index to production route', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/v2/leagues')
        ->assertRedirect('/leagues');
})->group('v2');

it('redirects v2 league dashboard to production route', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/v2/leagues/42/dashboard')
        ->assertRedirect('/leagues/42/dashboard');
});

it('redirects v2 league create to production route', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/v2/leagues', ['league_name' => 'Test League'])
        ->assertRedirect('/leagues');
});

it('redirects v2 league admin draft config update to production route', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/v2/leagues/42/admin/draft-config', ['draft_points' => 80])
        ->assertRedirect('/leagues/42/admin/draft-config');
});

it('redirects v2 league cancel to production route', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/v2/leagues/42/cancel')
        ->assertRedirect('/leagues/42/cancel');
});

it('registers league module auditor', function () {
    $this->artisan('module:audit League')
        ->expectsOutputToContain('League')
        ->assertSuccessful();
});

it('requires auth for v2 league routes', function () {
    $this->get('/v2/leagues')->assertRedirect(route('login'));
});
