<?php

namespace App\Modules\Pokepaste\Actions;

use App\Models\User;
use App\Modules\Playoffs\Models\PlayoffMatch;
use App\Modules\Pokepaste\Models\SetTeamPokepaste;
use App\Modules\Pokepaste\Services\EnsureSetTeamPokepasteSlotRows;
use App\Modules\Teams\Models\Team;

class ReadPlayoffMatchPokepasteSideSummariesAction
{
    public function __construct(
        private EnsureSetTeamPokepasteSlotRows $ensureSlotRows,
    ) {}

    /**
     * @return array{team1: array{public_id: string, has_data: bool}|null, team2: array{public_id: string, has_data: bool}|null}
     */
    public function __invoke(PlayoffMatch $match, ?User $viewer, bool $isLeagueAdmin): array
    {
        $complete = $match->isComplete();

        return [
            'team1' => $this->forTeam($match, (int) $match->team1_id, $viewer, $isLeagueAdmin, $complete),
            'team2' => $this->forTeam($match, (int) $match->team2_id, $viewer, $isLeagueAdmin, $complete),
        ];
    }

    /**
     * @return array{public_id: string, has_data: bool}|null
     */
    private function forTeam(PlayoffMatch $match, int $teamId, ?User $viewer, bool $isLeagueAdmin, bool $matchComplete): ?array
    {
        if ($teamId === 0) {
            return null;
        }

        $record = SetTeamPokepaste::query()
            ->where('matchable_type', PlayoffMatch::class)
            ->where('matchable_id', $match->id)
            ->where('team_id', $teamId)
            ->first();

        if ($record === null) {
            if ($matchComplete || $isLeagueAdmin) {
                return [
                    'public_id' => '',
                    'has_data' => false,
                ];
            }

            return null;
        }

        ($this->ensureSlotRows)($record);
        $hasData = $record->pasteSlots()->whereNotNull('league_pokemon_id')->exists();

        if ($matchComplete) {
            return [
                'public_id' => (string) $record->public_id,
                'has_data' => $hasData,
            ];
        }

        if ($isLeagueAdmin) {
            return [
                'public_id' => '',
                'has_data' => $hasData,
            ];
        }

        $teamRow = Team::query()->find($teamId);
        if ($viewer !== null && $teamRow !== null && (int) $teamRow->user_id === (int) $viewer->id) {
            return [
                'public_id' => (string) $record->public_id,
                'has_data' => $hasData,
            ];
        }

        return null;
    }
}
