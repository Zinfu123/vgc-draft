<?php

namespace App\Http\Requests\Match;

use App\Modules\League\Models\League;
use App\Modules\Matches\Models\Set;
use App\Modules\Pokepaste\Services\EnforceTeamMatchPokepasteChecker;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateSetRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->user() === null) {
            return false;
        }

        if ($this->input('command') !== 'reopen') {
            return true;
        }

        $set = Set::query()->find((int) $this->input('set_id'));
        if ($set === null) {
            return true;
        }

        $league = League::query()->find($set->league_id);

        return $league !== null && $this->user()->can('admin', $league);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        if ($this->input('command') === 'updatePokepaste') {
            return [
                'command' => 'required|string|in:updatePokepaste',
                'set_id' => 'required|integer|exists:sets,id',
                'team1_pokepaste' => 'nullable|string|max:2000',
                'team2_pokepaste' => 'nullable|string|max:2000',
            ];
        }

        if ($this->input('command') === 'reopen') {
            return [
                'command' => 'required|string|in:reopen',
                'set_id' => [
                    'required',
                    'integer',
                    Rule::exists('sets', 'id')->where('status', 0),
                ],
            ];
        }

        return [
            'command' => 'required|string|in:update',
            'set_id' => 'required|integer|exists:sets,id',
            'team1_id' => 'required|integer|exists:teams,id',
            'team2_id' => 'required|integer|exists:teams,id',
            'team1_score' => 'required|integer|min:0|max:2',
            'team2_score' => 'required|integer|min:0|max:2',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->input('command') !== 'update') {
                return;
            }

            $team1Score = (int) $this->input('team1_score');
            $team2Score = (int) $this->input('team2_score');

            $scoreOk = ($team1Score === 2 && $team2Score <= 1) || ($team2Score === 2 && $team1Score <= 1);
            if (! $scoreOk) {
                $validator->errors()->add(
                    'set_result',
                    'One team must reach 2 game wins before the set can be submitted (2-0 or 2-1).'
                );

                return;
            }

            $set = Set::query()->find((int) $this->input('set_id'));
            if ($set === null) {
                return;
            }

            $league = League::query()->with('matchConfig')->find($set->league_id);
            if ($league?->matchConfig?->require_team_match_pokepaste_before_results !== true) {
                return;
            }

            if (! app(EnforceTeamMatchPokepasteChecker::class)->poolSetBothSidesHaveData($set)) {
                $validator->errors()->add(
                    'set_result',
                    'Both teams must submit their match teamsheet (Pokepaste) before results can be saved.'
                );
            }
        });
    }
}
