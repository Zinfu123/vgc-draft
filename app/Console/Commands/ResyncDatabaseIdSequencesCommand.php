<?php

namespace App\Console\Commands;

use App\Console\Services\DatabaseIdSequenceResyncService;
use Illuminate\Console\Command;

class ResyncDatabaseIdSequencesCommand extends Command
{
    protected $signature = 'db:resync-sequences
                            {--schema=public : PostgreSQL schema to scan when no --table options are passed}
                            {--table=* : Only resync specific tables (repeatable)}';

    protected $description = 'Resync auto-increment ID sequences after a database restore or CSV import with preserved primary keys';

    public function handle(DatabaseIdSequenceResyncService $resyncService): int
    {
        /** @var list<string> $tables */
        $tables = array_values(array_filter(
            array_map(strval(...), (array) $this->option('table')),
            fn (string $table): bool => $table !== '',
        ));

        $results = $tables === []
            ? $resyncService->resyncAll((string) $this->option('schema'))
            : $resyncService->resyncTables($tables);

        if ($results === []) {
            $this->warn('No sequences were updated. Tables may be empty or lack serial ID columns.');

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
