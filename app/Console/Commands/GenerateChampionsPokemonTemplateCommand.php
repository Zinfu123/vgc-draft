<?php

namespace App\Console\Commands;

use App\Modules\League\Models\LeaguePokemonTemplate;
use App\Modules\League\Models\LeaguePokemonTemplateRow;
use App\Modules\Pokedex\Models\Pokedex;
use App\Modules\Pokedex\Models\VersionGroup;
use App\Modules\Pokedex\Services\DraftPoolPokedexResolver;
use App\Modules\Pokedex\Services\PikalyticsChampionsUsageService;
use App\Modules\Pokedex\Services\SerebiiChampionsAvailableRosterService;
use App\Modules\Pokedex\Services\SerebiiChampionsImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GenerateChampionsPokemonTemplateCommand extends Command
{
    protected $signature = 'pokemon:champions-template-generate
                            {--name=Champions Reg MA : Display name for the template}
                            {--slug=champions-reg-ma : Unique slug for the template}
                            {--replace : Overwrite existing template rows if the slug already exists}
                            {--dry-run : Print the generated cost table without saving to the database}
                            {--no-publish : Save the template as unpublished}
                            {--usage-file= : Path to a CSV file (name,usage_pct) to use instead of fetching from Pikalytics}';

    protected $description = 'Generate a Champions draft template with Pikalytics-based point costs';

    private const VERSION_GROUP_SLUG = 'champions-reg-ma';

    public function handle(
        SerebiiChampionsAvailableRosterService $rosterService,
        SerebiiChampionsImporter $importer,
        PikalyticsChampionsUsageService $pikalytics,
        DraftPoolPokedexResolver $pokedexResolver,
    ): int {
        $versionGroup = VersionGroup::query()->where('slug', self::VERSION_GROUP_SLUG)->first();

        if ($versionGroup === null) {
            $this->error('Version group ['.self::VERSION_GROUP_SLUG.'] not found. Run migrations first.');

            return self::FAILURE;
        }

        $this->info('Fetching Champions roster from Serebii…');
        $rosterHtml = $rosterService->fetchRosterHtml();

        if ($rosterHtml === null) {
            $this->error('Could not download the Champions roster from Serebii.');

            return self::FAILURE;
        }

        $rosterRows = $rosterService->parseRosterRows($rosterHtml);

        if ($rosterRows === []) {
            $this->error('Roster page parsed zero species. Serebii HTML may have changed.');

            return self::FAILURE;
        }

        $usageMap = $this->resolveUsageMap($pikalytics);

        /** @var list<array{nationaldex_id: float, pokedex_name: string, display_name: string, usage_pct: float, cost: int, pokedex_id: int|null}> $entries */
        $entries = [];
        $skippedNoDex = [];

        foreach ($rosterRows as $row) {
            $pokedexName = $pokedexResolver->canonicalName(
                $rosterService->resolveRowToPokedexName($row, $importer),
            );

            // Mega forms are excluded — they are the same Pokemon as the base form
            // and Mega Evolution is a battle mechanic, not a separate draft pick.
            if (str_contains($pokedexName, '-mega')) {
                continue;
            }

            /** @var Pokedex|null $pokemon */
            $pokemon = $pokedexResolver->resolveByName($pokedexName);

            if ($pokemon === null) {
                $skippedNoDex[] = $pokedexName;

                continue;
            }

            $usagePct = $this->resolveUsage($row['display_name'], $pokedexName, $usageMap);
            $cost = PikalyticsChampionsUsageService::usageToCost($usagePct);

            $entries[] = [
                'nationaldex_id' => (float) $pokemon->getAttribute('nationaldex_id'),
                'pokedex_name' => $pokedexName,
                'display_name' => $row['display_name'],
                'usage_pct' => $usagePct,
                'cost' => $cost,
                'pokedex_id' => (int) $pokemon->id,
            ];
        }

        if ($skippedNoDex !== []) {
            $this->warn('Skipped (no pokedex row): '.implode(', ', $skippedNoDex));
        }

        if ($entries === []) {
            $this->error('No valid entries to save after resolving roster against pokedex table.');

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            return $this->outputDryRun($entries);
        }

        return $this->saveTemplate($entries, (int) $versionGroup->id);
    }

    /**
     * @return array<string, float>
     */
    private function resolveUsageMap(PikalyticsChampionsUsageService $pikalytics): array
    {
        $usageFilePath = $this->option('usage-file');

        if ($usageFilePath !== null && $usageFilePath !== '') {
            $resolved = realpath((string) $usageFilePath);
            if ($resolved === false || ! is_readable($resolved)) {
                $this->error("Usage file not found or unreadable: {$usageFilePath}");

                return [];
            }

            $map = $pikalytics->parseUsageCsv($resolved);
            $this->info('Loaded usage data for '.count($map).' Pokémon from file: '.$usageFilePath);

            return $map;
        }

        $this->info('Fetching usage stats from Pikalytics…');
        $result = $pikalytics->fetchUsageMapWithResult();

        if ($result['error'] !== null) {
            $this->warn('Could not fetch Pikalytics data: '.$result['error']);
            $this->warn('All Pokémon will receive cost 1. Use --usage-file=path/to/usage.csv to provide data manually.');
            $this->newLine();
            $this->line('  CSV format: one row per Pokémon, two columns:');
            $this->line('    pokemon_name,usage_pct');
            $this->line('    incineroar,54.2');
            $this->line('    flutter-mane,12.5');

            return [];
        }

        $this->info('Loaded usage data for '.count($result['map']).' Pokémon from Pikalytics.');

        return $result['map'];
    }

    /**
     * Resolve usage % for a Pokemon by trying multiple slug lookups.
     *
     * @param  array<string, float>  $usageMap
     */
    private function resolveUsage(string $displayName, string $pokedexName, array $usageMap): float
    {
        // Primary: slug the display name from Serebii (matches how Pikalytics typically titles things).
        $displaySlug = Str::slug($displayName);
        if (isset($usageMap[$displaySlug])) {
            return $usageMap[$displaySlug];
        }

        // Secondary: try the internal pokedex name slug (already hyphenated).
        if (isset($usageMap[$pokedexName])) {
            return $usageMap[$pokedexName];
        }

        // Tertiary: for megas, try "mega-{base}" format.
        if (str_contains($pokedexName, '-mega')) {
            $base = str_replace('-mega', '', $pokedexName);
            $megaSlug = 'mega-'.$base;
            if (isset($usageMap[$megaSlug])) {
                return $usageMap[$megaSlug];
            }
        }

        return 0.0;
    }

    /**
     * @param  list<array{nationaldex_id: float, pokedex_name: string, display_name: string, usage_pct: float, cost: int, pokedex_id: int|null}>  $entries
     */
    private function outputDryRun(array $entries): int
    {
        $rows = array_map(fn (array $e): array => [
            (string) $e['nationaldex_id'],
            $e['pokedex_name'],
            $e['display_name'],
            $e['usage_pct'] > 0 ? number_format($e['usage_pct'], 2).'%' : '—',
            (string) $e['cost'],
        ], $entries);

        $this->table(['National Dex', 'Pokedex Name', 'Display Name', 'Usage %', 'Cost'], $rows);
        $this->info('Dry run complete. '.count($entries).' Pokémon. Pass --replace to save.');

        return self::SUCCESS;
    }

    /**
     * @param  list<array{nationaldex_id: float, pokedex_name: string, display_name: string, usage_pct: float, cost: int, pokedex_id: int|null}>  $entries
     */
    private function saveTemplate(array $entries, int $versionGroupId): int
    {
        $slug = (string) $this->option('slug');
        $name = (string) $this->option('name');
        $replace = (bool) $this->option('replace');
        $isPublished = ! $this->option('no-publish');

        $existing = LeaguePokemonTemplate::query()->where('slug', $slug)->first();

        if ($existing !== null && ! $replace) {
            $this->error("A template with slug \"{$slug}\" already exists. Use --replace to overwrite.");

            return self::FAILURE;
        }

        DB::transaction(function () use ($entries, $slug, $name, $versionGroupId, $isPublished, $existing): void {
            if ($existing !== null) {
                $existing->update([
                    'name' => $name,
                    'version_group_id' => $versionGroupId,
                    'is_published' => $isPublished,
                ]);
                LeaguePokemonTemplateRow::query()
                    ->where('league_pokemon_template_id', $existing->id)
                    ->delete();
                $template = $existing;
            } else {
                $template = LeaguePokemonTemplate::query()->create([
                    'name' => $name,
                    'slug' => $slug,
                    'description' => null,
                    'version_group_id' => $versionGroupId,
                    'is_published' => $isPublished,
                ]);
            }

            foreach ($entries as $entry) {
                if ($entry['pokedex_id'] === null) {
                    continue;
                }

                LeaguePokemonTemplateRow::query()->create([
                    'league_pokemon_template_id' => $template->id,
                    'pokedex_id' => $entry['pokedex_id'],
                    'cost' => $entry['cost'],
                ]);
            }
        });

        $this->info('Template "'.$name.'" saved with '.count($entries).' Pokémon (slug: '.$slug.').');

        if (! $isPublished) {
            $this->warn('Template saved as unpublished. Publish it via the database when ready.');
        }

        return self::SUCCESS;
    }
}
