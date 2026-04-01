<?php

use App\Modules\Pokedex\Models\VersionGroup;
use App\Modules\Stats\Models\VgcLadderSpeciesUsage;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

afterEach(function (): void {
    Carbon::setTestNow();
});

it('syncs using chaos url from config period and version group format key', function () {
    Config::set('showdown_vgc.chaos_base_url', 'https://stats.test');
    Config::set('showdown_vgc.import_period', '2026-03');

    $versionGroup = VersionGroup::query()->where('slug', 'scarlet-violet')->first();
    expect($versionGroup)->not->toBeNull();
    $versionGroup->update([
        'showdown_format_key' => 'gen9vgc2026test',
        'showdown_ladder_rating' => 1760,
    ]);

    Http::fake([
        'https://stats.test/2026-03/chaos/gen9vgc2026test-1760.json' => Http::response([
            'Pikachu' => ['Usage' => '10%'],
        ], 200),
    ]);

    $this->artisan('stats:sync-showdown-vgc-usage')->assertSuccessful();

    $row = VgcLadderSpeciesUsage::query()->where('species_key', 'pikachu')->first();
    expect($row)->not->toBeNull()
        ->and((float) $row->usage_percent)->toBe(10.0);
});

it('resolves period with HEAD when import_period is not set', function () {
    Carbon::setTestNow('2026-04-15 12:00:00');

    Config::set('showdown_vgc.chaos_base_url', 'https://stats.test');
    Config::set('showdown_vgc.import_period', null);

    $versionGroup = VersionGroup::query()->where('slug', 'scarlet-violet')->first();
    expect($versionGroup)->not->toBeNull();
    $versionGroup->update([
        'showdown_format_key' => 'gen9vgc2026head',
        'showdown_ladder_rating' => 1760,
    ]);

    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        $url = $request->url();
        if ($request->method() === 'HEAD' && str_contains($url, '/2026-04/')) {
            return Http::response('', 404);
        }
        if ($request->method() === 'HEAD' && str_contains($url, '/2026-03/')) {
            return Http::response('', 200);
        }
        if ($request->method() === 'GET' && str_contains($url, 'gen9vgc2026head-1760.json')) {
            return Http::response([
                'Pikachu' => ['Usage' => '7%'],
            ], 200);
        }

        return Http::response('not found', 404);
    });

    $this->artisan('stats:sync-showdown-vgc-usage')->assertSuccessful();

    $row = VgcLadderSpeciesUsage::query()->where('species_key', 'pikachu')->where('format_key', 'gen9vgc2026head')->first();
    expect($row)->not->toBeNull()
        ->and((float) $row->usage_percent)->toBe(7.0);
});

it('succeeds with no work when no version groups have showdown_format_key', function () {
    VersionGroup::query()->update(['showdown_format_key' => null, 'showdown_ladder_rating' => null]);

    $this->artisan('stats:sync-showdown-vgc-usage')->assertSuccessful();
});
