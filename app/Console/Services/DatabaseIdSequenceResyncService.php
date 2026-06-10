<?php

namespace App\Console\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseIdSequenceResyncService
{
    /**
     * @return list<array{table: string, column: string, max_id: int, next_id: int}>
     */
    public function resyncAll(?string $schema = null): array
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            return $this->resyncPostgres($schema);
        }

        $results = [];

        foreach ($this->discoverTablesWithIdColumn() as $table) {
            $result = $this->resyncTable($table);

            if ($result !== null) {
                $results[] = $result;
            }
        }

        return $results;
    }

    /**
     * @param  list<string>  $tables
     * @return list<array{table: string, column: string, max_id: int, next_id: int}>
     */
    public function resyncTables(array $tables, string $column = 'id'): array
    {
        $results = [];

        foreach ($tables as $table) {
            $result = $this->resyncTable($table, $column);

            if ($result !== null) {
                $results[] = $result;
            }
        }

        return $results;
    }

    /**
     * @return list<string>
     */
    public function postgresSchemasToScan(?string $schema): array
    {
        if ($schema !== null && $schema !== '') {
            return [$schema];
        }

        $schemas = DB::select(
            <<<'SQL'
            SELECT DISTINCT table_schema
            FROM information_schema.tables
            WHERE table_type = 'BASE TABLE'
              AND table_schema NOT IN ('pg_catalog', 'information_schema')
            ORDER BY table_schema
            SQL,
        );

        return array_map(fn (object $row): string => (string) $row->table_schema, $schemas);
    }

    /**
     * @return array{table: string, column: string, max_id: int, next_id: int}|null
     */
    public function resyncTable(string $table, string $column = 'id'): ?array
    {
        $tableName = $this->unqualifiedTableName($table);

        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, $column)) {
            return null;
        }

        $max = DB::table($tableName)->max($column);

        if ($max === null) {
            return null;
        }

        $max = (int) $max;
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $this->resyncSqliteSequence($tableName, $max);
        } elseif ($driver === 'mysql') {
            DB::statement('ALTER TABLE `'.$tableName.'` AUTO_INCREMENT = '.($max + 1));
        } elseif ($driver === 'pgsql') {
            $sequence = $this->postgresSerialSequence($table, $column)
                ?? $this->postgresSerialSequence($tableName, $column);

            if ($sequence === null) {
                return null;
            }

            DB::statement('SELECT setval(?, ?, true)', [$sequence, $max]);
        }

        return [
            'table' => $table,
            'column' => $column,
            'max_id' => $max,
            'next_id' => $max + 1,
        ];
    }

    /**
     * @return list<array{table: string, column: string, max_id: int, next_id: int}>
     */
    private function resyncPostgres(?string $schema): array
    {
        $results = [];

        foreach ($this->postgresSchemasToScan($schema) as $schemaName) {
            foreach ($this->discoverPostgresAutoIncrementColumns($schemaName) as $column) {
                $result = $this->resyncPostgresColumn($schemaName, $column['table_name'], $column['column_name']);

                if ($result !== null) {
                    $results[] = $result;
                }
            }
        }

        return $results;
    }

    /**
     * @return list<array{table_name: string, column_name: string}>
     */
    private function discoverPostgresAutoIncrementColumns(string $schema): array
    {
        $fromInformationSchema = DB::select(
            <<<'SQL'
            SELECT table_name, column_name
            FROM information_schema.columns
            WHERE table_schema = ?
              AND (
                  is_identity = 'YES'
                  OR column_default LIKE 'nextval(%'
              )
            ORDER BY table_name, column_name
            SQL,
            [$schema],
        );

        $columns = [];

        foreach ($fromInformationSchema as $row) {
            $columns[$row->table_name.'.'.$row->column_name] = [
                'table_name' => (string) $row->table_name,
                'column_name' => (string) $row->column_name,
            ];
        }

        if ($columns !== []) {
            return array_values($columns);
        }

        foreach ($this->discoverPostgresColumnsFromSequences($schema) as $column) {
            $columns[$column['table_name'].'.'.$column['column_name']] = $column;
        }

        return array_values($columns);
    }

    /**
     * @return list<array{table_name: string, column_name: string}>
     */
    private function discoverPostgresColumnsFromSequences(string $schema): array
    {
        $sequences = DB::select(
            "SELECT sequencename FROM pg_sequences WHERE schemaname = ? AND sequencename LIKE '%' || '_id_seq'",
            [$schema],
        );

        $columns = [];

        foreach ($sequences as $sequence) {
            $sequenceName = (string) $sequence->sequencename;

            if (! str_ends_with($sequenceName, '_id_seq')) {
                continue;
            }

            $tableName = substr($sequenceName, 0, -strlen('_id_seq'));

            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'id')) {
                continue;
            }

            $columns[$tableName.'.id'] = [
                'table_name' => $tableName,
                'column_name' => 'id',
            ];
        }

        return array_values($columns);
    }

    /**
     * @return array{table: string, column: string, max_id: int, next_id: int}|null
     */
    private function resyncPostgresColumn(string $schema, string $tableName, string $columnName): ?array
    {
        $qualifiedTable = $schema.'.'.$tableName;
        $max = DB::table($tableName)->max($columnName);

        if ($max === null) {
            return null;
        }

        $max = (int) $max;
        $sequence = $this->postgresSerialSequence($qualifiedTable, $columnName)
            ?? $this->postgresSerialSequence($tableName, $columnName)
            ?? $this->postgresSequenceFromPgSequences($schema, $tableName, $columnName);

        if ($sequence === null) {
            return null;
        }

        DB::statement('SELECT setval(?, ?, true)', [$sequence, $max]);

        return [
            'table' => $qualifiedTable,
            'column' => $columnName,
            'max_id' => $max,
            'next_id' => $max + 1,
        ];
    }

    /**
     * @return list<string>
     */
    private function discoverTablesWithIdColumn(): array
    {
        $tables = [];

        foreach (Schema::getTableListing() as $table) {
            $tableName = $this->unqualifiedTableName($table);

            if (Schema::hasColumn($tableName, 'id')) {
                $tables[] = $tableName;
            }
        }

        sort($tables);

        return array_values(array_unique($tables));
    }

    private function resyncSqliteSequence(string $table, int $max): void
    {
        if (! Schema::hasTable('sqlite_sequence')) {
            return;
        }

        $exists = DB::table('sqlite_sequence')->where('name', $table)->exists();

        if ($exists) {
            DB::table('sqlite_sequence')->where('name', $table)->update(['seq' => $max]);
        } else {
            DB::table('sqlite_sequence')->insert(['name' => $table, 'seq' => $max]);
        }
    }

    private function postgresSerialSequence(string $table, string $column): ?string
    {
        foreach ($this->postgresTableNameCandidates($table) as $candidate) {
            $sequence = DB::selectOne('SELECT pg_get_serial_sequence(?, ?) AS s', [$candidate, $column]);

            if ($sequence?->s !== null) {
                return $sequence->s;
            }
        }

        return null;
    }

    private function postgresSequenceFromPgSequences(string $schema, string $tableName, string $columnName): ?string
    {
        if ($columnName !== 'id') {
            return null;
        }

        $sequenceName = $tableName.'_id_seq';
        $exists = DB::selectOne(
            'SELECT sequencename FROM pg_sequences WHERE schemaname = ? AND sequencename = ?',
            [$schema, $sequenceName],
        );

        if ($exists === null) {
            return null;
        }

        return $schema.'.'.$sequenceName;
    }

    /**
     * @return list<string>
     */
    private function postgresTableNameCandidates(string $table): array
    {
        $candidates = [$table];
        $unqualified = $this->unqualifiedTableName($table);

        if ($unqualified !== $table) {
            $candidates[] = $unqualified;
        }

        if (! str_contains($table, '.')) {
            $candidates[] = 'public.'.$table;
        }

        return array_values(array_unique($candidates));
    }

    private function unqualifiedTableName(string $table): string
    {
        if (! str_contains($table, '.')) {
            return $table;
        }

        $segments = explode('.', $table);

        return (string) end($segments);
    }
}
