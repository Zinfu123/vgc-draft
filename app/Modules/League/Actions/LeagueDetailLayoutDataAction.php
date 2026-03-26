<?php

namespace App\Modules\League\Actions;

use App\Modules\League\Models\League;
use App\Modules\Matches\Models\MatchConfig;
use App\Modules\Teams\Actions\ReadTeamAction;
use App\Modules\Teams\Models\Team;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class LeagueDetailLayoutDataAction
{
    public function __construct(
        private ReadTeamAction $readTeamAction,
        private ReadLeagueDraftAction $readLeagueDraftAction,
    ) {}

    /**
     * @return array{league: League, teams: \Illuminate\Support\Collection, draft: mixed, adminFlag: int, matchConfig: MatchConfig|object}
     */
    public function __invoke(League $league): array
    {
        $teams = ($this->readTeamAction)(['league_id' => $league->id, 'command' => 'league']);
        $user_team = Team::query()->where('user_id', Auth::user()->id)->where('league_id', $league->id)->whereNull('dropped_at')->select('id', 'admin_flag')->first();
        $isOwner = (int) Auth::user()->id === (int) $league->league_owner;
        $adminflag = $isOwner || ($user_team && (int) $user_team->admin_flag === 1) ? 1 : 0;
        $match_config = MatchConfig::where('league_id', $league->id)->first();
        if ($match_config === null) {
            $match_config = (object) [
                'id' => 0,
                'league_id' => $league->id,
                'number_of_pools' => 0,
                'wins_required' => 0,
                'frequency_type' => 0,
                'frequency_value' => 0,
                'duration' => 0,
                'status' => 0,
            ];
        }
        $league->load('draftConfig');

        if ($league->logo !== null) {
            /** @var FilesystemAdapter $disk */
            $disk = Storage::disk('s3-league-logos');
            $league->logo = str_replace('\\', '/', $disk->url($league->logo));
        }

        return [
            'league' => $league,
            'teams' => $teams,
            'draft' => ($this->readLeagueDraftAction)(['league_id' => $league->id]),
            'adminFlag' => $adminflag,
            'matchConfig' => $match_config,
        ];
    }
}
