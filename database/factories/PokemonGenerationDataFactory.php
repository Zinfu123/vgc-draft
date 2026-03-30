<?php

namespace Database\Factories;

use App\Modules\Pokedex\Models\PokemonGenerationData;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PokemonGenerationData>
 */
class PokemonGenerationDataFactory extends Factory
{
    protected $model = PokemonGenerationData::class;

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
            'ability_primary_pokeapi_id' => 51,
            'ability_secondary_pokeapi_id' => null,
            'ability_hidden_pokeapi_id' => null,
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
