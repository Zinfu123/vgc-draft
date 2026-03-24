<?php

namespace App\Modules\League\Actions;

use App\Modules\League\Services\ImportLeaguePokemonToLeagueFromCsvService;
use Illuminate\Http\UploadedFile;

class CreateEditLeaguePokemonAction
{
    public function __construct(
        private ImportLeaguePokemonToLeagueFromCsvService $importCsv,
    ) {}

    /**
     * @param  array{league_id: int, csv_file: UploadedFile}  $data
     * @return array{upserted: int, skipped_unknown_dex: int}
     */
    public function __invoke(array $data): array
    {
        return $this->importCsv->import((int) $data['league_id'], $data['csv_file']);
    }
}
