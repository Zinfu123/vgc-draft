<?php

namespace App\Modules\Pokedex\Models;

use Database\Factories\PokemonGameDataFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PokemonGameData extends Model
{
    /** @use HasFactory<PokemonGameDataFactory> */
    use HasFactory;

    protected $table = 'pokemon_game_data';

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
        'ability_primary',
        'ability_secondary',
        'ability_hidden',
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

    protected static function newFactory(): PokemonGameDataFactory
    {
        return PokemonGameDataFactory::new();
    }
}
