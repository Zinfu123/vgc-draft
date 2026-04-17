<?php

namespace App\Modules\Draft\Models;

use App\Modules\League\Models\LeaguePokemon;
use App\Modules\Teams\Models\Team;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DraftWishlistItem extends Model
{
    protected $table = 'draft_wishlist_items';

    protected $fillable = [
        'team_id',
        'league_pokemon_id',
        'sort_order',
    ];

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<LeaguePokemon, $this>
     */
    public function leaguePokemon(): BelongsTo
    {
        return $this->belongsTo(LeaguePokemon::class);
    }
}
