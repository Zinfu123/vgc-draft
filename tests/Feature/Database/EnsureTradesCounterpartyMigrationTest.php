<?php

use Illuminate\Support\Facades\Schema;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('ensures trades has a nullable target team id and counterparty column', function () {
    expect(Schema::hasColumn('trades', 'counterparty'))->toBeTrue();

    $targetTeamColumn = collect(Schema::getColumns('trades'))
        ->firstWhere('name', 'target_team_id');

    expect($targetTeamColumn)->not->toBeNull()
        ->and($targetTeamColumn['nullable'] ?? false)->toBeTrue();
});

it('can run the ensure counterparty migration twice safely', function () {
    $path = database_path('migrations/2026_05_23_215659_ensure_counterparty_and_nullable_target_team_on_trades_table.php');
    $migration = include $path;

    $migration->up();
    $migration->up();

    expect(Schema::hasColumn('trades', 'counterparty'))->toBeTrue();
});
