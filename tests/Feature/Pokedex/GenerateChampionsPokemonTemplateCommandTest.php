<?php

use App\Modules\League\Models\LeaguePokemonTemplate;
use App\Modules\League\Models\LeaguePokemonTemplateRow;
use App\Modules\Pokedex\Services\PikalyticsChampionsUsageService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function championsTemplateVersionGroup(): object
{
    return DB::table('version_groups')->where('slug', 'champions-reg-ma')->first();
}

function insertPokedexRow(int $nationalDexId, string $name, float $nationaldexId = 0): int
{
    return DB::table('pokedex')->insertGetId([
        'nationaldex_id' => $nationaldexId ?: $nationalDexId,
        'name' => $name,
        'type1' => 'Grass',
        'type2' => null,
        'sprite_url' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function serebiiRosterHtml(): string
{
    return (string) file_get_contents(base_path('tests/Fixtures/Serebii/champions_available_roster_min.html'));
}

function pikalyticsHtml(): string
{
    return (string) file_get_contents(base_path('tests/Fixtures/Pikalytics/champions_usage_min.html'));
}

function fakeHttpResponses(): void
{
    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        if (str_contains($request->url(), 'pokemonchampions/pokemon.shtml')) {
            return Http::response(serebiiRosterHtml(), 200, ['Content-Type' => 'text/html']);
        }

        if (str_contains($request->url(), 'pikalytics.com')) {
            return Http::response(pikalyticsHtml(), 200, ['Content-Type' => 'text/html']);
        }

        return Http::response('unmocked', 404);
    });
}

it('exits with failure when champions-reg-ma version group is missing', function () {
    DB::table('version_groups')->where('slug', 'champions-reg-ma')->delete();

    $this->artisan('pokemon:champions-template-generate')
        ->expectsOutputToContain('Version group [champions-reg-ma] not found')
        ->assertExitCode(1);
});

it('exits with failure when serebii roster fetch fails', function () {
    championsTemplateVersionGroup();

    Http::fake(fn () => Http::response('', 503));

    $this->artisan('pokemon:champions-template-generate')
        ->expectsOutputToContain('Could not download the Champions roster from Serebii')
        ->assertExitCode(1);
});

it('creates template rows with correct tiered costs based on usage', function () {
    championsTemplateVersionGroup();

    fakeHttpResponses();

    insertPokedexRow(3, 'venusaur', 3);
    insertPokedexRow(3, 'venusaur-mega', 3.001);

    $this->artisan('pokemon:champions-template-generate', ['--replace' => true])
        ->assertExitCode(0);

    $template = LeaguePokemonTemplate::query()->where('slug', 'champions-reg-ma')->first();
    expect($template)->not->toBeNull();
    expect($template->name)->toBe('Champions Reg MA');
    expect($template->is_published)->toBeTrue();

    $rows = LeaguePokemonTemplateRow::query()
        ->where('league_pokemon_template_id', $template->id)
        ->get()
        ->keyBy('pokedex_id');

    $venusaurId = DB::table('pokedex')->where('name', 'venusaur')->value('id');

    // Venusaur: 0.30% usage → cost 2 (0.1–0.5% tier)
    expect($rows->get($venusaurId)?->cost)->toBe(2);
});

it('excludes mega forms from the template entirely', function () {
    championsTemplateVersionGroup();

    fakeHttpResponses();

    insertPokedexRow(3, 'venusaur', 3);
    insertPokedexRow(3, 'venusaur-mega', 3.001);

    $this->artisan('pokemon:champions-template-generate', ['--replace' => true])
        ->assertExitCode(0);

    $template = LeaguePokemonTemplate::query()->where('slug', 'champions-reg-ma')->firstOrFail();

    $megaRow = LeaguePokemonTemplateRow::query()
        ->where('league_pokemon_template_id', $template->id)
        ->whereHas('pokedex', fn ($q) => $q->where('name', 'venusaur-mega'))
        ->exists();

    expect($megaRow)->toBeFalse();
});

it('outputs a table in dry-run mode without saving to the database', function () {
    championsTemplateVersionGroup();

    fakeHttpResponses();

    insertPokedexRow(3, 'venusaur', 3);
    insertPokedexRow(3, 'venusaur-mega', 3.001);

    $this->artisan('pokemon:champions-template-generate', ['--dry-run' => true])
        ->expectsOutputToContain('Dry run complete')
        ->expectsOutputToContain('venusaur')
        ->assertExitCode(0);

    expect(LeaguePokemonTemplate::query()->where('slug', 'champions-reg-ma')->exists())->toBeFalse();
});

it('replaces existing template rows when --replace is passed', function () {
    championsTemplateVersionGroup();

    fakeHttpResponses();

    $venusaurId = insertPokedexRow(3, 'venusaur', 3);

    // First run: create template.
    $this->artisan('pokemon:champions-template-generate', ['--replace' => true])
        ->assertExitCode(0);

    $firstCount = LeaguePokemonTemplateRow::query()
        ->whereHas('template', fn ($q) => $q->where('slug', 'champions-reg-ma'))
        ->count();

    // Ensure venusaur-mega exists for the second run too.
    insertPokedexRow(3, 'venusaur-mega', 3.001);

    // Second run with --replace: should overwrite.
    $this->artisan('pokemon:champions-template-generate', ['--replace' => true])
        ->expectsOutputToContain('saved')
        ->assertExitCode(0);

    expect(LeaguePokemonTemplate::query()->where('slug', 'champions-reg-ma')->count())->toBe(1);
});

it('fails when template already exists and --replace is not passed', function () {
    championsTemplateVersionGroup();

    fakeHttpResponses();

    insertPokedexRow(3, 'venusaur', 3);

    // First run creates the template.
    $this->artisan('pokemon:champions-template-generate', ['--replace' => true])
        ->assertExitCode(0);

    // Second run without --replace should fail.
    $this->artisan('pokemon:champions-template-generate')
        ->expectsOutputToContain('already exists')
        ->assertExitCode(1);
});

it('saves template as unpublished when --no-publish is passed', function () {
    championsTemplateVersionGroup();

    fakeHttpResponses();

    insertPokedexRow(3, 'venusaur', 3);
    insertPokedexRow(3, 'venusaur-mega', 3.001);

    $this->artisan('pokemon:champions-template-generate', ['--replace' => true, '--no-publish' => true])
        ->assertExitCode(0);

    $template = LeaguePokemonTemplate::query()->where('slug', 'champions-reg-ma')->first();
    expect($template?->is_published)->toBeFalse();
});

it('falls back to cost 1 for all pokemon when pikalytics fetch fails', function () {
    championsTemplateVersionGroup();

    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        if (str_contains($request->url(), 'pokemonchampions/pokemon.shtml')) {
            return Http::response(serebiiRosterHtml(), 200, ['Content-Type' => 'text/html']);
        }

        return Http::response('', 503);
    });

    insertPokedexRow(3, 'venusaur', 3);
    insertPokedexRow(3, 'venusaur-mega', 3.001);

    $this->artisan('pokemon:champions-template-generate', ['--replace' => true])
        ->expectsOutputToContain('Could not fetch Pikalytics data')
        ->expectsOutputToContain('--usage-file')
        ->assertExitCode(0);

    $template = LeaguePokemonTemplate::query()->where('slug', 'champions-reg-ma')->first();
    expect($template)->not->toBeNull();

    $allCosts = LeaguePokemonTemplateRow::query()
        ->where('league_pokemon_template_id', $template->id)
        ->pluck('cost')
        ->all();

    expect(array_unique($allCosts))->toBe([1]);
});

