<?php

namespace App\Console\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;

class DatabaseCsvDumpImportService
{
    /**
     * Import order: parents before foreign-key children. Filenames match {@see database/data}.
     *
     * @var list<array{0: string, 1: string}>
     */
    private const IMPORT_STEPS = [
        ['users', 'users.csv'],
        ['version_groups', 'version_groups.csv'],
        ['pokedex', 'pokedex.csv'],
        ['pokemon_game_data', 'pokemon_game_data.csv'],
        ['version_group_held_items', 'version_group_held_items.csv'],
        ['leagues', 'leagues.csv'],
        ['match_configs', 'match_configs.csv'],
        ['pools', 'pools.csv'],
        ['teams', 'teams.csv'],
        ['league_pokemon_templates', 'league_pokemon_templates.csv'],
        ['league_pokemon_template_rows', 'league_pokemon_template_rows.csv'],
        ['draft_config', 'draft_config.csv'],
        ['drafts', 'drafts.csv'],
        ['league_pokemon', 'league_pokemon.csv'],
        ['draft_order', 'draft_order.csv'],
        ['draft_picks', 'draft_picks.csv'],
        ['draft_bans', 'draft_bans.csv'],
        ['draft_ban_order', 'draft_ban_order.csv'],
        ['sets', 'sets.csv'],
        ['match_prep_notes', 'match_prep_notes.csv'],
        ['playoffs', 'playoffs.csv'],
        ['playoff_matches', 'playoff_matches.csv'],
        ['trades', 'trades.csv'],
        ['trade_pokemon', 'trade_pokemon.csv'],
        ['set_team_pokepastes', 'set_team_pokepastes.csv'],
        ['set_team_pokepaste_slots', 'set_team_pokepaste_slots.csv'],
        ['pokemon_usage_stats_meta', 'pokemon_usage_stats_meta.csv'],
        ['pokemon_usage_stats', 'pokemon_usage_stats.csv'],
    ];

    /** @var list<string> */
    private const SKIP_FILENAMES = [
        'migrations.csv',
    ];

    /**
     * @return array{files: array<string, int>}
     */
    public function dryRun(string $directory): array
    {
        $directory = $this->resolveDirectory($directory);
        $counts = [];
        foreach (self::IMPORT_STEPS as [$table, $file]) {
            $path = $directory.DIRECTORY_SEPARATOR.$file;
            if (! is_readable($path)) {
                throw new InvalidArgumentException("Missing or unreadable CSV: {$path}");
            }
            $counts[$file] = count($this->readCsv($path));
        }

        return ['files' => $counts];
    }

    /**
     * @return array{inserted: array<string, int>}
     */
    public function import(string $directory, bool $truncateBeforeImport): array
    {
        $directory = $this->resolveDirectory($directory);

        $inserted = [];

        DB::transaction(function () use ($directory, $truncateBeforeImport, &$inserted): void {
            if ($truncateBeforeImport) {
                $this->truncateApplicationDataTables();
            }

            foreach (self::IMPORT_STEPS as [$table, $file]) {
                if (! Schema::hasTable($table)) {
                    throw new RuntimeException("Table missing (run migrations): {$table}");
                }

                $path = $directory.DIRECTORY_SEPARATOR.$file;
                if (! is_readable($path)) {
                    throw new InvalidArgumentException("Missing or unreadable CSV: {$path}");
                }

                $rows = $this->readCsv($path);
                if ($rows === []) {
                    $inserted[$file] = 0;

                    continue;
                }

                $columnMeta = $this->columnMetaByName($table);
                $mapped = [];
                foreach ($rows as $i => $row) {
                    try {
                        $mapped[] = $this->mapRowToDatabase($table, $columnMeta, $row);
                    } catch (InvalidArgumentException $e) {
                        throw new InvalidArgumentException("{$file} row ".($i + 2).': '.$e->getMessage(), 0, $e);
                    }
                }

                $this->insertChunks($table, $mapped);
                $inserted[$file] = count($mapped);
            }
        });

        $this->resyncIdSequences();

        return ['inserted' => $inserted];
    }

    public static function defaultDataDirectory(): string
    {
        return database_path('data');
    }

    /**
     * @return list<string>
     */
    public static function skipFilenames(): array
    {
        return self::SKIP_FILENAMES;
    }

    private function resolveDirectory(string $directory): string
    {
        $resolved = realpath($directory);
        if ($resolved === false || ! is_dir($resolved)) {
            throw new InvalidArgumentException("Directory not found or unreadable: {$directory}");
        }

        return $resolved;
    }

