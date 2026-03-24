<?php

namespace Database\Factories;

use App\Modules\Pokedex\Models\PokemonGameData;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PokemonGameData>
 */
class PokemonGameDataFactory extends Factory
{
    protected $model = PokemonGameData::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pokedex_id' => 1,
            'version_group_id' => 1,
            'pokeapi_pokemon_id' => fake()->numberBetween(1, 1025),
            'hp' => 50,
            'atk' => 50,
            'def' => 50,
            'spa' => 50,
            'spd' => 50,
            'spe' => 50,
            'type1' => 'Normal',
            'type2' => null,
            'ability_primary' => 'Keen Eye',
            'ability_secondary' => null,
            'ability_hidden' => null,
            'learnset' => [
                ['move_id' => 33, 'move_name' => 'tackle', 'method' => 'level-up', 'level' => 1],
            ],
            'mechanics' => [
                'tera_capable' => true,
                'mega' => false,
                'z_move' => false,
                'dynamax' => false,
                'gmax' => false,
            ],
        ];
    }
}
