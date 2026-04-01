<?php

use App\Modules\Pokedex\Models\VersionGroup;
use App\Modules\Stats\Models\VgcLadderSpeciesUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('imports vgc chaos json when format is allowlisted', function () {
    $vg = VersionGroup::query()->where('slug', 'scarlet-violet')->first();
    expect($vg)->not->toBeNull();
    $vg->update(['showdown_format_key' => 'gen9vgc2026test']);

    Http::fake([
        'https://example.test/stats.json' => Http::response([
            'Incineroar' => [
                'Usage' => '47.15%',
                'Moves' => ['Fake Out' => 0.99],
            ],
        ], 200),
    ]);

    $this->artisan('stats:import-showdown-vgc', [
        'url' => 'https://example.test/stats.json',
        '--format' => 'gen9vgc2026test',
        '--period' => '2026-03',
    ])->assertExitCode(0);

    $row = VgcLadderSpeciesUsage::query()->where('species_key', 'incineroar')->first();
    expect($row)->not->toBeNull()
        ->and((float) $row->usage_percent)->toBe(47.15);
});

it('imports smogon chaos wrapper with data key and fractional usage', function () {
    Http::fake([
        'https://example.test/wrapped.json' => Http::response([
            'info' => ['metagame' => 'gen9vgc2026regi'],
            'data' => [
                'Incineroar' => [
                    'usage' => 0.4715,
                    'Moves' => ['Fake Out' => 0.99],
                ],
            ],
        ], 200),
    ]);

    $this->artisan('stats:import-showdown-vgc', [
        'url' => 'https://example.test/wrapped.json',
        '--format' => 'gen9vgc2026wrapped',
        '--period' => '2026-03',
    ])->assertExitCode(0);

    $row = VgcLadderSpeciesUsage::query()->where('species_key', 'incineroar')->first();
    expect($row)->not->toBeNull()
        ->and((float) $row->usage_percent)->toBe(47.15);
});

it('rejects non vgc format keys', function () {
    $this->artisan('stats:import-showdown-vgc', [
        'url' => 'https://example.test/x.json',
        '--format' => 'gen9ou',
    ])->assertExitCode(1);
});
