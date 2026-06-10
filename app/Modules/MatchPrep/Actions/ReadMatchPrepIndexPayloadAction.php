<?php

namespace App\Modules\MatchPrep\Actions;

use App\Models\User;
use App\Modules\League\Models\LeaguePokemon;
use App\Modules\Matches\Actions\ShowSetsAction;
use App\Modules\Matches\Models\Set;
use App\Modules\MatchPrep\Models\MatchPrepNote;
use App\Modules\MatchPrep\Support\MatchPrepNotePayload;
use App\Modules\Shared\Actions\LogoToUrlAction;
use App\Modules\Teams\Models\Team;
use Illuminate\Http\Request;

class ReadMatchPrepIndexPayloadAction
{
    public function __construct(
        private ShowSetsAction $showSetsAction,
        private BuildLeaguePokemonDraftPreviewAction $buildDraftPreview,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function __invoke(User $user, Request $request): array
    {
        $listLeagues = new ListUserLeaguesForPrepAction;
        $leagues = $listLeagues($user->id);

        $selectedLeagueId = $request->integer('league_id');
        if ($selectedLeagueId === 0 && $leagues !== []) {
            $selectedLeagueId = (int) $leagues[0]['id'];
        }

        $team = null;
        if ($selectedLeagueId !== 0) {
            $team = Team::query()
                ->where('user_id', $user->id)
                ->where('league_id', $selectedLeagueId)
                ->first();
        }

        $setsPayload = [];
        if ($team !== null) {
            /** @var \Illuminate\Support\Collection<int, Set> $sets */
            $sets = ($this->showSetsAction)([
                'command' => 'team',
                'league_id' => $selectedLeagueId,
                'team_id' => $team->id,
            ]);

            // Eager-load both teams on the entire collection at once
            $sets->loadMissing(['team1', 'team2']);

            // Load my roster once — it's constant across all sets
            $myRoster = LeaguePokemon::query()
                ->where('drafted_by', $team->id)
                ->where('league_id', $selectedLeagueId)
                ->with('pokemon')
                ->orderByDesc('cost')
                ->get();
            $myRosterBuilt = ($this->buildDraftPreview)($myRoster);

            // Collect all opponent team IDs then load all their rosters in one query
            $opponentTeamIds = $sets->map(fn (Set $set) => $set->team1_id === $team->id ? $set->team2_id : $set->team1_id)
                ->filter()
                ->unique()
                ->values()
                ->all();

            $allOpponentRosters = LeaguePokemon::query()
                ->whereIn('drafted_by', $opponentTeamIds)
                ->where('league_id', $selectedLeagueId)
                ->with('pokemon')
                ->orderByDesc('cost')
                ->get()
                ->groupBy('drafted_by');

            // Load all notes for these sets in one query
            $allNotes = MatchPrepNote::query()
                ->where('user_id', $user->id)
                ->whereIn('set_id', $sets->pluck('id')->all())
                ->get()
                ->keyBy('set_id');

            $logoAction = new LogoToUrlAction;

            foreach ($sets->sortBy('round') as $set) {
                $opponentTeam = $set->team1_id === $team->id ? $set->team2 : $set->team1;
                if ($opponentTeam === null) {
                    continue;
                }

                $opponentLogo = $opponentTeam->logo;
                if ($opponentLogo !== null) {
                    $opponentLogo = $logoAction->logoToUrl($opponentLogo);
                }

                $opponentRoster = $allOpponentRosters->get($opponentTeam->id) ?? collect();
                $note = $allNotes->get($set->id);

                $setsPayload[] = [
                    'my_team_id' => $team->id,
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
                    'my_roster' => $myRosterBuilt,
                    'note' => MatchPrepNotePayload::forNote($note),
                ];
            }
        }

        return [
            'leagues' => $leagues,
            'selected_league_id' => $selectedLeagueId,
            'team_id' => $team?->id,
            'matches' => $setsPayload,
        ];
    }
}