    /**
     * @return list<array<string, string|null>>
     */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException("Could not open CSV: {$path}");
        }

        try {
            $header = fgetcsv($handle);
            if ($header === false || $header === [null] || $header === []) {
                throw new InvalidArgumentException("CSV has no header row: {$path}");
            }

            $header = array_map(fn (string $h): string => trim($h), $header);
            $rows = [];

            while (($data = fgetcsv($handle)) !== false) {
                if ($this->isCsvRowEmpty($data)) {
                    continue;
                }
                $rows[] = $this->combineRow($header, $data);
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  list<string|null>  $data
     */
    private function isCsvRowEmpty(array $data): bool
    {
        foreach ($data as $cell) {
            if ($cell !== null && trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $header
     * @param  list<string|null>  $data
     * @return array<string, string|null>
     */
    private function combineRow(array $header, array $data): array
    {
        $data = array_pad($data, count($header), null);
        $data = array_slice($data, 0, count($header));

        /** @var array<string, string|null> */
        return array_combine($header, $data);
    }

    /**
     * @return array<string, array{name: string, type_name: string, nullable: bool}>
     */
    private function columnMetaByName(string $table): array
    {
        $byName = [];
        foreach (Schema::getColumns($table) as $column) {
            $byName[$column['name']] = $column;
        }

        return $byName;
    }

    /**
     * @param  array<string, array{name: string, type_name: string, nullable: bool}>  $columnMeta
     * @param  array<string, string|null>  $row
     * @return array<string, mixed>
     */
    private function mapRowToDatabase(string $table, array $columnMeta, array $row): array
    {
        $out = [];

        foreach ($row as $key => $raw) {
            if (! isset($columnMeta[$key])) {
                continue;
            }

            $meta = $columnMeta[$key];
            $out[$key] = $this->castCell($table, $meta, $raw);
        }

        return $out;
    }

    /**
     * @param  array{name: string, type_name: string, nullable: bool}  $column
     */
    private function castCell(string $table, array $column, ?string $raw): mixed
    {
        $nullable = $column['nullable'];
        $name = $column['name'];
        $type = strtolower($column['type_name']);

        if ($raw === null || trim($raw) === '') {
            if ($nullable) {
                return null;
            }

            throw new InvalidArgumentException("Column \"{$name}\" on \"{$table}\" is not nullable but CSV value is empty.");
        }

        $trim = trim($raw);

        if ($type === 'bool' || str_contains($type, 'bool')) {
            $v = strtolower($trim);

            return in_array($v, ['1', 'true', 't', 'yes'], true);
        }

        if ($type === 'tinyint' || str_starts_with($type, 'tinyint')) {
            if (preg_match('/^(true|false|yes|no|t|f)$/i', $trim) === 1) {
                return in_array(strtolower($trim), ['true', 'yes', 't'], true);
            }
        }

        if (
            str_contains($type, 'int')
            || $type === 'integer'
            || $type === 'smallint'
            || $type === 'bigint'
        ) {
            if (! is_numeric($trim)) {
                throw new InvalidArgumentException("Column \"{$name}\" expects a number, got \"{$trim}\".");
            }

            return (int) $trim;
        }

        if (
            str_contains($type, 'double')
            || str_contains($type, 'float')
            || str_contains($type, 'decimal')
            || str_contains($type, 'numeric')
            || $type === 'real'
        ) {
            if (! is_numeric($trim)) {
                throw new InvalidArgumentException("Column \"{$name}\" expects a numeric value, got \"{$trim}\".");
            }

            return (float) $trim;
        }

        if (str_contains($type, 'json')) {
            $decoded = json_decode($trim, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new InvalidArgumentException("Column \"{$name}\" has invalid JSON: ".json_last_error_msg());
            }

            $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
            if (is_array($decoded)) {
                return json_encode($decoded, $flags) ?: '{}';
            }

            return json_encode($decoded, $flags) ?: 'null';
        }

        return $trim;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function insertChunks(string $table, array $rows, int $chunkSize = 200): void
    {
        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }

    private function truncateApplicationDataTables(): void
    {
        $tables = array_reverse(array_column(self::IMPORT_STEPS, 0));
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            $qualified = implode(', ', array_map(fn (string $t): string => '"'.$t.'"', $tables));
            DB::statement('TRUNCATE TABLE '.$qualified.' RESTART IDENTITY CASCADE');

            return;
        }

        Schema::disableForeignKeyConstraints();
        try {
            foreach ($tables as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->delete();
                }
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    private function resyncIdSequences(): void
    {
        $tables = array_column(self::IMPORT_STEPS, 0);
        $driver = Schema::getConnection()->getDriverName();

        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $max = DB::table($table)->max('id');
            if ($max === null) {
                continue;
            }
            $max = (int) $max;

            if ($driver === 'sqlite') {
                if (! Schema::hasTable('sqlite_sequence')) {
                    continue;
                }
                $exists = DB::table('sqlite_sequence')->where('name', $table)->exists();
                if ($exists) {
                    DB::table('sqlite_sequence')->where('name', $table)->update(['seq' => $max]);
                } else {
                    DB::table('sqlite_sequence')->insert(['name' => $table, 'seq' => $max]);
                }
            } elseif ($driver === 'mysql') {
                DB::statement('ALTER TABLE `'.$table.'` AUTO_INCREMENT = '.($max + 1));
            } elseif ($driver === 'pgsql') {
                $seq = DB::selectOne('SELECT pg_get_serial_sequence(?, ?) AS s', [$table, 'id']);
                if ($seq !== null && $seq->s !== null) {
                    DB::statement('SELECT setval(?, ?, true)', [$seq->s, $max]);
                }
            }
        }
    }
}
