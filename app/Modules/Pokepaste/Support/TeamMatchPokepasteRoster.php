<?php

namespace App\Modules\Pokepaste\Support;

use App\Modules\Pokepaste\Models\SetTeamPokepaste;
use App\Modules\Pokepaste\Services\EnsureSetTeamPokepasteSlotRows;

class TeamMatchPokepasteRoster
{
    public function __construct(
        private EnsureSetTeamPokepasteSlotRows $ensureSlotRows,
    ) {}

    public function hasData(SetTeamPokepaste $pokepaste): bool
    {
        ($this->ensureSlotRows)($pokepaste);

        return $pokepaste->pasteSlots()->whereNotNull('league_pokemon_id')->exists();
    }
}
