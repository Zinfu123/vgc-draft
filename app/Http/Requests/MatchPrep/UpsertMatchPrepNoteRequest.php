<?php

namespace App\Http\Requests\MatchPrep;

use App\Modules\League\Models\LeaguePokemon;
use App\Modules\Matches\Models\Set;
use App\Modules\Teams\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpsertMatchPrepNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $set = $this->route('set');

        if ($user === null || ! $set instanceof Set) {
            return false;
        }

        return Team::query()
            ->where('user_id', $user->id)
            ->where('league_id', $set->league_id)
            ->whereIn('id', [(int) $set->team1_id, (int) $set->team2_id])
            ->exists();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'bring_six_slots' => ['required', 'array', 'size:6'],
            'bring_six_slots.*' => ['nullable', 'integer'],
            'plan_1_slots' => ['required', 'array', 'size:4'],
            'plan_1_slots.*' => ['nullable', 'integer'],
            'plan_2_slots' => ['required', 'array', 'size:4'],
            'plan_2_slots.*' => ['nullable', 'integer'],
            'plan_3_slots' => ['required', 'array', 'size:4'],
            'plan_3_slots.*' => ['nullable', 'integer'],
            'plan_1_notes' => ['nullable', 'string', 'max:65535'],
            'plan_2_notes' => ['nullable', 'string', 'max:65535'],
            'plan_3_notes' => ['nullable', 'string', 'max:65535'],
            'calcs' => ['nullable', 'array', 'max:40'],
            'calcs.*.my_league_pokemon_id' => ['required', 'integer'],
            'calcs.*.opponent_league_pokemon_id' => ['required', 'integer'],
            'calcs.*.body' => ['nullable', 'string', 'max:50000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('calcs')) {
            $this->merge(['calcs' => []]);
        }

        $calcs = $this->input('calcs', []);
        if (! is_array($calcs)) {
            $this->merge(['calcs' => []]);

            return;
        }
        $filtered = [];
        foreach ($calcs as $c) {
            if (! is_array($c)) {
                continue;
            }
            $my = $c['my_league_pokemon_id'] ?? null;
            $opp = $c['opponent_league_pokemon_id'] ?? null;
            $body = trim((string) ($c['body'] ?? ''));
            if (($my === null || $my === '') && ($opp === null || $opp === '') && $body === '') {
                continue;
            }
            $filtered[] = $c;
        }
        $this->merge(['calcs' => $filtered]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $user = $this->user();
            $set = $this->route('set');
            if ($user === null || ! $set instanceof Set) {
                return;
            }

            $team = Team::query()
                ->where('user_id', $user->id)
                ->where('league_id', $set->league_id)
                ->first();

            if ($team === null) {
                return;
            }

            $opponentTeamId = (int) $set->team1_id === (int) $team->id
                ? (int) $set->team2_id
                : (int) $set->team1_id;

            $this->validateSlotsOnTeam($validator, 'bring_six_slots', $this->input('bring_six_slots', []), $team->id, (int) $set->league_id);

            $bringRaw = $this->input('bring_six_slots', []);
            $bringIds = [];
            if (is_array($bringRaw)) {
                foreach ($bringRaw as $v) {
                    if (is_numeric($v)) {
                        $bringIds[] = (int) $v;
                    }
                }
            }
            $bringSet = array_unique($bringIds);

            foreach (['plan_1_slots', 'plan_2_slots', 'plan_3_slots'] as $key) {
                $slots = $this->input($key, []);
                if (! is_array($slots)) {
                    continue;
                }
                foreach ($slots as $index => $leaguePokemonId) {
                    if ($leaguePokemonId === null || $leaguePokemonId === '') {
                        continue;
                    }
                    if (! is_numeric($leaguePokemonId)) {
                        $validator->errors()->add($key.'.'.$index, __('Invalid Pokémon selection.'));

                        continue;
                    }
                    $id = (int) $leaguePokemonId;
                    $lp = LeaguePokemon::query()->find($id);
                    if ($lp === null || (int) $lp->drafted_by !== (int) $team->id || (int) $lp->league_id !== (int) $set->league_id) {
                        $validator->errors()->add($key.'.'.$index, __('That Pokémon is not on your team in this league.'));

                        continue;
                    }
                    if ($bringSet !== [] && ! in_array($id, $bringSet, true)) {
                        $validator->errors()->add($key.'.'.$index, __('Choose Pokémon from your bring-6 above.'));
                    }
                }
            }

            $calcs = $this->input('calcs', []);
            if (! is_array($calcs)) {
                return;
            }
            foreach ($calcs as $index => $c) {
                if (! is_array($c)) {
                    continue;
                }
                $my = $c['my_league_pokemon_id'] ?? null;
                $opp = $c['opponent_league_pokemon_id'] ?? null;
                if (! is_numeric($my)) {
                    $validator->errors()->add('calcs.'.$index.'.my_league_pokemon_id', __('Select your Pokémon for this calc.'));

                    continue;
                }
                if (! is_numeric($opp)) {
                    $validator->errors()->add('calcs.'.$index.'.opponent_league_pokemon_id', __('Select the opponent Pokémon for this calc.'));

                    continue;
                }
                $myLp = LeaguePokemon::query()->find((int) $my);
                if ($myLp === null || (int) $myLp->drafted_by !== (int) $team->id || (int) $myLp->league_id !== (int) $set->league_id) {
                    $validator->errors()->add('calcs.'.$index.'.my_league_pokemon_id', __('That Pokémon is not on your team in this league.'));
                }
                $oppLp = LeaguePokemon::query()->find((int) $opp);
                if ($oppLp === null || (int) $oppLp->drafted_by !== $opponentTeamId || (int) $oppLp->league_id !== (int) $set->league_id) {
                    $validator->errors()->add('calcs.'.$index.'.opponent_league_pokemon_id', __('That Pokémon is not on the opponent team in this league.'));
                }
            }
        });
    }

    /**
     * @param  array<int, mixed>  $slots
     */
    private function validateSlotsOnTeam(Validator $validator, string $key, array $slots, int $teamId, int $leagueId): void
    {
        foreach ($slots as $index => $leaguePokemonId) {
            if ($leaguePokemonId === null || $leaguePokemonId === '') {
                continue;
            }
            if (! is_numeric($leaguePokemonId)) {
                $validator->errors()->add($key.'.'.$index, __('Invalid Pokémon selection.'));

                continue;
            }
            $lp = LeaguePokemon::query()->find((int) $leaguePokemonId);
            if ($lp === null || (int) $lp->drafted_by !== $teamId || (int) $lp->league_id !== $leagueId) {
                $validator->errors()->add($key.'.'.$index, __('That Pokémon is not on your team in this league.'));
            }
        }
    }
}
