<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

use App\Modules\League\Models\League;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('resyncs sqlite sequences for populated tables', function () {
    $owner = User::factory()->create();

    DB::table('leagues')->insert([
        'id' => 50,
        'name' => 'Restored League',
        'league_owner' => $owner->id,
        'set_frequency' => 7,
        'status' => 2,
        'open' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    if (Schema::hasTable('sqlite_sequence')) {
        DB::table('sqlite_sequence')->updateOrInsert(
            ['name' => 'leagues'],
            ['seq' => 1],
        );
    }

    $this->artisan('db:resync-sequences', ['--table' => ['leagues']])
        ->assertSuccessful()
        ->expectsOutputToContain('ID sequences updated:');

    if (Schema::hasTable('sqlite_sequence')) {
        expect((int) DB::table('sqlite_sequence')->where('name', 'leagues')->value('seq'))->toBe(50);
    }

    $league = League::create([
        'name' => 'Next League',
        'league_owner' => $owner->id,
        'set_frequency' => 7,
    ]);

    expect($league->id)->toBe(51);
});

it('scans all tables when no --table option is passed', function () {
    $owner = User::factory()->create();

    DB::table('leagues')->insert([
        'id' => 10,
        'name' => 'Bulk Resync League',
        'league_owner' => $owner->id,
        'set_frequency' => 7,
        'status' => 2,
        'open' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    if (Schema::hasTable('sqlite_sequence')) {
        DB::table('sqlite_sequence')->updateOrInsert(
            ['name' => 'leagues'],
            ['seq' => 1],
        );
    }

    $this->artisan('db:resync-sequences')
        ->assertSuccessful()
        ->expectsOutputToContain('leagues');
});

it('reports when no sequences need updating', function () {
    $this->artisan('db:resync-sequences', ['--table' => ['missing_table_name']])
        ->assertSuccessful()
        ->expectsOutputToContain('No sequences were updated');
});
