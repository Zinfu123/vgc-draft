<?php

namespace App\Modules\Pokepaste\Actions;

use App\Modules\Matches\Models\Set;
use App\Modules\Pokepaste\Models\SetTeamPokepaste;
use App\Modules\Pokepaste\Services\EnsureSetTeamPokepasteSlotRows;
use App\Modules\Teams\Models\Team;

class ReadMatchPokepastePayloadAction
{
    public function __construct(
        private EnsureSetTeamPokepasteSlotRows $ensureSlotRows,
    ) {}

    /**
     * @return array{pokepaste_public_id: string}
     */
    public function __invoke(Set $set, Team $team): array
    {
        $record = SetTeamPokepaste::query()->firstOrCreate(
            [
                'set_id' => $set->id,
                'team_id' => $team->id,
            ],
        );

        ($this->ensureSlotRows)($record);

        return [
            'pokepaste_public_id' => (string) $record->public_id,
        ];
    }
}
