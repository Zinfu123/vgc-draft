<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the mistaken duplicate Mega Venusaur row (nationaldex 3.001). The canonical
 * row remains id 4 with nationaldex 3.002 per the main pokedex CSV.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pokedex')) {
            return;
        }

        DB::table('pokedex')
            ->where('name', 'venusaur-mega')
            ->where('nationaldex_id', '>=', 3.0005)
            ->where('nationaldex_id', '<', 3.0015)
            ->delete();

        $this->resyncPokedexIdSequence();
    }

    public function down(): void
    {
        // Intentionally empty: re-inserting the duplicate would restore incorrect data.
    }

    private function resyncPokedexIdSequence(): void
    {
        $table = 'pokedex';
        if (! Schema::hasTable($table)) {
            return;
        }

        $max = DB::table($table)->max('id');
        if ($max === null) {
            return;
        }

        $max = (int) $max;
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            if (! Schema::hasTable('sqlite_sequence')) {
                return;
            }
            $exists = DB::table('sqlite_sequence')->where('name', $table)->exists();
            if ($exists) {
                DB::table('sqlite_sequence')->where('name', $table)->update(['seq' => $max]);
            } else {
                DB::table('sqlite_sequence')->insert(['name' => $table, 'seq' => $max]);
            }
        } elseif ($driver === 'mysql') {
            DB::statement('ALTER TABLE `'.$table.'` AUTO_INCREMENT = '.($max + 1));
        } elseif ($driver === 'pgsql') {
            $seq = DB::selectOne('SELECT pg_get_serial_sequence(?, ?) AS s', [$table, 'id']);
            if ($seq !== null && $seq->s !== null) {
                DB::statement('SELECT setval(?, ?, true)', [$seq->s, $max]);
            }
        }
    }
};