it('loads usage data from a csv file when --usage-file is passed', function () {
    championsTemplateVersionGroup();

    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        if (str_contains($request->url(), 'pokemonchampions/pokemon.shtml')) {
            return Http::response(serebiiRosterHtml(), 200, ['Content-Type' => 'text/html']);
        }

        return Http::response('', 503); // Pikalytics unreachable.
    });

    insertPokedexRow(3, 'venusaur', 3);
    insertPokedexRow(727, 'incineroar', 727);

    $csvPath = tempnam(sys_get_temp_dir(), 'usage_test_');
    file_put_contents($csvPath, "name,usage_pct\nvenusaur,22.0\nincineroar,54.0\n");

    $this->artisan('pokemon:champions-template-generate', ['--replace' => true, '--usage-file' => $csvPath])
        ->expectsOutputToContain('Loaded usage data for 2 Pokémon from file')
        ->assertExitCode(0);

    unlink($csvPath);

    $template = LeaguePokemonTemplate::query()->where('slug', 'champions-reg-ma')->first();
    expect($template)->not->toBeNull();

    $rows = LeaguePokemonTemplateRow::query()
        ->where('league_pokemon_template_id', $template->id)
        ->get()
        ->keyBy('pokedex_id');

    $venusaurId = DB::table('pokedex')->where('name', 'venusaur')->value('id');
    $incineroarId = DB::table('pokedex')->where('name', 'incineroar')->value('id');

    expect($rows->get($venusaurId)?->cost)->toBe(10); // 22% → cost 10
    expect($rows->get($incineroarId)?->cost)->toBe(10); // 54% → cost 10
});

