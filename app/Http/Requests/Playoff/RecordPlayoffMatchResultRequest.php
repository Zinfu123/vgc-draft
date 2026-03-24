<?php

namespace App\Http\Requests\Playoff;

use App\Modules\League\Models\League;
use App\Modules\Playoffs\Models\Playoff;
use App\Modules\Playoffs\Models\PlayoffMatch;
use App\Modules\Pokepaste\Services\EnforceTeamMatchPokepasteChecker;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RecordPlayoffMatchResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $league = $this->route('league');

        return $user !== null && $league instanceof League && $user->can('admin', $league);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var League $league */
        $league = $this->route('league');

        return [
            'playoff_match_id' => [
                'required',
                'integer',
                Rule::exists('playoff_matches', 'id')->whereIn(
                    'playoff_id',
                    Playoff::query()->where('league_id', $league->id)->select('id')
                ),
            ],
            'team1_score' => ['required', 'integer', 'min:0', 'max:2'],
            'team2_score' => ['required', 'integer', 'min:0', 'max:2'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $team1Score = (int) $this->input('team1_score');
            $team2Score = (int) $this->input('team2_score');

            $scoreOk = ($team1Score === 2 && $team2Score <= 1) || ($team2Score === 2 && $team1Score <= 1);
            if (! $scoreOk) {
                $validator->errors()->add(
                    'playoff_result',
                    'One team must reach 2 game wins before the set can be submitted (2-0 or 2-1).'
                );

                return;
            }

            /** @var League $league */
            $league = $this->route('league');
            $match = PlayoffMatch::query()->find((int) $this->input('playoff_match_id'));
            if ($match === null) {
                return;
            }

            $league->loadMissing('matchConfig');
            if ($league->matchConfig?->require_team_match_pokepaste_before_results !== true) {
                return;
            }

            if (! app(EnforceTeamMatchPokepasteChecker::class)->playoffMatchBothSidesHaveData($match)) {
                $validator->errors()->add(
                    'playoff_result',
                    'Both teams must submit their match teamsheet (Pokepaste) before a playoff result can be recorded.'
                );
            }
        });
    }

    public function playoffMatch(): ?PlayoffMatch
    {
        return PlayoffMatch::query()->find((int) $this->input('playoff_match_id'));
    }
}
