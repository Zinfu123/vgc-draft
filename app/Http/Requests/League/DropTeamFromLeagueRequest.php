<?php

namespace App\Http\Requests\League;

use App\Modules\League\Models\League;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DropTeamFromLeagueRequest extends FormRequest
{
    public function authorize(): bool
    {
        $league = $this->route('league');

        return $league instanceof League
            && $this->user() !== null
            && $this->user()->can('admin', $league);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var League $league */
        $league = $this->route('league');

        return [
            'team_id' => [
                'required',
                'integer',
                Rule::exists('teams', 'id')->where('league_id', $league->id),
            ],
        ];
    }
}
