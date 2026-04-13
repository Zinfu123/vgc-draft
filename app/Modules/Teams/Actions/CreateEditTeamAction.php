<?php

namespace App\Modules\Teams\Actions;

use App\Models\User;
use App\Modules\League\Enums\LeagueStatus;
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

    /**
     * Asserts no other team in the league shares the same effective Showdown username (case-insensitive).
     */
    private function assertShowdownUsernameUniqueInLeague(int $leagueId, string $effectiveUsername, ?int $excludeTeamId = null): void
    {
        $normalized = ShowdownUsernameNormalizer::normalize($effectiveUsername);

        $teams = Team::query()
            ->where('league_id', $leagueId)
            ->when($excludeTeamId !== null, fn ($q) => $q->where('id', '!=', $excludeTeamId))
            ->with('user')
            ->get();

        foreach ($teams as $team) {
            $teamEffective = $team->effectiveShowdownUsername();
            if ($teamEffective !== null && ShowdownUsernameNormalizer::normalize($teamEffective) === $normalized) {
                throw ValidationException::withMessages([
                    'showdown_username' => ['This Showdown username is already registered to another team in this league.'],
                ]);
            }
        }
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

        $effectiveUsername = $showdownToStore ?? $user->showdown_username;
        if ($effectiveUsername !== null) {
            $this->assertShowdownUsernameUniqueInLeague($request->integer('league_id'), $effectiveUsername);
        }

        if ($request->hasFile('logo')) {
            $logo = (new TeamLogoUploadAction)->upload($request);
        } else {
            $logo = null;
        }
        $joiningLeague = League::query()->where('id', $request->league_id)->firstOrFail();

        if ($joiningLeague->status !== LeagueStatus::Registration) {
            throw ValidationException::withMessages([
                'league_id' => 'This league is no longer accepting new teams.',
            ]);
        }

        if (Team::where('league_id', $request->league_id)->where('user_id', $request->user_id)->exists()) {
            throw new \Exception('Team already exists');
        } elseif (Team::where('league_id', $request->league_id)->count() >= $joiningLeague->maximum_teams) {
            throw new \Exception('Maximum number of teams reached');
        }

        $joiningLeague->loadMissing('draftConfig');
        $draftPoints = $joiningLeague->draftConfig->draft_points;
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
        if (Team::where('league_id', $request->league_id)->count() == $joiningLeague->maximum_teams) {
            $joiningLeague->open = false;
            $joiningLeague->save();
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

        $effectiveUsername = $team->showdown_username ?? $user->showdown_username;
        if ($effectiveUsername !== null) {
            $this->assertShowdownUsernameUniqueInLeague($team->league_id, $effectiveUsername, $team->id);
        }

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
