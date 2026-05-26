<?php

namespace App\Modules\Pokepaste\Actions;

use App\Modules\League\Models\League;
use App\Modules\Pokepaste\Models\SetTeamPokepaste;
use App\Modules\Pokepaste\Models\SetTeamPokepasteSlot;
use App\Modules\Pokepaste\Services\EnsureSetTeamPokepasteSlotRows;
use App\Modules\Pokepaste\Services\PokepasteSlotValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class UpdateSetTeamPokepasteAction
{
    public function __construct(
        private PokepasteSlotValidator $slotValidator,
        private EnsureSetTeamPokepasteSlotRows $ensureSlotRows,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $slots
     */
    public function __invoke(
        SetTeamPokepaste $pokepaste,
        League $league,
        array $slots,
        bool $detailsVisible,
    ): RedirectResponse {
        $pokepaste->loadMissing('team');
        $team = $pokepaste->team;
        $normalized = $this->slotValidator->validateAndNormalize($team, $league, $slots, allowPartialSave: true);

        ($this->ensureSlotRows)($pokepaste);

        DB::transaction(function () use ($pokepaste, $normalized, $detailsVisible): void {
            foreach ($normalized as $index => $slot) {
                SetTeamPokepasteSlot::query()->updateOrCreate(
                    [
                        'set_team_pokepaste_id' => $pokepaste->id,
                        'slot_index' => $index,
                    ],
                    SetTeamPokepasteSlot::attributesFromNormalizedSlot($slot)
                );
            }

            $pokepaste->update(['details_visible' => $detailsVisible]);
        });

        return redirect()
            ->route('pokepaste.show', ['pokepaste' => $pokepaste, 'edit' => 1])
            ->with('success', 'Match team paste saved.');
    }
}
