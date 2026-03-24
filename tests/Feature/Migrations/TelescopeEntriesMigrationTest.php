<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can run the telescope tables migration up more than once', function () {
    $migration = require database_path('migrations/2026_03_23_135820_create_telescope_entries_table.php');

    $migration->up();
    $migration->up();
})->throwsNoExceptions();