it('warns about and skips pokemon that have no pokedex row', function () {
    championsTemplateVersionGroup();

    fakeHttpResponses();

    // Insert incineroar (valid) but NOT venusaur — venusaur should be warned about.
    // venusaur-mega is in the fixture but will be silently excluded as a mega form.
    insertPokedexRow(727, 'incineroar', 727);

    $exitCode = \Illuminate\Support\Facades\Artisan::call('pokemon:champions-template-generate', ['--replace' => true]);
    $output = \Illuminate\Support\Facades\Artisan::output();

    expect($exitCode)->toBe(0);
    expect($output)->toContain('Skipped (no pokedex row): venusaur');
});

// Unit tests for the cost tier algorithm.
describe('PikalyticsChampionsUsageService::usageToCost', function () {
    it('assigns cost 10 for usage >= 20%', function () {
        expect(PikalyticsChampionsUsageService::usageToCost(54.2))->toBe(10);
        expect(PikalyticsChampionsUsageService::usageToCost(20.0))->toBe(10);
    });

    it('assigns cost 9 for usage 12–20%', function () {
        expect(PikalyticsChampionsUsageService::usageToCost(15.0))->toBe(9);
        expect(PikalyticsChampionsUsageService::usageToCost(12.0))->toBe(9);
    });

    it('assigns cost 8 for usage 8–12%', function () {
        expect(PikalyticsChampionsUsageService::usageToCost(8.75))->toBe(8);
        expect(PikalyticsChampionsUsageService::usageToCost(8.0))->toBe(8);
    });

    it('assigns cost 7 for usage 5–8%', function () {
        expect(PikalyticsChampionsUsageService::usageToCost(6.5))->toBe(7);
    });

    it('assigns cost 6 for usage 3–5%', function () {
        expect(PikalyticsChampionsUsageService::usageToCost(4.0))->toBe(6);
    });

    it('assigns cost 5 for usage 2–3%', function () {
        expect(PikalyticsChampionsUsageService::usageToCost(2.5))->toBe(5);
    });

    it('assigns cost 4 for usage 1–2%', function () {
        expect(PikalyticsChampionsUsageService::usageToCost(1.5))->toBe(4);
    });

    it('assigns cost 3 for usage 0.5–1%', function () {
        expect(PikalyticsChampionsUsageService::usageToCost(0.75))->toBe(3);
    });

    it('assigns cost 2 for usage 0.1–0.5%', function () {
        expect(PikalyticsChampionsUsageService::usageToCost(0.30))->toBe(2);
    });

    it('assigns cost 1 for usage below 0.1% or zero', function () {
        expect(PikalyticsChampionsUsageService::usageToCost(0.0))->toBe(1);
        expect(PikalyticsChampionsUsageService::usageToCost(0.05))->toBe(1);
    });
});

it('parses pikalytics usage html correctly', function () {
    $service = new PikalyticsChampionsUsageService;
    $html = pikalyticsHtml();

    $usageMap = $service->parseUsageMap($html);

    expect($usageMap)->toHaveKey('incineroar');
    expect($usageMap['incineroar'])->toBe(54.20);

    expect($usageMap)->toHaveKey('flutter-mane');
    expect($usageMap['flutter-mane'])->toBe(12.50);

    expect($usageMap)->toHaveKey('venusaur');
    expect($usageMap['venusaur'])->toBe(0.30);

    expect($usageMap)->toHaveKey('mega-venusaur');
    expect($usageMap['mega-venusaur'])->toBe(8.75);
});
