<?php

namespace App\Console\Commands;

use App\Modules\Pokedex\Models\Pokedex;
use App\Modules\Pokedex\Models\VersionGroup;
use App\Modules\Pokedex\Services\SerebiiChampionsAvailableRosterService;
use App\Modules\Pokedex\Services\SerebiiChampionsImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class PokemonImportChampionsSerebiiCommand extends Command
{
    protected $signature = 'pokemon:import-champions-serebii
                            {--id= : Import only this pokedex row id}
                            {--only-missing : Skip species that already have pokemon_generation_data for champions-reg-ma (resume)}
                            {--chunk= : Max species to process this run (use with --only-missing for batched resume)}
                            {--roster-only : Only import species listed on Serebii\'s Champions available Pokémon page}
                            {--failure-log= : Append failed/skipped species to this file (default: storage/logs/champions-serebii-import-failures.log)}
                            {--no-failure-log : Do not write a failure log file (console table is still shown)}';

    protected $description = 'Import Pokémon Champions data from Serebii (moves, stats, abilities) for the champions-reg-ma version group';

    public function handle(SerebiiChampionsImporter $importer, SerebiiChampionsAvailableRosterService $rosterService): int
    {
        $slug = 'champions-reg-ma';
        $versionGroup = VersionGroup::query()->where('slug', $slug)->first();

        if ($versionGroup === null) {
            $this->error("Version group [{$slug}] not found. Run migrations first.");

            return self::FAILURE;
        }

        if ($this->option('roster-only') && $this->option('id') !== null) {
            $this->error('Options --roster-only and --id cannot be used together.');

            return self::FAILURE;
        }

        $championsRosterNames = null;
        $query = Pokedex::query()->orderBy('id');

        if ($this->option('roster-only')) {
            $html = $rosterService->fetchRosterHtml();
            if ($html === null) {
                $this->error('Could not download the Champions available Pokémon roster from Serebii.');

                return self::FAILURE;
            }

            $championsRosterNames = $rosterService->resolveUniquePokedexNamesFromHtml($html, $importer);
            if ($championsRosterNames === []) {
                $this->error('Roster page parsed zero species. Serebii HTML may have changed.');

                return self::FAILURE;
            }

            $query->whereIn('name', $championsRosterNames);
        } elseif ($this->option('id') !== null) {
            $query->where('id', (int) $this->option('id'));
        }

        if ($this->option('only-missing')) {
            $query->whereDoesntHave('generationData', function ($q) use ($versionGroup): void {
                $q->where('version_group_id', $versionGroup->id);
            });
        }

        $chunk = $this->option('chunk');
        if ($chunk !== null && $chunk !== '') {
            $query->limit(max(1, (int) $chunk));
        }

        // count() on the query builder ignores limit(); use the limited result set size.
        $pokedexRows = $query->get();

        if ($championsRosterNames !== null) {
            $presentNames = Pokedex::query()
                ->whereIn('name', $championsRosterNames)
                ->pluck('name')
                ->unique()
                ->values()
                ->all();
            $missingPokedex = array_values(array_diff($championsRosterNames, $presentNames));
            if ($missingPokedex !== []) {
                $this->warn('Some roster species have no pokedex row yet (skipped): '.implode(', ', $missingPokedex));
            }
        }

        $count = $pokedexRows->count();
        if ($count === 0) {
            $this->info('Nothing to import'.($this->option('only-missing') ? ' (all species already have data, or no rows match).' : '.'));

            return self::SUCCESS;
        }

        $suffix = [];
        if ($championsRosterNames !== null) {
            $suffix[] = 'Champions available roster ('.count($championsRosterNames).' species)';
        }
        if ($this->option('only-missing')) {
            $suffix[] = 'only rows without data';
        }
        if ($chunk !== null && $chunk !== '') {
            $suffix[] = 'max '.(int) $chunk.' this run';
        }
        $hint = $suffix !== [] ? ' ('.implode(', ', $suffix).')' : '';

        $this->info("Importing {$count} species from Serebii for [{$slug}]{$hint}…");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $succeeded = 0;
        $failed = 0;
        /** @var list<array{pokedex_id: int, name: string, reason: string, serebii_url: string}> $failures */
        $failures = [];

        foreach ($pokedexRows as $pokedex) {
            $result = $importer->import($pokedex, $versionGroup);
            if ($result->success) {
                $succeeded++;
            } else {
                $failed++;
                $failures[] = [
                    'pokedex_id' => (int) $pokedex->id,
                    'name' => (string) $pokedex->getAttribute('name'),
                    'reason' => (string) $result->failureReason,
                    'serebii_url' => (string) ($result->serebiiUrl ?? ''),
                ];
            }

            usleep(150_000);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Import finished. Succeeded: {$succeeded}, Failed/skipped: {$failed}.");

        if ($failed > 0) {
            $tableRows = array_map(
                fn (array $f): array => [
                    (string) $f['pokedex_id'],
                    $f['name'],
                    $f['reason'],
                    $f['serebii_url'],
                ],
                $failures
            );
            $this->newLine();
            $this->warn('Failed or skipped imports:');
            $this->table(['Pokedex ID', 'Name', 'Reason', 'Serebii URL'], $tableRows);

            if (! $this->option('no-failure-log')) {
                $logPath = $this->option('failure-log');
                $logPath = ($logPath !== null && $logPath !== '')
                    ? (str_starts_with($logPath, '/') ? $logPath : base_path($logPath))
                    : storage_path('logs/champions-serebii-import-failures.log');

                $dir = dirname($logPath);
                if (! is_dir($dir)) {
                    File::makeDirectory($dir, 0755, true);
                }

                $lines = [
                    '--- '.now()->toIso8601String()." — {$failed} failure(s) — [{$slug}] ---",
                ];
                foreach ($failures as $f) {
                    $lines[] = sprintf(
                        "%d\t%s\t%s\t%s",
                        $f['pokedex_id'],
                        $f['name'],
                        str_replace(["\n", "\r", "\t"], ' ', $f['reason']),
                        $f['serebii_url']
                    );
                }
                $lines[] = '';
                File::append($logPath, implode("\n", $lines));
                $this->info("Failure log appended to: {$logPath}");
            }
        }

        if ($this->option('only-missing') && ($chunk !== null && $chunk !== '') && $count === (int) $chunk) {
            $remaining = Pokedex::query()
                ->whereDoesntHave('generationData', function ($q) use ($versionGroup): void {
                    $q->where('version_group_id', $versionGroup->id);
                })
                ->when($this->option('id') !== null, fn ($q) => $q->where('id', (int) $this->option('id')))
                ->when($championsRosterNames !== null, fn ($q) => $q->whereIn('name', $championsRosterNames))
                ->count();

            if ($remaining > 0) {
                $this->warn("{$remaining} species still missing data. Re-run with the same flags to continue.");
            }
        }

        return self::SUCCESS;
    }
}
