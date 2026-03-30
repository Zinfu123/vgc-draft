<?php

namespace App\Modules\Pokedex\Models;

use Database\Factories\PokemonGenerationDataFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PokemonGenerationData extends Model
{
    /** @use HasFactory<PokemonGenerationDataFactory> */
    use HasFactory;

    protected $table = 'pokemon_generation_data';

    protected $fillable = [
        'pokedex_id',
        'version_group_id',
        'pokeapi_pokemon_id',
        'hp',
        'atk',
        'def',
        'spa',
        'spd',
        'spe',
        'type1',
        'type2',
        'ability_primary_pokeapi_id',
        'ability_secondary_pokeapi_id',
        'ability_hidden_pokeapi_id',
        'learnset',
        'mechanics',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'learnset' => 'array',
            'mechanics' => 'array',
        ];
    }

    public function pokedex(): BelongsTo
    {
        return $this->belongsTo(Pokedex::class, 'pokedex_id');
    }

    public function versionGroup(): BelongsTo
    {
        return $this->belongsTo(VersionGroup::class, 'version_group_id');
    }

    protected static function newFactory(): PokemonGenerationDataFactory
    {
        return PokemonGenerationDataFactory::new();
    }
}
