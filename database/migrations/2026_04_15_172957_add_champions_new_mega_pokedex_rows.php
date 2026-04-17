<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Inserts pokedex rows for Champions-exclusive new Mega Evolutions that do not
 * exist in any previous generation database.
 *
 * Existing Megas (e.g. Mega Venusaur, Mega Charizard) already have rows from
 * the XY/ORAS era and are skipped here. Only Megas introduced for the first
 * time in Pokémon Champions are added.
 *
 * nationaldex_id convention: {dex_number}.{form_index} (e.g. 609.001).
 * Types match the base Pokémon unless Serebii data explicitly shows a change.
 */
return new class extends Migration
{
    /**
     * @return array<int, array{nationaldex_id: float, name: string, type1: string, type2: string}>
     */
    private function newMegas(): array
    {
        return [
            // Kanto
            ['nationaldex_id' => 36.001, 'name' => 'clefable-mega', 'type1' => 'Fairy', 'type2' => '-'],
            ['nationaldex_id' => 71.001, 'name' => 'victreebel-mega', 'type1' => 'Grass', 'type2' => 'Poison'],
            ['nationaldex_id' => 121.001, 'name' => 'starmie-mega', 'type1' => 'Water', 'type2' => 'Psychic'],
            ['nationaldex_id' => 149.001, 'name' => 'dragonite-mega', 'type1' => 'Dragon', 'type2' => 'Flying'],
            // Johto
            ['nationaldex_id' => 154.001, 'name' => 'meganium-mega', 'type1' => 'Grass', 'type2' => '-'],
            ['nationaldex_id' => 160.001, 'name' => 'feraligatr-mega', 'type1' => 'Water', 'type2' => '-'],
            ['nationaldex_id' => 227.001, 'name' => 'skarmory-mega', 'type1' => 'Steel', 'type2' => 'Flying'],
            // Hoenn
            ['nationaldex_id' => 358.001, 'name' => 'chimecho-mega', 'type1' => 'Psychic', 'type2' => '-'],
            // Sinnoh
            ['nationaldex_id' => 478.001, 'name' => 'froslass-mega', 'type1' => 'Ice', 'type2' => 'Ghost'],
            // Unova
            ['nationaldex_id' => 500.001, 'name' => 'emboar-mega', 'type1' => 'Fire', 'type2' => 'Fighting'],
            ['nationaldex_id' => 530.001, 'name' => 'excadrill-mega', 'type1' => 'Ground', 'type2' => 'Steel'],
            ['nationaldex_id' => 609.001, 'name' => 'chandelure-mega', 'type1' => 'Fire', 'type2' => 'Ghost'],
            ['nationaldex_id' => 623.001, 'name' => 'golurk-mega', 'type1' => 'Ground', 'type2' => 'Ghost'],
            // Kalos
            ['nationaldex_id' => 652.001, 'name' => 'chesnaught-mega', 'type1' => 'Grass', 'type2' => 'Fighting'],
            ['nationaldex_id' => 655.001, 'name' => 'delphox-mega', 'type1' => 'Fire', 'type2' => 'Psychic'],
            ['nationaldex_id' => 658.003, 'name' => 'greninja-mega', 'type1' => 'Water', 'type2' => 'Dark'],
            ['nationaldex_id' => 670.002, 'name' => 'floette-eternal-mega', 'type1' => 'Fairy', 'type2' => '-'],
            ['nationaldex_id' => 678.003, 'name' => 'meowstic-mega', 'type1' => 'Psychic', 'type2' => '-'],
            ['nationaldex_id' => 701.001, 'name' => 'hawlucha-mega', 'type1' => 'Fighting', 'type2' => 'Flying'],
            // Alola
            ['nationaldex_id' => 740.001, 'name' => 'crabominable-mega', 'type1' => 'Fighting', 'type2' => 'Ice'],
            ['nationaldex_id' => 780.001, 'name' => 'drampa-mega', 'type1' => 'Normal', 'type2' => 'Dragon'],
            // Paldea
            ['nationaldex_id' => 952.001, 'name' => 'scovillain-mega', 'type1' => 'Grass', 'type2' => 'Fire'],
            ['nationaldex_id' => 970.001, 'name' => 'glimmora-mega', 'type1' => 'Rock', 'type2' => 'Poison'],
        ];
    }

    public function up(): void
    {
        $now = now();

        foreach ($this->newMegas() as $mega) {
            DB::table('pokedex')->insertOrIgnore([
                'nationaldex_id' => $mega['nationaldex_id'],
                'name' => $mega['name'],
                'type1' => $mega['type1'],
                'type2' => $mega['type2'],
                'sprite_url' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $names = array_column($this->newMegas(), 'name');

        DB::table('pokedex')->whereIn('name', $names)->delete();
    }
};
