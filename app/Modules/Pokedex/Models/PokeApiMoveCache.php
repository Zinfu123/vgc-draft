<?php

namespace App\Modules\Pokedex\Models;

use Illuminate\Database\Eloquent\Model;

class PokeApiMoveCache extends Model
{
    protected $table = 'pokeapi_move_cache';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'name',
        'type_slug',
        'damage_class',
        'power',
        'accuracy',
        'ailment_name',
        'short_effect_en',
        'updated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'power' => 'integer',
            'accuracy' => 'integer',
            'updated_at' => 'datetime',
        ];
    }
}
