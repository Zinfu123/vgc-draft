<?php

namespace App\Console\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseIdSequenceResyncService
{
    /**
     * @return list<array{table: string, column: string, max_id: int, next_id: int}>
     */
    public function resyncAll(string $schema = 'public'): array
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            return $this->resyncPostgresSchema($schema);
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
     * @return array{table: string, column: string, max_id: int, next_id: int}|null
     */
    public function resyncTable(string $table, string $column = 'id'): ?array
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return null;
        }

        $max = DB::table($table)->max($column);

        if ($max === null) {
            return null;
        }

        $max = (int) $max;
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $this->resyncSqliteSequence($table, $max);
        } elseif ($driver === 'mysql') {
            DB::statement('ALTER TABLE `'.$table.'` AUTO_INCREMENT = '.($max + 1));
        } elseif ($driver === 'pgsql') {
            $sequence = $this->postgresSerialSequence($table, $column);

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
    private function resyncPostgresSchema(string $schema): array
    {
        $rows = DB::select(
            <<<'SQL'
            SELECT
                format('%I.%I', n.nspname, c.relname) AS qualified_table,
                c.relname AS table_name,
                a.attname AS column_name
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            JOIN pg_attribute a ON a.attrelid = c.oid
            WHERE c.relkind = 'r'
              AND n.nspname = ?
              AND a.attnum > 0
              AND NOT a.attisdropped
              AND pg_get_serial_sequence(format('%I.%I', n.nspname, c.relname), a.attname) IS NOT NULL
            ORDER BY c.relname, a.attname
            SQL,
            [$schema],
        );

        $results = [];

        foreach ($rows as $row) {
            $max = DB::table($row->table_name)->max($row->column_name);

            if ($max === null) {
                continue;
            }

            $max = (int) $max;
            $sequence = $this->postgresSerialSequence($row->qualified_table, $row->column_name)
                ?? $this->postgresSerialSequence($row->table_name, $row->column_name);

            if ($sequence === null) {
                continue;
            }

            DB::statement('SELECT setval(?, ?, true)', [$sequence, $max]);

            $results[] = [
                'table' => $row->qualified_table,
                'column' => $row->column_name,
                'max_id' => $max,
                'next_id' => $max + 1,
            ];
        }

        return $results;
    }

    /**
     * @return list<string>
     */
    private function discoverTablesWithIdColumn(): array
    {
        $tables = [];

        foreach (Schema::getTableListing() as $table) {
            if (Schema::hasColumn($table, 'id')) {
                $tables[] = $table;
            }
        }

        sort($tables);

        return $tables;
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
        $sequence = DB::selectOne('SELECT pg_get_serial_sequence(?, ?) AS s', [$table, $column]);

        return $sequence?->s;
    }
}
