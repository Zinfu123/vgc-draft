<?php

namespace Database\Seeders;

use App\Actions\SyncPokedexFromCsvAction;
use Illuminate\Database\Seeder;

class PokedexSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(SyncPokedexFromCsvAction::class)->handle();
    }
}
