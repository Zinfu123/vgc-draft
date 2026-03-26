<?php

namespace App\Console\Commands;

use App\Console\Services\DatabaseCsvDumpImportService;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

class ImportDatabaseCsvDumpCommand extends Command
{
    protected $signature = 'db:import-csv
                            {--path= : Directory containing CSV dumps (default: database/data)}
                            {--dry-run : List row counts per CSV without changing the database}
                            {--truncate : Truncate importer-managed tables before load (PostgreSQL: TRUNCATE … CASCADE)}
                            {--fresh : Run migrate:fresh --force, then truncate importer tables and load CSVs}';

    protected $description = 'Import CSV dumps from database/data in foreign-key order (skips migrations.csv; resyncs ID sequences). --fresh truncates importer tables after migrations to clear any migration-seeded rows.';

    public function handle(DatabaseCsvDumpImportService $importer): int
    {
        $rawPath = $this->option('path');
        $path = $rawPath !== null && $rawPath !== ''
            ? (string) $rawPath
            : DatabaseCsvDumpImportService::defaultDataDirectory();

        if (! is_dir($path)) {
            $this->error("Directory not found: {$path}");

            return self::FAILURE;
        }

        try {
            if ($this->option('dry-run')) {
                $summary = $importer->dryRun($path);
                $this->info('Dry run — no database changes.');
                foreach ($summary['files'] as $file => $count) {
                    $this->line("  {$file}: {$count} data rows");
                }
                $this->line('  Skipped files: '.implode(', ', DatabaseCsvDumpImportService::skipFilenames()));

                return self::SUCCESS;
            }

            if (! $this->option('fresh') && ! $this->option('truncate')) {
                $this->error('Refusing to import without --fresh or --truncate (avoids duplicate keys on non-empty tables).');

                return self::FAILURE;
            }

            if ($this->option('fresh')) {
                $this->call('migrate:fresh', ['--force' => true]);
            }

            $truncate = $this->option('truncate') || $this->option('fresh');
            $summary = $importer->import($path, $truncate);

            $this->info('Import finished.');
            foreach ($summary['inserted'] as $file => $count) {
                $this->line("  {$file}: {$count} rows inserted");
            }
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
}
