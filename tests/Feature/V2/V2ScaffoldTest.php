<?php

use App\Kernel\Audit\ModuleAuditRegistry;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('exposes v2 health endpoint with enabled modules list', function () {
    $response = $this->get('/v2');

    $response->assertSuccessful()
        ->assertJson([
            'version' => 2,
            'modules' => [],
        ]);
});

it('registers module audit command', function () {
    $this->artisan('module:audit')
        ->expectsOutput('No module auditors registered yet.')
        ->assertSuccessful();
});

it('resolves module audit registry from container', function () {
    expect(app(ModuleAuditRegistry::class))->toBeInstanceOf(ModuleAuditRegistry::class);
});

it('loads modules config', function () {
    expect(config('modules.v2.enabled'))->toBeArray();
});

it('hides v2 preview nav when no modules are enabled', function () {
    expect(\App\Kernel\Support\V2PreviewNav::isVisible())->toBeFalse();
    expect(\App\Kernel\Support\V2PreviewNav::links())->toBe([]);
});

it('renders teams index page', function () {
    $user = \App\Models\User::factory()->create();

    $this->actingAs($user)
        ->get('/teams?league_id=1')
        ->assertSuccessful();
})->group('v2');

it('renders pool detail page', function () {
    $user = \App\Models\User::factory()->create();

    $this->actingAs($user)
        ->get('/pools/1')
        ->assertSuccessful();
})->group('v2');
