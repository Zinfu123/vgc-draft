<?php

namespace App\Modules\League\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\League\ImportLeaguePokemonCsvRequest;
use App\Modules\League\Actions\CreateEditLeaguePokemonAction;
use App\Modules\League\Actions\ReadLeagueDraftAction;
use App\Modules\League\Actions\ReadLeaguePokemonAction;
use App\Modules\League\Models\League;
use App\Modules\Matches\Models\MatchConfig;
use App\Modules\Teams\Actions\ReadTeamAction;
use App\Modules\Teams\Models\Team;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class LeaguePokemonController extends Controller
{
    public function read(League $league, ReadLeaguePokemonAction $readLeaguePokemonAction, ReadTeamAction $readTeamAction, ReadLeagueDraftAction $readLeagueDraftAction)
    {
        $teams = $readTeamAction(['league_id' => $league->id, 'command' => 'league']);
        $user_team = Team::where('user_id', Auth::id())->where('league_id', $league->id)->select('id', 'admin_flag')->first();
        $adminFlag = $user_team ? $user_team->admin_flag : 0;
        $matchConfig = MatchConfig::where('league_id', $league->id)->first();
        if ($matchConfig === null) {
            $matchConfig = (object) [
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
        if ($league->logo !== null) {
            /** @var FilesystemAdapter $disk */
            $disk = Storage::disk('s3-league-logos');
            $league->logo = str_replace('\\', '/', $disk->url($league->logo));
        }

        return Inertia::render('league/LeaguePokemonPage', [
            'league' => $league,
            'section' => 'pokemon',
            'teams' => $teams,
            'draft' => $readLeagueDraftAction(['league_id' => $league->id]),
            'adminFlag' => $adminFlag,
            'matchConfig' => $matchConfig,
            'pokemon' => $readLeaguePokemonAction(['league_id' => $league->id, 'command' => 'available']),
            'pokemon_drafted' => $readLeaguePokemonAction(['league_id' => $league->id, 'command' => 'draftedpokemon']),
        ]);
    }

    public function create(ImportLeaguePokemonCsvRequest $request, CreateEditLeaguePokemonAction $createEditLeaguePokemonAction): \Illuminate\Http\RedirectResponse
    {
        $leagueId = $request->route('league') instanceof League
            ? $request->route('league')->id
            : (int) $request->input('league_id');

        $createEditLeaguePokemonAction([
            'league_id' => $leagueId,
            'csv_file' => $request->file('csv_file'),
        ]);

        return redirect()->route('leagues.admin.pokemon-pool', ['league' => $leagueId])
            ->with('success', 'CSV import completed.');
    }
}
