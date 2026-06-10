<?php

namespace App\Console\Commands;

use App\Jobs\ImportSinglePokedexGameDataJob;
use App\Modules\Pokedex\Models\Pokedex;
use App\Modules\Pokedex\Models\VersionGroup;
use App\Modules\Pokedex\Services\PokeApiPokemonGameDataImporter;
use Illuminate\Console\Command;

class PokemonImportVersionGroupCommand extends Command
{
    /**
     * Pokedex {@see Pokedex::$name} values for Ogerpon mask forms (not the base Teal form).
     *
     * @var list<string>
     */
    public const OGERPON_MASK_POKEDEX_NAMES = [
        'ogerpon-wellspring',
        'ogerpon-hearthflame',
        'ogerpon-cornerstone',
    ];

    protected $signature = 'pokemon:import-version-group
                            {slug? : PokeAPI version group slug (e.g. scarlet-violet)}
                            {--id= : Import only this pokedex row id}
                            {--ogerpon-mask-forms : Re-import only ogerpon-wellspring, ogerpon-hearthflame, and ogerpon-cornerstone (refreshes wrong variety data)}
                            {--async : Dispatch a queued job per species instead of running synchronously}
                            {--only-missing : Skip species that already have pokemon_generation_data for this version group (resume)}
                            {--chunk= : Max species to process this run (use with --only-missing for batched resume)}';

    protected $description = 'Import or refresh pokemon_generation_data from PokeAPI for a version group';

    public function handle(PokeApiPokemonGameDataImporter $importer): int
    {
        $slug = $this->argument('slug') ?: (string) config('pokemon.default_version_group_slug');
        $versionGroup = VersionGroup::query()->where('slug', $slug)->first();
        if ($versionGroup === null) {
            $this->error("Unknown version group slug: {$slug}");

            return self::FAILURE;
        }

        $query = Pokedex::query()->orderBy('id');
        if ($this->option('id') !== null) {
            $query->where('id', (int) $this->option('id'));
        }

        if ($this->option('ogerpon-mask-forms')) {
            $query->whereIn('name', self::OGERPON_MASK_POKEDEX_NAMES);
        }

        if ($this->option('only-missing')) {
            $query->whereDoesntHave('generationData', function ($q) use ($versionGroup) {
                $q->where('version_group_id', $versionGroup->id);
            });
        }

        $chunk = $this->option('chunk');
        if ($chunk !== null && $chunk !== '') {
            $query->limit(max(1, (int) $chunk));
        }

        $count = $query->count();
        if ($count === 0) {
            $this->info("Nothing to import for [{$slug}]".($this->option('only-missing') ? ' (all species already have data, or no rows match).' : '.'));

            return self::SUCCESS;
        }

        $suffix = [];
        if ($this->option('only-missing')) {
            $suffix[] = 'only rows without data';
        }
        if ($chunk !== null && $chunk !== '') {
            $suffix[] = 'max '.(int) $chunk.' this run';
        }
        $hint = $suffix !== [] ? ' ('.implode(', ', $suffix).')' : '';

        $this->info("Importing {$count} species for version group [{$slug}]{$hint}…");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        foreach ($query->cursor() as $pokedex) {
            if ($this->option('async')) {
                ImportSinglePokedexGameDataJob::dispatch($pokedex->id, $versionGroup->id);
            } else {
                $importer->import($pokedex, $versionGroup);
                usleep(150_000);
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        if ($this->option('async')) {
            $this->info('Jobs dispatched. Run a queue worker to process them.');
        } else {
            $this->info('Import finished.');
        }

        if ($this->option('only-missing') && ($chunk !== null && $chunk !== '') && $count === (int) $chunk) {
            $remaining = Pokedex::query()
                ->whereDoesntHave('generationData', function ($q) use ($versionGroup) {
                    $q->where('version_group_id', $versionGroup->id);
                })
                ->when($this->option('id') !== null, fn ($q) => $q->where('id', (int) $this->option('id')))
                ->count();
            if ($remaining > 0) {
                $this->warn("{$remaining} species still missing data. Re-run with the same flags to continue.");
            }
        }

        return self::SUCCESS;
    }
}
