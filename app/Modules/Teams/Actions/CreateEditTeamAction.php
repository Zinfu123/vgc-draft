<?php

namespace App\Modules\Teams\Actions;

use App\Models\User;
use App\Modules\League\Models\League;
use App\Modules\Pokepaste\Services\ShowdownUsernameNormalizer;
use App\Modules\Teams\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CreateEditTeamAction
{
    /**
     * @return array<int, string|\Illuminate\Contracts\Validation\ValidationRule>
     */
    private function showdownUsernameRules(): array
    {
        return ['nullable', 'string', 'max:64', 'regex:/^[a-zA-Z0-9_\-\[\] ]*$/'];
    }

    /**
     * Persisted team showdown override: null means "use coach profile". Non-null only when different from profile or profile empty.
     */
    private function resolveShowdownUsernameForStorage(string $rawInput, User $user): ?string
    {
        $trimmedInput = trim($rawInput);
        $trimmedUser = $user->showdown_username !== null ? trim((string) $user->showdown_username) : '';

        $normalizedInput = $trimmedInput !== '' ? ShowdownUsernameNormalizer::normalize($trimmedInput) : null;
        $normalizedUser = $trimmedUser !== '' ? ShowdownUsernameNormalizer::normalize($trimmedUser) : null;

        if ($normalizedInput === null && $normalizedUser === null) {
            throw ValidationException::withMessages([
                'showdown_username' => ['Add your Pokémon Showdown username in Profile or enter one for this team.'],
            ]);
        }

        if ($trimmedInput === '') {
            return null;
        }

        if ($normalizedUser !== null && $normalizedInput === $normalizedUser) {
            return null;
        }

        return $trimmedInput;
    }

    private function assertCoachHasShowdownUsername(User $user, ?string $teamOverride): void
    {
        $normalizedUser = $user->showdown_username !== null && trim((string) $user->showdown_username) !== ''
            ? ShowdownUsernameNormalizer::normalize(trim((string) $user->showdown_username))
            : null;
        $normalizedTeam = $teamOverride !== null && trim($teamOverride) !== ''
            ? ShowdownUsernameNormalizer::normalize(trim($teamOverride))
            : null;

        if ($normalizedUser === null && $normalizedTeam === null) {
            throw ValidationException::withMessages([
                'showdown_username' => ['Add your Pokémon Showdown username in Profile or enter one for this team.'],
            ]);
        }
    }

    public function create(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'league_id' => 'required|exists:leagues,id',
            'user_id' => 'required|exists:users,id',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'pick_position' => 'required|integer',
            'showdown_username' => $this->showdownUsernameRules(),
        ]);
        $user = User::query()->findOrFail($request->integer('user_id'));

        $rawShowdownInput = (string) $request->input('showdown_username', '');
        $showdownToStore = $this->resolveShowdownUsernameForStorage($rawShowdownInput, $user);

        if ($request->hasFile('logo')) {
            $logo = (new TeamLogoUploadAction)->upload($request);
        } else {
            $logo = null;
        }
        if (Team::where('league_id', $request->league_id)->where('user_id', $request->user_id)->exists()) {
            throw new \Exception('Team already exists');
        } elseif (Team::where('league_id', $request->league_id)->count() >= League::where('id', $request->league_id)->select('maximum_teams')->first()->maximum_teams) {
            throw new \Exception('Maximum number of teams reached');
        }

        $draftPoints = League::with('draftConfig')->find($request->league_id)->draftConfig->draft_points;
        $team = Team::create([
            'name' => $request->name,
            'showdown_username' => $showdownToStore,
            'league_id' => $request->league_id,
            'user_id' => $request->user_id,
            'logo' => $logo,
            'pick_position' => $request->pick_position,
            'draft_points' => $draftPoints,
        ]);
        $teamcount = Team::where('league_id', $request->league_id)->count();
        if ($teamcount == 1) {
            $team->admin_flag = 1;
        }

        $team->save();
        if (Team::where('league_id', $request->league_id)->count() == League::where('id', $request->league_id)->select('maximum_teams')->first()->maximum_teams) {
            $league = League::where('id', $request->league_id)->first();
            $league->open = false;
            $league->save();
        }

        return $team;
    }

    public function edit(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'showdown_username' => $this->showdownUsernameRules(),
        ]);

        $team = Team::query()->whereKey($request->integer('team_id'))->with('user')->firstOrFail();
        $team->name = $request->name;

        $user = $team->user ?? User::query()->findOrFail($team->user_id);
        $rawShowdownInput = (string) $request->input('showdown_username', '');
        $trimmedInput = trim($rawShowdownInput);

        if ($trimmedInput === '') {
            $team->showdown_username = null;
        } else {
            $trimmedUser = $user->showdown_username !== null ? trim((string) $user->showdown_username) : '';
            $nInput = ShowdownUsernameNormalizer::normalize($trimmedInput);
            $nUser = $trimmedUser !== '' ? ShowdownUsernameNormalizer::normalize($trimmedUser) : null;
            $team->showdown_username = ($nUser !== null && $nInput === $nUser) ? null : $trimmedInput;
        }

        $this->assertCoachHasShowdownUsername($user, $team->showdown_username);

        if ($request->hasFile('logo')) {
            if ($team->logo !== null) {
                $oldlogo = $team->logo;
                Storage::disk('s3-team-logos')->delete($oldlogo);
            }
            $logo = (new TeamLogoUploadAction)->upload($request);
            $team->logo = $logo;
        }
        $team->save();

        return $team;
    }
}
