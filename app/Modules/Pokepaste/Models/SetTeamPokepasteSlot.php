<?php

namespace App\Modules\Pokepaste\Models;

use App\Modules\League\Models\LeaguePokemon;
use App\Modules\Pokedex\Models\VersionGroupHeldItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SetTeamPokepasteSlot extends Model
{
    protected $table = 'set_team_pokepaste_slots';

    protected $fillable = [
        'set_team_pokepaste_id',
        'slot_index',
        'league_pokemon_id',
        'ability',
        'moves',
        'version_group_held_item_id',
        'nature',
        'tera_type',
        'ev_hp',
        'ev_atk',
        'ev_def',
        'ev_spa',
        'ev_spd',
        'ev_spe',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'slot_index' => 'integer',
            'moves' => 'array',
            'nature' => 'integer',
            'ev_hp' => 'integer',
            'ev_atk' => 'integer',
            'ev_def' => 'integer',
            'ev_spa' => 'integer',
            'ev_spd' => 'integer',
            'ev_spe' => 'integer',
        ];
    }

    public function pokepaste(): BelongsTo
    {
        return $this->belongsTo(SetTeamPokepaste::class, 'set_team_pokepaste_id');
    }

    public function leaguePokemon(): BelongsTo
    {
        return $this->belongsTo(LeaguePokemon::class, 'league_pokemon_id');
    }

    public function heldItem(): BelongsTo
    {
        return $this->belongsTo(VersionGroupHeldItem::class, 'version_group_held_item_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function toFrontendSlotArray(): array
    {
        $moves = $this->moves ?? [];
        if (! is_array($moves)) {
            $moves = ['', '', '', ''];
        }
        $moves = array_values(array_pad(array_slice($moves, 0, 4), 4, ''));

        $evs = null;
        $evParts = [];
        foreach (
            [
                'hp' => $this->ev_hp,
                'atk' => $this->ev_atk,
                'def' => $this->ev_def,
                'spa' => $this->ev_spa,
                'spd' => $this->ev_spd,
                'spe' => $this->ev_spe,
            ] as $key => $value
        ) {
            if ($value > 0) {
                $evParts[$key] = $value;
            }
        }
        if ($evParts !== []) {
            $evs = $evParts;
        }

        return [
            'league_pokemon_id' => $this->league_pokemon_id,
            'ability' => (string) ($this->ability ?? ''),
            'moves' => $moves,
            'version_group_held_item_id' => $this->version_group_held_item_id,
            'nature' => $this->nature,
            'tera_type' => $this->tera_type,
            'evs' => $evs,
        ];
    }

    /**
     * @param  array<string, mixed>  $normalizedSlot
     */
    public static function attributesFromNormalizedSlot(array $normalizedSlot): array
    {
        $evs = $normalizedSlot['evs'] ?? null;
        if (! is_array($evs)) {
            $evs = [];
        }

        $leaguePokemonId = $normalizedSlot['league_pokemon_id'] ?? null;
        $leaguePokemonId = is_numeric($leaguePokemonId) && (int) $leaguePokemonId > 0 ? (int) $leaguePokemonId : null;

        $ability = trim((string) ($normalizedSlot['ability'] ?? ''));

        return [
            'league_pokemon_id' => $leaguePokemonId,
            'ability' => $ability !== '' ? $ability : null,
            'moves' => $normalizedSlot['moves'],
            'version_group_held_item_id' => $normalizedSlot['version_group_held_item_id'] ?? null,
            'nature' => $normalizedSlot['nature'] ?? null,
            'tera_type' => $normalizedSlot['tera_type'] ?? null,
            'ev_hp' => max(0, min(252, (int) ($evs['hp'] ?? 0))),
            'ev_atk' => max(0, min(252, (int) ($evs['atk'] ?? 0))),
            'ev_def' => max(0, min(252, (int) ($evs['def'] ?? 0))),
            'ev_spa' => max(0, min(252, (int) ($evs['spa'] ?? 0))),
            'ev_spd' => max(0, min(252, (int) ($evs['spd'] ?? 0))),
            'ev_spe' => max(0, min(252, (int) ($evs['spe'] ?? 0))),
        ];
    }
}
