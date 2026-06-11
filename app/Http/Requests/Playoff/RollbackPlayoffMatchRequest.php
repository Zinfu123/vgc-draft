<?php

namespace App\Http\Requests\Playoff;

use App\Http\Requests\Playoff\Concerns\ResolvesRouteLeague;
use App\Modules\Playoffs\Models\Playoff;
use App\Modules\Playoffs\Models\PlayoffMatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RollbackPlayoffMatchRequest extends FormRequest
{
    use ResolvesRouteLeague;

    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->can('admin', $this->routeLeague());
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $league = $this->routeLeague();

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
