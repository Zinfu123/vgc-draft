<?php

use Illuminate\Support\Facades\DB;

it('refuses import without --fresh or --truncate', function () {
    $path = database_path('data');
    if (! is_dir($path)) {
        $this->markTestSkipped('database/data is not present');
    }

    $this->artisan('migrate:fresh', ['--force' => true]);
    $this->artisan('db:import-csv', ['--path' => $path])->assertFailed();
});

it('dry run reports row counts without writing', function () {
    $path = database_path('data');
    if (! is_dir($path)) {
        $this->markTestSkipped('database/data is not present');
    }

    $this->artisan('migrate:fresh', ['--force' => true]);
    $beforeUsers = DB::table('users')->count();

    $this->artisan('db:import-csv', ['--path' => $path, '--dry-run' => true])->assertSuccessful();

    expect(DB::table('users')->count())->toBe($beforeUsers);
});

it('imports csv dump when tables are truncated after migrate fresh', function () {
    $path = database_path('data');
    if (! is_dir($path)) {
        $this->markTestSkipped('database/data is not present');
    }

    $this->artisan('migrate:fresh', ['--force' => true]);
    $this->artisan('db:import-csv', ['--path' => $path, '--truncate' => true])->assertSuccessful();

    expect(DB::table('users')->count())->toBeGreaterThan(0);
    expect(DB::table('pokedex')->count())->toBeGreaterThan(50);
});
