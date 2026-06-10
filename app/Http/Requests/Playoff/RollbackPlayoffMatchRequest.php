<?php

namespace App\Http\Requests\Playoff;

use App\Modules\League\Models\League;
use App\Modules\Playoffs\Models\Playoff;
use App\Modules\Playoffs\Models\PlayoffMatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RollbackPlayoffMatchRequest extends FormRequest
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
        ];
    }

    public function playoffMatch(): ?PlayoffMatch
    {
        return PlayoffMatch::query()->find((int) $this->input('playoff_match_id'));
    }
}
