<?php

namespace App\Modules\Pokepaste\Services;

use App\Modules\Pokepaste\Models\SetTeamPokepaste;
use App\Modules\Pokepaste\Models\SetTeamPokepasteSlot;

class EnsureSetTeamPokepasteSlotRows
{
    public function __invoke(SetTeamPokepaste $pokepaste): void
    {
        $existing = $pokepaste->pasteSlots()
            ->pluck('slot_index')
            ->all();

        for ($i = 0; $i < 6; $i++) {
            if (in_array($i, $existing, true)) {
                continue;
            }

            SetTeamPokepasteSlot::query()->create([
                'set_team_pokepaste_id' => $pokepaste->id,
                'slot_index' => $i,
                'moves' => ['', '', '', ''],
                'ev_hp' => 0,
                'ev_atk' => 0,
                'ev_def' => 0,
                'ev_spa' => 0,
                'ev_spd' => 0,
                'ev_spe' => 0,
            ]);
        }
    }
}
