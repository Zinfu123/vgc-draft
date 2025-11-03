<?php

namespace App\Modules\League\Actions;

/* Define Models */
use App\Modules\League\Models\LeaguePokemon;
use App\Modules\Shared\Models\Pokedex;

/* End Define Models */

/* Define Dependencies */
/* End Define Dependencies */

class CreateEditLeaguePokemonAction
{
    public function __invoke($data)
    {
        $league_id = $data['league_id'];
        $file = $data['csv_file'];
        $handle = fopen($file->getRealPath(), 'r');
        $header = fgetcsv($handle);
        if (! is_numeric($header[0])) {
            // If first column is not numeric, assume it's a header
            $rows = [];
        } else {
            // If first column is numeric, it's data
            $rows = [$header];
        }
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        foreach ($rows as $row) {
            $nationaldex_id = $row[0];
            $pokemon = Pokedex::where('nationaldex_id', $nationaldex_id)->first();
            $cost = $row[2];
            $pokedex_id = $pokemon->id;
            $pokemon = LeaguePokemon::create([
                'league_id' => $league_id,
                'pokedex_id' => $pokedex_id,
                'cost' => $cost,
                'name' => $pokemon->name,
            ]);
        }
    }
}
