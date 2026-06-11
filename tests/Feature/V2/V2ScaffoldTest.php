<?php

use App\Kernel\Audit\ModuleAuditRegistry;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('exposes v2 health endpoint with enabled modules list', function () {
    $response = $this->get('/v2');

    $response->assertSuccessful()
        ->assertJson([
            'version' => 2,
            'modules' => ['Pokedex', 'TeamCoverage', 'Teams', 'Draft', 'Matches', 'Trade'],
        ]);
});

it('registers module audit command', function () {
    $this->artisan('module:audit')
        ->expectsOutput('Registered module auditors: Pokedex, TeamCoverage, Teams, Draft, Matches, Trade')
        ->assertSuccessful();
});

it('resolves module audit registry from container', function () {
    expect(app(ModuleAuditRegistry::class))->toBeInstanceOf(ModuleAuditRegistry::class);
});

it('loads modules config', function () {
    expect(config('modules.v2.enabled'))->toBeArray();
});

it('exposes v2 preview nav links when modules are enabled', function () {
    expect(\App\Kernel\Support\V2PreviewNav::links())->toBe([
        ['module' => 'Pokedex', 'href' => '/pokedex'],
        ['module' => 'TeamCoverage', 'href' => '/team-coverage'],
        ['module' => 'Teams', 'href' => '/teams'],
        ['module' => 'Draft', 'href' => '/draft'],
        ['module' => 'Matches', 'href' => '/match'],
        ['module' => 'Trade', 'href' => '/v2/leagues/1/trades'],
    ]);
});

it('redirects v2 draft detail to production route', function () {
    $user = \App\Models\User::factory()->create();

    $this->actingAs($user)
        ->get('/v2/draft/1')
        ->assertRedirect('/draft/1');
})->group('v2');

it('redirects v2 teams index to production route', function () {
    $user = \App\Models\User::factory()->create();

    $this->actingAs($user)
        ->get('/v2/teams?league_id=1')
        ->assertRedirect('/teams?league_id=1');
})->group('v2');

it('redirects v2 match set detail to production route', function () {
    $user = \App\Models\User::factory()->create();

    $this->actingAs($user)
        ->get('/v2/match/set/1')
        ->assertRedirect('/match/set/1');
})->group('v2');
