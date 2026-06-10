<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('adds is_bye on sets when the column is missing but the repair migration had already been recorded', function (): void {
    expect(Schema::hasColumn('sets', 'is_bye'))->toBeTrue();

    Schema::table('sets', function ($table): void {
        $table->dropColumn('is_bye');
    });

    expect(Schema::hasColumn('sets', 'is_bye'))->toBeFalse();

    DB::table('migrations')
        ->where('migration', '2026_05_18_141308_ensure_is_bye_column_exists_on_sets_table')
        ->delete();

    $this->artisan('migrate', [
        '--path' => 'database/migrations/2026_05_18_141308_ensure_is_bye_column_exists_on_sets_table.php',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect(Schema::hasColumn('sets', 'is_bye'))->toBeTrue();
});
