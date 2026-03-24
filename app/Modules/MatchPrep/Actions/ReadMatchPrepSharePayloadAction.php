<?php

namespace App\Modules\MatchPrep\Actions;

use App\Modules\League\Models\LeaguePokemon;
use App\Modules\Matches\Models\Set;
use App\Modules\MatchPrep\Models\MatchPrepNote;
use App\Modules\MatchPrep\Support\MatchPrepNotePayload;
use App\Modules\Shared\Actions\LogoToUrlAction;
use App\Modules\Teams\Models\Team;

class ReadMatchPrepSharePayloadAction
{
    public function __construct(
        private BuildLeaguePokemonDraftPreviewAction $buildDraftPreview,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function __invoke(string $shareUuid): ?array
    {
        $note = MatchPrepNote::query()
            ->where('share_uuid', $shareUuid)
            ->where('share_enabled', true)
            ->first();

        if ($note === null) {
            return null;
        }

        $set = Set::query()
            ->whereKey($note->set_id)
            ->with(['team1', 'team2', 'league'])
            ->first();

        if ($set === null || $set->team1_id === null || $set->team2_id === null) {
            return null;
        }

        $ownerTeam = Team::query()
            ->where('user_id', $note->user_id)
            ->where('league_id', $set->league_id)
            ->first();

        if ($ownerTeam === null) {
            return null;
        }

        $opponentTeam = $set->team1_id === $ownerTeam->id ? $set->team2 : $set->team1;
        if ($opponentTeam === null) {
            return null;
        }

        $logoAction = new LogoToUrlAction;
        $opponentLogo = $opponentTeam->logo;
        if ($opponentLogo !== null) {
            $opponentLogo = $logoAction->logoToUrl($opponentLogo);
        }

        $opponentRoster = LeaguePokemon::query()
            ->where('drafted_by', $opponentTeam->id)
            ->where('league_id', $set->league_id)
            ->with('pokemon')
            ->orderByDesc('cost')
            ->get();

        $myRoster = LeaguePokemon::query()
            ->where('drafted_by', $ownerTeam->id)
            ->where('league_id', $set->league_id)
            ->with('pokemon')
            ->orderByDesc('cost')
            ->get();

        return [
            'match' => [
                'my_team_id' => $ownerTeam->id,
                'set' => [
                    'id' => $set->id,
                    'round' => $set->round,
                    'team1_id' => (int) $set->team1_id,
                    'team2_id' => (int) $set->team2_id,
                    'team1_score' => $set->team1_score,
                    'team2_score' => $set->team2_score,
                    'winner_id' => $set->winner_id,
                    'replay1' => $set->replay1,
                    'replay2' => $set->replay2,
                    'replay3' => $set->replay3,
                ],
                'opponent' => [
                    'team_id' => $opponentTeam->id,
                    'name' => $opponentTeam->name,
                    'logo' => $opponentLogo,
                    'roster' => ($this->buildDraftPreview)($opponentRoster),
                ],
                'my_roster' => ($this->buildDraftPreview)($myRoster),
                'note' => MatchPrepNotePayload::forNote($note),
            ],
            'league_name' => $set->league?->name ?? '',
        ];
    }
}
