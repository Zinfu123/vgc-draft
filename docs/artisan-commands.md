# Artisan commands (this project)

Custom commands live in `app/Console/Commands/` and auto-register in Laravel 12 (no manual registration in a `Kernel`).

| Command | Purpose |
| --- | --- |
| `db:resync-sequences` | Resync auto-increment ID sequences after a DB restore or CSV import with preserved primary keys |
| `pokemon:import-version-group` | Import or refresh `pokemon_generation_data` from PokeAPI for a version group |
| `pokemon:import-version-group-held-items` | Import holdable items into `version_group_held_items` from PokeAPI |
| `pokemon:validate-sv-import` | Sanity-check Scarlet/Violet import coverage against PokeAPI regional dexes |
| `league:import-draft-csv` | Bulk import draft/match CSV exports with preserved IDs |
| `league:pokemon-template-import` | Create or update a league Pokémon pool template from CSV |
| `league:pokemon-template-from-league` | Snapshot a league’s pool into a reusable template |
| `usage-stats:rebuild` | Recompute global Pokémon usage aggregates |

For built-in Laravel commands, run `php artisan list`. A small demo command `inspire` is defined in `routes/console.php`.

---

## `pokemon:import-version-group`

**Description:** Import or refresh `pokemon_generation_data` from PokeAPI for a version group (including per-species ability rows and PokéAPI move cache). Item legality and metadata for a generation live in `version_group_held_items` via `pokemon:import-version-group-held-items`.

**Signature:**

```text
pokemon:import-version-group
    {slug? : PokeAPI version group slug (e.g. scarlet-violet)}
    {--id= : Import only this pokedex row id}
    {--async : Dispatch a queued job per species instead of running synchronously}
    {--only-missing : Skip species that already have pokemon_generation_data for this version group (resume)}
    {--chunk= : Max species to process this run (use with --only-missing for batched resume)}
```

**Behavior:**

- If `slug` is omitted, uses `config('pokemon.default_version_group_slug')`.
- Fails if the slug is not found in `version_groups`.
- Iterates `pokedex` rows (ordered by `id`), optionally filtered by `--id`, `--only-missing`, and `--chunk`.
- **Sync (default):** Calls `PokeApiPokemonGameDataImporter` per row and sleeps ~150ms between calls to avoid hammering the API.
- **`--async`:** Dispatches `ImportSinglePokedexGameDataJob` per row; you must run a queue worker.
- With `--only-missing` and `--chunk`, if the chunk limit is hit and more rows remain, the command warns you to re-run with the same flags.

**Examples:**

```bash
php artisan pokemon:import-version-group scarlet-violet
php artisan pokemon:import-version-group --only-missing --chunk=100
php artisan pokemon:import-version-group --id=42
php artisan pokemon:import-version-group scarlet-violet --async
```

---

## `pokemon:import-version-group-held-items`

**Description:** Import PokeAPI holdable items (held items, berries, plates, type items, choice items, species-specific items, etc.) into `version_group_held_items`.

**Signature:**

```text
pokemon:import-version-group-held-items
    {slug? : Version group slug (e.g. scarlet-violet)}
```

**Behavior:**

- If `slug` is omitted, uses `config('pokemon.default_version_group_slug')`.
- Resolves the version group by slug; fails if unknown.
- Runs `PokeApiVersionGroupHeldItemImporter::importForVersionGroup()` and reports how many rows were written or updated.

**Example:**

```bash
php artisan pokemon:import-version-group-held-items scarlet-violet
```

---

## `pokemon:validate-sv-import`

**Description:** Compare PokeAPI Scarlet/Violet regional Pokédex species sets with your database’s `pokemon_generation_data` for a version group (default slug `scarlet-violet`). Read-only; does not modify data.

**Signature:**

```text
pokemon:validate-sv-import
    {--slug=scarlet-violet : Version group slug stored in your database (PokeAPI slug)}
```

**Behavior:**

- Loads union of species IDs from Paldea, Kitakami, and Blueberry dexes via `PokeApiSvDexSpeciesService`, plus Generation IX species list for reference.
- Compares to distinct `FLOOR(nationaldex_id)` among pokedex rows that have game data for the chosen version group.
- Prints a summary table and lists samples of IDs missing in the DB or present in the DB but not on those three regional dexes.
- Fails early if the version group slug is unknown or regional dex data could not be fetched (check `POKEAPI_URL` and connectivity).

**Example:**

