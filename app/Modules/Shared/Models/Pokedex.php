<?php

namespace App\Modules\Shared\Models;

use Illuminate\Database\Eloquent\Model;

class Pokedex extends Model
{
    protected $table = 'pokedex';

    protected $fillable = [
        'name',
        'type1',
        'type2',
        'sprite_url',
    ];
}
