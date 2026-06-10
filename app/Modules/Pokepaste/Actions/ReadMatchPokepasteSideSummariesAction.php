<?php

namespace App\Modules\Pokepaste\Actions;

use App\Modules\Matches\Models\Set;
use App\Modules\Pokepaste\Models\SetTeamPokepaste;

class ReadMatchPokepasteSideSummariesAction
{
    /**
     * @return array{team1: array{public_id: string, has_data: bool}|null, team2: array{public_id: string, has_data: bool}|null}
     */
    public function __invoke(Set $set): array
    {
        return [
            'team1' => $this->forTeam($set->id, (int) $set->team1_id),
            'team2' => $this->forTeam($set->id, (int) $set->team2_id),
        ];
    }

    /**
     * @return array{public_id: string, has_data: bool}|null
     */
    private function forTeam(int $setId, int $teamId): ?array
    {
        $record = SetTeamPokepaste::query()
            ->where('matchable_type', Set::class)
            ->where('matchable_id', $setId)
            ->where('team_id', $teamId)
            ->first();

        if ($record === null) {
            return null;
        }

        $hasData = $record->pasteSlots()->whereNotNull('league_pokemon_id')->exists();

        return [
            'public_id' => (string) $record->public_id,
            'has_data' => $hasData,
        ];
    }
}
