<?php

namespace Database\Seeders;

use App\Modules\Pokedex\Models\Pokedex;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PokedexSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {

        // Clear the table
        DB::table('pokedex')->delete();

        $csvFile = fopen(Storage::disk('s3')->url('pokedex.csv'), 'r');
        $firstline = true;
        while (($data = fgetcsv($csvFile, 2000, ',')) !== false) {
            if (! $firstline) {
                Pokedex::create([
                    'id' => $data[0],
                    'nationaldex_id' => $data[1],
                    'name' => $data[2],
                    'type1' => $data[3],
                    'type2' => $data[4] ?: null,
                    'sprite_url' => $data[5],
                ]);
            }
            $firstline = false;
        }
        fclose($csvFile);
    }
}
