<?php

namespace App\Console\Commands;

use App\Console\Services\DatabaseIdSequenceResyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ResyncDatabaseIdSequencesCommand extends Command
{
    protected $signature = 'db:resync-sequences
                            {--schema= : PostgreSQL schema to scan (default: all non-system schemas)}
                            {--table=* : Only resync specific tables (repeatable)}';

    protected $description = 'Resync auto-increment ID sequences after a database restore or CSV import with preserved primary keys';

    public function handle(DatabaseIdSequenceResyncService $resyncService): int
    {
        /** @var list<string> $tables */
        $tables = array_values(array_filter(
            array_map(strval(...), (array) $this->option('table')),
            fn (string $table): bool => $table !== '',
        ));

        $schemaOption = $this->option('schema');
        $schema = is_string($schemaOption) && $schemaOption !== '' ? $schemaOption : null;

        if ($this->output->isVerbose()) {
            $driver = Schema::getConnection()->getDriverName();
            $this->line("Database driver: {$driver}");

            if ($driver === 'pgsql') {
                foreach ($resyncService->postgresSchemasToScan($schema) as $schemaName) {
                    $this->line("Scanning schema: {$schemaName}");
                }
            }
        }

        $results = $tables === []
            ? $resyncService->resyncAll($schema)
            : $resyncService->resyncTables($tables);

        if ($this->output->isVerbose() && $results === []) {
            $this->line('No serial/identity columns with existing rows were updated.');
        }

        if ($results === []) {
            $this->warn('No sequences were updated. Tables may be empty, use a non-default schema, or lack serial/identity ID columns.');
            $this->line('Try: php artisan db:resync-sequences -v');
            $this->line('Or:  php artisan db:resync-sequences --table=leagues --table=sets');

            return self::SUCCESS;
        }

        $this->info('ID sequences updated:');
        $this->table(
            ['Table', 'Column', 'Max ID', 'Next ID'],
            array_map(
                fn (array $result): array => [
                    $result['table'],
                    $result['column'],
                    (string) $result['max_id'],
                    (string) $result['next_id'],
                ],
                $results,
            ),
        );

        return self::SUCCESS;
    }
}