```bash
php artisan pokemon:validate-sv-import
php artisan pokemon:validate-sv-import --slug=scarlet-violet
```

---

## `league:import-draft-csv`

**Description:** Import league draft and match CSV exports with preserved primary keys and resynced ID sequences for the active database driver.

**Signature:**

```text
league:import-draft-csv
    {path : Directory containing league_pokemon.csv, draft_config.csv, draft_order.csv, draft_picks.csv, drafts.csv, and sets.csv}
    {--replace : Delete existing draft-related rows for every league_id present in the CSVs before importing}
    {--dry-run : Parse CSVs and validate prerequisites without writing to the database}
    {--skip-db-check : Skip validating leagues, users, teams, pokedex, and pools (insert may still fail on foreign keys)}
```

**Behavior:**

- Resolves `path` as given, or under the application base path if the first attempt is not a directory.
- **`--dry-run`:** Validates and reports row counts per file and league IDs; no DB writes.
- Otherwise delegates to `LeagueDraftCsvImportService::import()` with `replace` and optional full DB prerequisite checks.

**Example:**

```bash
php artisan league:import-draft-csv ./storage/app/imports/my-export --dry-run
php artisan league:import-draft-csv ./storage/app/imports/my-export --replace
```

---

## `league:pokemon-template-import`

**Description:** Create or update a league Pokémon pool template from a CSV file (developer/CLI workflow). Each row is `nationaldex_id` and `cost` (optional header row supported).

**Signature:**

```text
league:pokemon-template-import
    {path : Absolute or relative path to CSV}
    {name : Display name for the template}
    {--slug= : URL-friendly unique key (defaults from display name)}
    {--replace : Replace rows on an existing template with the same slug}
    {--version-group= : Version group slug (e.g. scarlet-violet); defaults to highest sort_order}
    {--no-publish : Create or update template as unpublished (hidden from the public catalog)}
```

**Behavior:**

- Relative paths (non-absolute, non Windows drive paths) are resolved with `base_path()`.
- `--version-group` must match a `version_groups.slug` if provided; otherwise the service picks a default version group.
- Reports template id, name, slug, version group, rows imported, and a warning count for rows skipped due to unknown `nationaldex_id`.

**Example:**

```bash
php artisan league:pokemon-template-import storage/app/templates/pool.csv "VGC 2026 starter pool" --slug=vgc-2026 --version-group=scarlet-violet
```

---

## `league:pokemon-template-from-league`

**Description:** Create a template that copies an existing league’s Pokémon pool (`pokedex_id` + cost per `league_pokemon` row).

**Signature:**

```text
league:pokemon-template-from-league
    {league : League ID to copy the Pokémon pool from}
    {name : Display name for the new template}
    {--slug= : URL-friendly unique key (defaults from display name)}
    {--replace : Overwrite an existing template that uses the same slug}
    {--version-group= : Version group slug; overrides the league's default game version group}
    {--no-publish : Create or update template as unpublished (hidden from the public catalog)}
```

**Behavior:**

- Uses `CreateLeaguePokemonTemplateFromLeaguePoolService::createFromLeague()`.
- `--version-group` overrides the league’s default game version group when set and valid.
- Prints template metadata and `rows_written` on success.

**Example:**

```bash
php artisan league:pokemon-template-from-league 12 "Spring 2026 mirror" --slug=spring-2026-mirror --replace
```

---

## `usage-stats:rebuild`

**Description:** Rebuild global Pokémon usage aggregates: draft picks and bans, match “bring” counts, and game win/loss stats derived from regular sets and playoff matches.

**Signature:**

```text
usage-stats:rebuild
```

**Behavior:**

- Invokes `RebuildPokemonUsageStatsService` inside a database transaction.
- Clears existing `pokemon_usage_stats` rows and repopulates them from draft picks/bans, sets, and playoff match data (see `app/Modules/Stats/Services/RebuildPokemonUsageStatsService.php` for the full query logic).
- No arguments or options.

**Example:**

```bash
php artisan usage-stats:rebuild
```

**Production scheduling:** Laravel’s scheduler (e.g. Laravel Cloud with Scheduler enabled) dispatches `App\Jobs\RebuildPokemonUsageStatsJob` daily at **05:00** (app timezone). The job runs the same service as the command. Ensure **queue workers** are running so the job is executed after dispatch.

---

## `inspire` (demo closure)

Defined in `routes/console.php`. Displays an inspirational quote via Laravel’s `Inspiring` facade. Not used for application data.

```bash
php artisan inspire
```
