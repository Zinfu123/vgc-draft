<?php

namespace App\Jobs;

use App\Modules\Pokedex\Models\Pokedex;
use App\Modules\Pokedex\Models\VersionGroup;
use App\Modules\Pokedex\Services\PokeApiPokemonGameDataImporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ImportSinglePokedexGameDataJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public int $tries = 3;

    public function __construct(
        public int $pokedexId,
        public int $versionGroupId
    ) {}

    public function handle(PokeApiPokemonGameDataImporter $importer): void
    {
        $pokedex = Pokedex::query()->findOrFail($this->pokedexId);
        $versionGroup = VersionGroup::query()->findOrFail($this->versionGroupId);

        $importer->import($pokedex, $versionGroup);
    }
}
