<?php

namespace App\Modules\League\Services;

use App\Modules\League\Models\LeaguePokemon;
use App\Modules\Pokedex\Models\Pokedex;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ImportLeaguePokemonToLeagueFromCsvService
{
    public function __construct(
        private NationaldexCostCsvReader $csvReader,
    ) {}

    /**
     * @return array{upserted: int, skipped_unknown_dex: int}
     */
    public function import(int $leagueId, UploadedFile $file): array
    {
        $rows = $this->csvReader->readFromUploadedFile($file);

        return DB::transaction(function () use ($leagueId, $rows): array {
            $upserted = 0;
            $skipped = 0;

            foreach ($rows as [$nationaldexId, $cost]) {
                $pokemon = Pokedex::query()->where('nationaldex_id', $nationaldexId)->first();
                if ($pokemon === null) {
                    $skipped++;

                    continue;
                }

                LeaguePokemon::query()->updateOrCreate(
                    [
                        'league_id' => $leagueId,
                        'pokedex_id' => $pokemon->id,
                    ],
                    [
                        'name' => $pokemon->name,
                        'cost' => $cost,
                    ]
                );
                $upserted++;
            }

            return [
                'upserted' => $upserted,
                'skipped_unknown_dex' => $skipped,
            ];
        });
    }
}
