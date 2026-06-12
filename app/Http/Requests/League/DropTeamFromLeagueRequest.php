<?php

namespace App\Http\Requests\League;

use App\Http\Requests\Concerns\ResolvesRouteLeague;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DropTeamFromLeagueRequest extends FormRequest
{
    use ResolvesRouteLeague;

    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->can('admin', $this->routeLeague());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $league = $this->routeLeague();

        return [
            'team_id' => [
                'required',
                'integer',
                Rule::exists('teams', 'id')->where('league_id', $league->id),
            ],
        ];
    }
}
