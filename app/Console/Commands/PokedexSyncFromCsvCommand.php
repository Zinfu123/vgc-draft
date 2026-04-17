<?php

namespace App\Console\Commands;

use App\Actions\SyncPokedexFromCsvAction;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

class PokedexSyncFromCsvCommand extends Command
{
    protected $signature = 'pokedex:sync-from-csv
                            {--path= : CSV path (default: database/data/pokedex.csv)}';

    protected $description = 'Upsert the pokedex table from a CSV file (preserves IDs; does not delete rows absent from the CSV).';

    public function handle(SyncPokedexFromCsvAction $sync): int
    {
        $raw = $this->option('path');
        $path = is_string($raw) && $raw !== '' ? $raw : null;

        try {
            $count = $sync->handle($path);
            $this->info("Synced {$count} pokedex row(s) from CSV.");

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
