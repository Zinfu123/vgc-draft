<?php

namespace App\Modules\League\Models;

use App\Modules\Pokedex\Models\Pokedex;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaguePokemonTemplateRow extends Model
{
    protected $table = 'league_pokemon_template_rows';

    protected $fillable = [
        'league_pokemon_template_id',
        'pokedex_id',
        'cost',
    ];

    /**
     * @return BelongsTo<LeaguePokemonTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(LeaguePokemonTemplate::class, 'league_pokemon_template_id');
    }

    /**
     * @return BelongsTo<Pokedex, $this>
     */
    public function pokedex(): BelongsTo
    {
        return $this->belongsTo(Pokedex::class, 'pokedex_id');
    }
}
