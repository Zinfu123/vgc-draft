<?php

namespace App\Console\Commands;

use App\Modules\League\Services\LeagueDraftCsvImportService;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

class ImportLeagueDraftCsvCommand extends Command
{
    protected $signature = 'league:import-draft-csv
                            {path : Directory containing league_pokemon.csv, draft_config.csv, draft_order.csv, draft_picks.csv, drafts.csv, and sets.csv}
                            {--replace : Delete existing draft-related rows for every league_id present in the CSVs before importing}
                            {--dry-run : Parse CSVs and validate prerequisites without writing to the database}
                            {--skip-db-check : Skip validating leagues, users, teams, pokedex, and pools (insert may still fail on foreign keys)}';

    protected $description = 'Import league draft and match CSV exports with preserved primary keys and resynced ID sequences';

    public function handle(LeagueDraftCsvImportService $importer): int
    {
        $raw = (string) $this->argument('path');
        $path = $this->resolveImportDirectory($raw);
        if ($path === null) {
            $this->error("Directory not found: {$raw}");

            return self::FAILURE;
        }

        try {
            if ($this->option('dry-run')) {
                $summary = $importer->dryRun($path);
                $this->info('Dry run OK — no database changes were made.');
                foreach ($summary['files'] as $file => $count) {
                    $this->line("  {$file}: {$count} rows");
                }
                $this->line('  League IDs: '.implode(', ', $summary['league_ids']));

                return self::SUCCESS;
            }

            $summary = $importer->import(
                $path,
                (bool) $this->option('replace'),
                ! (bool) $this->option('skip-db-check'),
            );

            $this->info('Import completed.');
            foreach ($summary['inserted'] as $file => $count) {
                $this->line("  {$file}: {$count} rows inserted");
            }
            $this->line('  League IDs: '.implode(', ', $summary['league_ids']));
            $this->line('  ID sequences updated for this connection driver.');

            return self::SUCCESS;
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function resolveImportDirectory(string $raw): ?string
    {
        foreach ([$raw, base_path(trim($raw, '/\\'))] as $candidate) {
            $resolved = realpath($candidate);
            if ($resolved !== false && is_dir($resolved)) {
                return $resolved;
            }
        }

        return null;
    }
}
