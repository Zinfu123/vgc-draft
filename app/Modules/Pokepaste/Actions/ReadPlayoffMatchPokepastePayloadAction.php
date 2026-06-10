<?php

namespace App\Modules\Pokepaste\Actions;

use App\Modules\Playoffs\Models\PlayoffMatch;
use App\Modules\Playoffs\Services\PlayoffMatchPokepastePlanningService;
use App\Modules\Pokepaste\Models\SetTeamPokepaste;
use App\Modules\Pokepaste\Services\EnsureSetTeamPokepasteSlotRows;
use App\Modules\Teams\Models\Team;

class ReadPlayoffMatchPokepastePayloadAction
{
    public function __construct(
        private EnsureSetTeamPokepasteSlotRows $ensureSlotRows,
        private PlayoffMatchPokepastePlanningService $planningService,
    ) {}

    /**
     * @return array{pokepaste_public_id: string}|null
     */
    public function __invoke(PlayoffMatch $match, Team $team): ?array
    {
        if (! $this->planningService->mayCoachPlanPokepaste($match, (int) $team->id)) {
            return null;
        }

        $record = SetTeamPokepaste::query()->firstOrCreate(
            [
                'matchable_type' => PlayoffMatch::class,
                'matchable_id' => $match->id,
                'team_id' => $team->id,
            ],
        );

        ($this->ensureSlotRows)($record);

        return [
            'pokepaste_public_id' => (string) $record->public_id,
        ];
    }
}
