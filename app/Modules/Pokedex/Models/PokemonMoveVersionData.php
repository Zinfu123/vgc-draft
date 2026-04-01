<?php

namespace App\Modules\Pokedex\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PokemonMoveVersionData extends Model
{
    protected $table = 'pokemon_move_version_data';

    protected $fillable = [
        'version_group_id',
        'pokeapi_move_id',
        'name',
        'type_slug',
        'damage_class',
        'power',
        'accuracy',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pokeapi_move_id' => 'integer',
            'power' => 'integer',
            'accuracy' => 'integer',
        ];
    }

    public function versionGroup(): BelongsTo
    {
        return $this->belongsTo(VersionGroup::class, 'version_group_id');
    }
}
