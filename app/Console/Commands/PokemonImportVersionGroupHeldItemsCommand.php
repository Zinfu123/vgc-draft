<?php

namespace App\Console\Commands;

use App\Modules\Pokedex\Models\VersionGroup;
use App\Modules\Pokedex\Services\PokeApiVersionGroupHeldItemImporter;
use Illuminate\Console\Command;

class PokemonImportVersionGroupHeldItemsCommand extends Command
{
    protected $signature = 'pokemon:import-version-group-held-items
                            {slug? : Version group slug (e.g. scarlet-violet)}';

    protected $description = 'Import PokeAPI holdable items (held-items, berries, plates, type items, choice, species-specific) into version_group_held_items';

    public function handle(PokeApiVersionGroupHeldItemImporter $importer): int
    {
        $slug = $this->argument('slug') ?: (string) config('pokemon.default_version_group_slug');
        $versionGroup = VersionGroup::query()->where('slug', $slug)->first();
        if ($versionGroup === null) {
            $this->error("Unknown version group slug: {$slug}");

            return self::FAILURE;
        }

        $this->info("Importing held items from PokeAPI for [{$slug}]…");

        $count = $importer->importForVersionGroup($versionGroup);

        $this->info("Done. Wrote or updated {$count} held item rows.");

        return self::SUCCESS;
    }
}
