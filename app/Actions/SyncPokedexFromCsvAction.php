<?php

namespace App\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;

class SyncPokedexFromCsvAction
{
    private const REQUIRED_COLUMNS = [
        'id',
        'nationaldex_id',
        'name',
        'type1',
        'type2',
        'sprite_url',
        'created_at',
        'updated_at',
    ];

    /**
     * Upsert pokedex rows from a CSV dump. Preserves primary keys so foreign keys remain valid.
     * Rows present in the database but absent from the CSV are left unchanged.
     *
     * @return int Number of rows read from the CSV (including header-skipped empty lines)
     */
    public function handle(?string $path = null): int
    {
        $path ??= database_path('data/pokedex.csv');

        if (! is_readable($path)) {
            throw new InvalidArgumentException("Pokedex CSV not found or unreadable: {$path}");
        }

        $rows = $this->readCsvRows($path);

        if ($rows === []) {
            return 0;
        }

        DB::transaction(function () use ($rows): void {
            foreach (array_chunk($rows, 200) as $chunk) {
                DB::table('pokedex')->upsert(
                    $chunk,
                    ['id'],
                    ['nationaldex_id', 'name', 'type1', 'type2', 'sprite_url', 'created_at', 'updated_at'],
                );
            }
        });

        $this->resyncPokedexIdSequence();

        return count($rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readCsvRows(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Could not open CSV: {$path}");
        }

        try {
            $header = fgetcsv($handle, 0, ',', '"', '\\');
            if ($header === false || $header === [null] || $header === []) {
                throw new InvalidArgumentException("CSV has no header row: {$path}");
            }

            /** @var list<string> $header */
            $header = array_map(static fn (string $h): string => trim($h), $header);
            $this->assertHeader($header, $path);

            $out = [];

            while (($data = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
                if ($this->isRowEmpty($data)) {
                    continue;
                }

                $data = array_pad($data, count($header), null);
                $data = array_slice($data, 0, count($header));
                /** @var array<string, string|null> $assoc */
                $assoc = array_combine($header, $data);
                if ($assoc === false) {
                    throw new InvalidArgumentException("CSV row column count mismatch in: {$path}");
                }

                $out[] = $this->mapAssocRow($assoc);
            }

            return $out;
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  list<string>  $header
     */
    private function assertHeader(array $header, string $path): void
    {
        foreach (self::REQUIRED_COLUMNS as $column) {
            if (! in_array($column, $header, true)) {
                throw new InvalidArgumentException("CSV missing required column \"{$column}\" in: {$path}");
            }
        }
    }

    /**
     * @param  list<string|null>  $data
     */
    private function isRowEmpty(array $data): bool
    {
        foreach ($data as $cell) {
            if ($cell !== null && trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, string|null>  $row
     * @return array<string, mixed>
     */
    private function mapAssocRow(array $row): array
    {
        $type2Raw = $row['type2'] ?? null;
        $type2 = $this->nullableString($type2Raw);

        $spriteRaw = $row['sprite_url'] ?? null;
        $spriteUrl = $this->nullableString($spriteRaw);

        $nationaldexRaw = $row['nationaldex_id'] ?? '';
        $nationaldexTrim = trim((string) $nationaldexRaw);
        if ($nationaldexTrim === '' || ! is_numeric($nationaldexTrim)) {
            throw new InvalidArgumentException('nationaldex_id must be numeric.');
        }

        $idRaw = $row['id'] ?? '';
        $idTrim = trim((string) $idRaw);
        if ($idTrim === '' || ! ctype_digit($idTrim)) {
            throw new InvalidArgumentException('id must be a positive integer.');
        }

        $name = trim((string) ($row['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('name must not be empty.');
        }

        $type1 = trim((string) ($row['type1'] ?? ''));
        if ($type1 === '' || $type1 === '-') {
            throw new InvalidArgumentException('type1 must not be empty.');
        }

        $createdAt = trim((string) ($row['created_at'] ?? ''));
        $updatedAt = trim((string) ($row['updated_at'] ?? ''));
        if ($createdAt === '' || $updatedAt === '') {
            throw new InvalidArgumentException('created_at and updated_at are required.');
        }

        return [
            'id' => (int) $idTrim,
            'nationaldex_id' => (float) $nationaldexTrim,
            'name' => $name,
            'type1' => $type1,
            'type2' => $type2,
            'sprite_url' => $spriteUrl,
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ];
    }

    private function nullableString(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $trim = trim($raw);
        if ($trim === '' || $trim === '-') {
            return null;
        }

        return $trim;
    }

    private function resyncPokedexIdSequence(): void
    {
        $table = 'pokedex';
        if (! Schema::hasTable($table)) {
            return;
        }

        $max = DB::table($table)->max('id');
        if ($max === null) {
            return;
        }

        $max = (int) $max;
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            if (! Schema::hasTable('sqlite_sequence')) {
                return;
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
