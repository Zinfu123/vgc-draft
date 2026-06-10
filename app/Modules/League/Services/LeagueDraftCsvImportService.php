<?php

namespace App\Modules\League\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;

class LeagueDraftCsvImportService
{
    private const FILES = [
        'league_pokemon.csv',
        'draft_config.csv',
        'draft_order.csv',
        'draft_picks.csv',
        'drafts.csv',
        'sets.csv',
    ];

    /**
     * @return array{files: array<string, int>, league_ids: list<int>}
     */
    public function dryRun(string $directory): array
    {
        $directory = $this->resolveDirectory($directory);
        $parsed = $this->loadAll($directory);
        $this->validateCrossFileReferences($parsed);
        $this->validateDatabasePrerequisites($parsed);

        return [
            'files' => array_map(fn (array $rows): int => count($rows), $parsed),
            'league_ids' => $this->distinctLeagueIds($parsed),
        ];
    }

    /**
     * @return array{inserted: array<string, int>, league_ids: list<int>}
     */
    public function import(string $directory, bool $replace, bool $validateDatabase = true): array
    {
        $directory = $this->resolveDirectory($directory);
        $parsed = $this->loadAll($directory);
        $this->validateCrossFileReferences($parsed);
        if ($validateDatabase) {
            $this->validateDatabasePrerequisites($parsed);
        }

        $leagueIds = $this->distinctLeagueIds($parsed);

        DB::transaction(function () use ($parsed, $replace, $leagueIds): void {
            if ($replace) {
                $this->deleteExistingForLeagues($leagueIds);
            }

            $this->insertChunks('league_pokemon', array_map(fn (array $r): array => $this->mapLeaguePokemonRow($r), $parsed['league_pokemon.csv']));
            $this->insertChunks('draft_config', array_map(fn (array $r): array => $this->mapDraftConfigRow($r), $parsed['draft_config.csv']));
            $this->insertChunks('drafts', array_map(fn (array $r): array => $this->mapDraftsRow($r), $parsed['drafts.csv']));
            $this->insertChunks('draft_order', array_map(fn (array $r): array => $this->mapDraftOrderRow($r), $parsed['draft_order.csv']));
            $this->insertChunks('draft_picks', array_map(fn (array $r): array => $this->mapDraftPicksRow($r), $parsed['draft_picks.csv']));
            $this->insertChunks('sets', array_map(fn (array $r): array => $this->mapSetsRow($r), $parsed['sets.csv']));
        });

        app(\App\Console\Services\DatabaseIdSequenceResyncService::class)->resyncTables([
            'league_pokemon',
            'draft_config',
            'drafts',
            'draft_order',
            'draft_picks',
            'sets',
        ]);

        return [
            'inserted' => array_map(fn (array $rows): int => count($rows), $parsed),
            'league_ids' => $leagueIds,
        ];
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
     * @return array<string, list<array<string, string|null>>>
     */
    private function loadAll(string $directory): array
    {
        $parsed = [];
        foreach (self::FILES as $file) {
            $path = $directory.DIRECTORY_SEPARATOR.$file;
            if (! is_readable($path)) {
                throw new InvalidArgumentException("Missing or unreadable CSV: {$path}");
            }
            $parsed[$file] = $this->readCsv($path);
            if ($parsed[$file] === []) {
                throw new InvalidArgumentException("CSV is empty (no data rows): {$file}");
            }
        }

        return $parsed;
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
     * @param  array<string, list<array<string, string|null>>>  $parsed
     */
    private function validateCrossFileReferences(array $parsed): void
    {
        $leaguePokemonIds = $this->columnIntSet($parsed['league_pokemon.csv'], 'id');
        $draftIds = $this->columnIntSet($parsed['drafts.csv'], 'id');

        foreach ($parsed['draft_picks.csv'] as $i => $row) {
            $draftId = $this->intOrNull($row['draft_id'] ?? null);
            $lpId = $this->intOrNull($row['league_pokemon_id'] ?? null);
            if ($draftId === null || ! isset($draftIds[$draftId])) {
                throw new InvalidArgumentException('draft_picks.csv row '.($i + 2)." references unknown draft_id: {$draftId}");
            }
            if ($lpId === null || ! isset($leaguePokemonIds[$lpId])) {
                throw new InvalidArgumentException('draft_picks.csv row '.($i + 2)." references unknown league_pokemon_id: {$lpId}");
            }
        }
    }

    /**
     * @param  array<string, list<array<string, string|null>>>  $parsed
     */
    private function validateDatabasePrerequisites(array $parsed): void
    {
        $leagueIds = $this->distinctLeagueIds($parsed);
        foreach ($leagueIds as $id) {
            if (! DB::table('leagues')->where('id', $id)->exists()) {
                throw new InvalidArgumentException("League id {$id} does not exist; create the league before importing.");
            }
        }

        $pokedexIds = $this->columnIntSet($parsed['league_pokemon.csv'], 'pokedex_id');
        foreach (array_keys($pokedexIds) as $pid) {
            if (! DB::table('pokedex')->where('id', $pid)->exists()) {
                throw new InvalidArgumentException("Pokedex id {$pid} does not exist (referenced in league_pokemon.csv).");
            }
        }

        $userIds = $this->columnIntSet($parsed['draft_order.csv'], 'user_id');
        foreach (array_keys($userIds) as $uid) {
            if (! DB::table('users')->where('id', $uid)->exists()) {
                throw new InvalidArgumentException("User id {$uid} does not exist (referenced in draft_order.csv).");
            }
        }

        $teamIds = $this->columnIntSet($parsed['draft_order.csv'], 'team_id');
        $teamIds = array_replace($teamIds, $this->columnIntSet($parsed['draft_picks.csv'], 'team_id'));
        $teamIds = array_replace($teamIds, $this->columnIntSet($parsed['sets.csv'], 'team1_id'));
        $teamIds = array_replace($teamIds, $this->columnIntSet($parsed['sets.csv'], 'team2_id'));

        foreach (array_keys($teamIds) as $tid) {
            if (! DB::table('teams')->where('id', $tid)->exists()) {
                throw new InvalidArgumentException("Team id {$tid} does not exist (referenced in CSV data).");
            }
        }

        $poolIds = $this->columnIntSet($parsed['sets.csv'], 'pool_id');
        foreach (array_keys($poolIds) as $poolId) {
            if (! DB::table('pools')->where('id', $poolId)->exists()) {
                throw new InvalidArgumentException("Pool id {$poolId} does not exist (referenced in sets.csv).");
            }
        }

        foreach ($parsed['league_pokemon.csv'] as $i => $row) {
            $draftedBy = $this->intOrNull($row['drafted_by'] ?? null);
            if ($draftedBy !== null && ! DB::table('teams')->where('id', $draftedBy)->exists()) {
                throw new InvalidArgumentException('league_pokemon.csv row '.($i + 2)." references unknown drafted_by team id: {$draftedBy}");
            }
        }
    }

    /**
     * @param  list<int>  $leagueIds
     */
    private function deleteExistingForLeagues(array $leagueIds): void
    {
        Schema::disableForeignKeyConstraints();
        try {
            foreach ($leagueIds as $leagueId) {
                DB::table('draft_picks')->where('league_id', $leagueId)->delete();
                DB::table('draft_order')->where('league_id', $leagueId)->delete();
                DB::table('sets')->where('league_id', $leagueId)->delete();
                DB::table('drafts')->where('league_id', $leagueId)->delete();
                DB::table('league_pokemon')->where('league_id', $leagueId)->delete();
                DB::table('draft_config')->where('league_id', $leagueId)->delete();
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function insertChunks(string $table, array $rows, int $chunkSize = 250): void
    {
        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }

    /**
     * @param  array<string, string|null>  $r
     * @return array<string, mixed>
     */
    private function mapLeaguePokemonRow(array $r): array
    {
        return [
            'id' => (int) $r['id'],
            'league_id' => (int) $r['league_id'],
            'pokedex_id' => (int) $r['pokedex_id'],
            'name' => (string) $r['name'],
            'cost' => (int) $r['cost'],
            'banned' => $this->csvBool($r['banned'] ?? '0'),
            'is_drafted' => $this->csvBool($r['is_drafted'] ?? '0'),
            'drafted_by' => $this->intOrNull($r['drafted_by'] ?? null),
            'kos' => (int) ($r['kos'] ?? 0),
            'created_at' => $this->timestampOrNow($r['created_at'] ?? null),
            'updated_at' => $this->timestampOrNow($r['updated_at'] ?? null),
        ];
    }

    /**
     * @param  array<string, string|null>  $r
     * @return array<string, mixed>
     */
    private function mapDraftConfigRow(array $r): array
    {
        return [
            'id' => (int) $r['id'],
            'league_id' => (int) $r['league_id'],
            'draft_date' => $this->dateOrNull($r['draft_date'] ?? null),
            'draft_points' => (int) ($r['draft_points'] ?? 0),
            'minimum_drafts' => (int) ($r['minimum_drafts'] ?? 0),
            'ban_enabled' => $this->csvBool($r['ban_enabled'] ?? '0'),
            'bans_per_user' => $this->intOrNull($r['bans_per_user'] ?? null),
            'minimum_cost_to_ban' => $this->intOrNull($r['minimum_cost_to_ban'] ?? null),
            'created_at' => $this->timestampOrNow($r['created_at'] ?? null),
            'updated_at' => $this->timestampOrNow($r['updated_at'] ?? null),
        ];
    }

    /**
     * @param  array<string, string|null>  $r
     * @return array<string, mixed>
     */
    private function mapDraftsRow(array $r): array
    {
        return [
            'id' => (int) $r['id'],
            'league_id' => (int) $r['league_id'],
            'round_number' => (int) $r['round_number'],
            'status' => (int) ($r['status'] ?? 0),
            'pick_number' => (int) ($r['pick_number'] ?? 0),
            'created_at' => $this->timestampOrNow($r['created_at'] ?? null),
            'updated_at' => $this->timestampOrNow($r['updated_at'] ?? null),
        ];
    }

    /**
     * @param  array<string, string|null>  $r
     * @return array<string, mixed>
     */
    private function mapDraftOrderRow(array $r): array
    {
        return [
            'id' => (int) $r['id'],
            'league_id' => (int) $r['league_id'],
            'user_id' => (int) $r['user_id'],
            'pick_number' => (int) ($r['pick_number'] ?? 1),
            'status' => (int) ($r['status'] ?? 0),
            'is_last_pick' => (int) ($r['is_last_pick'] ?? 0),
            'team_name' => (string) $r['team_name'],
            'team_id' => $this->intOrNull($r['team_id'] ?? null),
            'round_number' => (int) $r['round_number'],
            'created_at' => $this->timestampOrNow($r['created_at'] ?? null),
            'updated_at' => $this->timestampOrNow($r['updated_at'] ?? null),
        ];
    }

    /**
     * @param  array<string, string|null>  $r
     * @return array<string, mixed>
     */
    private function mapDraftPicksRow(array $r): array
    {
        return [
            'id' => (int) $r['id'],
            'draft_id' => (int) $r['draft_id'],
            'team_id' => (int) $r['team_id'],
            'league_pokemon_id' => (int) $r['league_pokemon_id'],
            'round_number' => (int) $r['round_number'],
            'pick_number' => (int) $r['pick_number'],
            'league_id' => (int) $r['league_id'],
            'created_at' => $this->timestampOrNow($r['created_at'] ?? null),
            'updated_at' => $this->timestampOrNow($r['updated_at'] ?? null),
        ];
    }

    /**
     * @param  array<string, string|null>  $r
     * @return array<string, mixed>
     */
    private function mapSetsRow(array $r): array
    {
        return [
            'id' => (int) $r['id'],
            'league_id' => (int) $r['league_id'],
            'pool_id' => (int) $r['pool_id'],
            'round' => (int) ($r['round'] ?? 1),
            'team1_id' => (int) $r['team1_id'],
            'team2_id' => (int) $r['team2_id'],
            'team1_score' => $this->intOrNull($r['team1_score'] ?? null),
            'team2_score' => $this->intOrNull($r['team2_score'] ?? null),
            'team1_pokepaste' => $this->stringOrNull($r['team1_pokepaste'] ?? null),
            'team2_pokepaste' => $this->stringOrNull($r['team2_pokepaste'] ?? null),
            'replay1' => $this->stringOrNull($r['replay1'] ?? null),
            'replay2' => $this->stringOrNull($r['replay2'] ?? null),
            'replay3' => $this->stringOrNull($r['replay3'] ?? null),
            'winner_id' => $this->intOrNull($r['winner_id'] ?? null),
            'status' => (int) ($r['status'] ?? 1),
            'team1_points' => $this->intOrNull($r['team1_points'] ?? null),
            'team2_points' => $this->intOrNull($r['team2_points'] ?? null),
            'created_at' => $this->timestampOrNow($r['created_at'] ?? null),
            'updated_at' => $this->timestampOrNow($r['updated_at'] ?? null),
        ];
    }

    private function csvBool(?string $value): int
    {
        if ($value === null || trim($value) === '') {
            return 0;
        }

        $v = strtolower(trim($value));

        return in_array($v, ['1', 'true', 'yes'], true) ? 1 : 0;
    }

    private function intOrNull(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return (int) $value;
    }

    private function stringOrNull(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return $value;
    }

    private function dateOrNull(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return substr(trim($value), 0, 10);
    }

    private function timestampOrNow(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return now()->format('Y-m-d H:i:s');
        }

        return trim($value);
    }

    /**
     * @param  list<array<string, string|null>>  $rows
     * @return array<int, true>
     */
    private function columnIntSet(array $rows, string $column): array
    {
        $set = [];
        foreach ($rows as $row) {
            $v = $this->intOrNull($row[$column] ?? null);
            if ($v !== null) {
                $set[$v] = true;
            }
        }

        return $set;
    }

    /**
     * @param  array<string, list<array<string, string|null>>>  $parsed
     * @return list<int>
     */
    private function distinctLeagueIds(array $parsed): array
    {
        $ids = [];
        foreach (['league_pokemon.csv', 'draft_config.csv', 'draft_order.csv', 'draft_picks.csv', 'drafts.csv', 'sets.csv'] as $file) {
            foreach ($parsed[$file] as $row) {
                $id = $this->intOrNull($row['league_id'] ?? null);
                if ($id !== null) {
                    $ids[$id] = true;
                }
            }
        }

        $list = array_map('intval', array_keys($ids));
        sort($list);

        return $list;
    }
}
